<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

declare(strict_types=1);

namespace Avarda\Checkout3\Model;

use Avarda\Checkout3\Api\OrphanPurchaseResolverInterface;
use Avarda\Checkout3\Api\PendingOrderCancellationGuardInterface;
use Avarda\Checkout3\Api\QuotePaymentManagementInterface;
use Avarda\Checkout3\Exception\PurchaseLockedException;
use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PurchaseState;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class PendingOrderCancellationGuard implements PendingOrderCancellationGuardInterface
{
    protected QuotePaymentManagementInterface $quotePaymentManagement;
    protected OrphanPurchaseResolverInterface $orphanPurchaseResolver;
    protected OrderRepositoryInterface $orderRepository;
    protected PaymentData $paymentDataHelper;
    protected PurchaseState $purchaseStateHelper;
    protected LockManagerInterface $lockManager;
    protected LoggerInterface $logger;

    public function __construct(
        QuotePaymentManagementInterface $quotePaymentManagement,
        OrphanPurchaseResolverInterface $orphanPurchaseResolver,
        OrderRepositoryInterface $orderRepository,
        PaymentData $paymentDataHelper,
        PurchaseState $purchaseStateHelper,
        LockManagerInterface $lockManager,
        LoggerInterface $logger,
    ) {
        $this->quotePaymentManagement = $quotePaymentManagement;
        $this->orphanPurchaseResolver = $orphanPurchaseResolver;
        $this->orderRepository = $orderRepository;
        $this->paymentDataHelper = $paymentDataHelper;
        $this->purchaseStateHelper = $purchaseStateHelper;
        $this->lockManager = $lockManager;
        $this->logger = $logger;
    }

    public function cancelUnlessPaid(OrderInterface $order, callable $cancel): void
    {
        $payment = $order->getPayment();
        if (!$this->paymentDataHelper->isAvardaPayment($payment)) {
            $cancel();
            return;
        }

        // PaymentComplete and SaveOrder hold this lock while completing the same order
        $purchaseId = $this->paymentDataHelper->getPurchaseId($payment);
        if ($purchaseId !== null && !$this->lockManager->lock($purchaseId, 0)) {
            $this->logger->info(sprintf(
                'Order %s not cancelled: purchase %s is being processed elsewhere',
                $order->getIncrementId(),
                $purchaseId
            ));
            return;
        }

        try {
            $this->quotePaymentManagement->updateOnlyOrderPaymentStatus($order);
            $state = $this->paymentDataHelper->getState($payment);
            if ($this->purchaseStateHelper->isComplete($state)) {
                try {
                    $this->quotePaymentManagement->finalizeOrder($order);
                    return;
                } catch (PaymentException $e) {
                    // The completed purchase does not cover the order total, so it is not really paid
                    $this->logger->warning(sprintf(
                        'Order %s finalize refused, cancelling as unpaid: %s',
                        $order->getIncrementId(),
                        $e->getMessage()
                    ));
                    $cancel();
                    return;
                }
            }

            try {
                $result = $this->orphanPurchaseResolver->rebindToNewerPurchase($order);
            } catch (PurchaseLockedException $e) {
                $this->logger->info($e->getMessage());
                return;
            }

            if ($result === OrphanPurchaseResolverInterface::RESULT_REBOUND) {
                // The status handler saves the repository instance, so finalize that one
                $reboundOrder = $this->orderRepository->get($order->getEntityId());
                $this->quotePaymentManagement->updateOrderPaymentStatus($reboundOrder);
                $this->quotePaymentManagement->finalizeOrder($reboundOrder);
                return;
            }
            if ($result === OrphanPurchaseResolverInterface::RESULT_NEWER_PENDING) {
                $this->logger->info(sprintf(
                    'Order %s not cancelled: a newer Avarda purchase of its quote may still be paid',
                    $order->getIncrementId()
                ));
                return;
            }

            $cancel();
        } finally {
            if ($purchaseId !== null) {
                $this->lockManager->unlock($purchaseId);
            }
        }
    }
}
