<?php

namespace Avarda\Checkout3\Preference\Magento\Sales;

use Avarda\Checkout3\Api\PendingOrderCancellationGuardInterface;
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
    protected LoggerInterface $logger;
    protected PendingOrderCancellationGuardInterface $cancellationGuard;

    public function __construct(
        StoresConfig $storesConfig,
        CollectionFactory $collectionFactory,
        OrderManagementInterface $orderManagement,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger,
        PendingOrderCancellationGuardInterface $cancellationGuard,
    ) {
        parent::__construct($storesConfig, $collectionFactory, $orderManagement);
        $this->orderManagement = $orderManagement;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        $this->cancellationGuard = $cancellationGuard;
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

                    $this->cancellationGuard->cancelUnlessPaid($order, function () use ($entityId) {
                        $this->orderManagement->cancel((int) $entityId);
                    });
                } catch (Exception $e) {
                    $this->logger->warning('Failed to process order ' . ($entityId) . ': ' . $e->getMessage());
                }
            }
        }
    }
}
