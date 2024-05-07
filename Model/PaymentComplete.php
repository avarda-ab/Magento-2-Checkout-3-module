<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Model;

use Avarda\Checkout3\Api\AvardaOrderRepositoryInterface;
use Avarda\Checkout3\Api\PaymentCompleteInterface;
use Avarda\Checkout3\Api\QuotePaymentManagementInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\PaymentException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class PaymentComplete implements PaymentCompleteInterface
{
    protected QuotePaymentManagementInterface $quotePaymentManagement;
    protected AvardaOrderRepositoryInterface $avardaOrderRepository;
    protected OrderRepositoryInterface $orderRepository;
    protected LoggerInterface $logger;

    public function __construct(
        QuotePaymentManagementInterface $quotePaymentManagement,
        AvardaOrderRepositoryInterface $avardaOrderRepository,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        $this->quotePaymentManagement = $quotePaymentManagement;
        $this->avardaOrderRepository = $avardaOrderRepository;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    /**
     * Handle serverside order complete handling
     *
     * @param $purchaseId
     * @return string
     * @throws LocalizedException
     */
    public function execute($purchaseId)
    {
        try {
            try {
                $orderId = $this->avardaOrderRepository->getByPurchaseId($purchaseId);
                // No order found
                if (!$orderId->getOrderId()) {
                    $this->logger->warning("No order found with '{$purchaseId}'");
                    return "";
                }

                $order = $this->orderRepository->get($orderId->getOrderId());
                $this->quotePaymentManagement->updateOrderPaymentStatus($order);
                $this->quotePaymentManagement->finalizeOrder($order);

                return "OK";
            } catch (PaymentException $e) {
                $this->logger->critical($e);
            }
        } catch (NoSuchEntityException $noSuchEntityException) {
            // Order is already saved
        }
        return "";
    }
}
