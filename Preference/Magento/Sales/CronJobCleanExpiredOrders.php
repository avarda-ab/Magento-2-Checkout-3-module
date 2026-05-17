<?php

namespace Avarda\Checkout3\Preference\Magento\Sales;

use Avarda\Checkout3\Api\QuotePaymentManagementInterface;
use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PurchaseState;
use Exception;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\CronJob\CleanExpiredOrders;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Model\StoresConfig;
use Psr\Log\LoggerInterface;

class CronJobCleanExpiredOrders extends CleanExpiredOrders
{
    protected OrderManagementInterface $orderManagement;
    protected OrderRepositoryInterface $orderRepository;
    protected QuotePaymentManagementInterface $quotePaymentManagement;
    protected PaymentData $paymentData;
    protected LoggerInterface $logger;

    public function __construct(
        StoresConfig $storesConfig,
        CollectionFactory $collectionFactory,
        OrderManagementInterface $orderManagement,
        OrderRepositoryInterface $orderRepository,
        QuotePaymentManagementInterface $quotePaymentManagement,
        PaymentData $paymentData,
        LoggerInterface $logger
    ) {
        parent::__construct($storesConfig, $collectionFactory, $orderManagement);
        $this->orderManagement = $orderManagement;
        $this->orderRepository = $orderRepository;
        $this->quotePaymentManagement = $quotePaymentManagement;
        $this->paymentData = $paymentData;
        $this->logger = $logger;
    }

    /**
     * Override to check the status from Avarda before canceling
     * Clean expired quotes (cron process)
     *
     * @return void
     */
    public function execute()
    {
        $lifetimes = $this->storesConfig->getStoresConfigByPath('sales/orders/delete_pending_after');
        foreach ($lifetimes as $storeId => $lifetime) {
            $orders = $this->orderCollectionFactory->create();
            $orders->addFieldToFilter('store_id', $storeId);
            $orders->addFieldToFilter('status', Order::STATE_PENDING_PAYMENT);
            $orders->getSelect()->where(
                new \Zend_Db_Expr('TIME_TO_SEC(TIMEDIFF(CURRENT_TIMESTAMP, `updated_at`)) >= ' . $lifetime * 60)
            );

            foreach ($orders->getAllIds() as $entityId) {
                try {
                    $order = clone $this->orderRepository->get($entityId);

                    if ($this->paymentData->isAvardaPayment($order->getPayment())) {
                        // Update payment status to make sure it is not paid late
                        $this->quotePaymentManagement->updateOnlyOrderPaymentStatus($order);
                        $state = $this->paymentData->getState($order->getPayment());
                        // If the order is completed, finalize it instead of canceling
                        if ($state == PurchaseState::COMPLETED) {
                            $this->quotePaymentManagement->finalizeOrder($order);
                            continue;
                        }
                    }

                    $this->orderManagement->cancel((int) $entityId);
                } catch (Exception $e) {
                    $this->logger->warning('Failed to process order ' . ($entityId) . ': ' . $e->getMessage());
                }
            }
        }
    }
}
