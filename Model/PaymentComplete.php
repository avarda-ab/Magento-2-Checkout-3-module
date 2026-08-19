<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Model;

use Avarda\Checkout3\Api\AvardaOrderRepositoryInterface;
use Avarda\Checkout3\Api\OrphanPurchaseResolverInterface;
use Avarda\Checkout3\Api\PaymentCompleteInterface;
use Avarda\Checkout3\Api\QuotePaymentManagementInterface;
use Avarda\Checkout3\Exception\PurchaseLockedException;
use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PurchaseState;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class PaymentComplete implements PaymentCompleteInterface
{
    protected QuotePaymentManagementInterface $quotePaymentManagement;
    protected AvardaOrderRepositoryInterface $avardaOrderRepository;
    protected OrderRepositoryInterface $orderRepository;
    protected LoggerInterface $logger;
    protected LockManagerInterface $lockManager;
    protected OrphanPurchaseResolverInterface $orphanPurchaseResolver;
    protected PaymentData $paymentDataHelper;
    protected PurchaseState $purchaseStateHelper;

    public function __construct(
        QuotePaymentManagementInterface $quotePaymentManagement,
        AvardaOrderRepositoryInterface $avardaOrderRepository,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger,
        LockManagerInterface $lockManager,
        OrphanPurchaseResolverInterface $orphanPurchaseResolver,
        PaymentData $paymentDataHelper,
        PurchaseState $purchaseStateHelper,
    ) {
        $this->quotePaymentManagement = $quotePaymentManagement;
        $this->avardaOrderRepository = $avardaOrderRepository;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        $this->lockManager = $lockManager;
        $this->orphanPurchaseResolver = $orphanPurchaseResolver;
        $this->paymentDataHelper = $paymentDataHelper;
        $this->purchaseStateHelper = $purchaseStateHelper;
    }

    /**
     * Handle completing order
     *
     * @param $purchaseId
     * @return string
     * @throws LocalizedException
     */
    public function execute($purchaseId): string
    {
        $wasLocked = false;
        try {
            try {
                // If cron/serverside call is processing order at the same time ignore the request
                if (!$this->lockManager->isLocked($purchaseId)) {
                    $wasLocked = true;
                    $this->lockManager->lock($purchaseId);

                    $orderId = $this->avardaOrderRepository->getByPurchaseId($purchaseId);
                    if ($orderId->getOrderId()) {
                        $order = $this->orderRepository->get($orderId->getOrderId());
                    } else {
                        $order = $this->orphanPurchaseResolver->resolveByPurchaseId($purchaseId);
                    }
                    if (!$order) {
                        $this->logger->warning("No order found with '{$purchaseId}'");
                        return "";
                    }

                    $this->quotePaymentManagement->updateOrderPaymentStatus($order);
                    $state = $this->paymentDataHelper->getState($order->getPayment());
                    // A dead purchase may have been replaced by a newer one the customer actually paid
                    if (!$this->purchaseStateHelper->isComplete($state)
                        && $this->orphanPurchaseResolver->rebindToNewerPurchase($order)
                            === OrphanPurchaseResolverInterface::RESULT_REBOUND
                    ) {
                        $this->quotePaymentManagement->updateOrderPaymentStatus($order);
                    }
                    $this->quotePaymentManagement->finalizeOrder($order);
                    return "OK";
                }
            } catch (PaymentException | PurchaseLockedException $e) {
                $this->logger->info("Payment complete handling did not succeed: " . $e->getMessage());
            }
        } catch (Exception $e) {
            $this->logger->error("Payment complete handling failed with error: " . $e->getMessage());
        } finally {
            if ($wasLocked) {
                $this->lockManager->unlock($purchaseId);
            }
        }

        return "";
    }
}
