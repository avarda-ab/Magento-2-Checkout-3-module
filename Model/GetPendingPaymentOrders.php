<?php

namespace Avarda\Checkout3\Model;

use Avarda\Checkout3\Helper\PaymentData;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class GetPendingPaymentOrders
{
    protected SearchCriteriaBuilder $searchCriteriaBuilder;
    protected OrderRepositoryInterface $orderRepository;
    protected PaymentData $paymentDataHelper;

    public function __construct(
        SearchCriteriaBuilder $searchCriteriaHelper,
        OrderRepositoryInterface $orderRepository,
        PaymentData $paymentDataHelper
    ) {
        $this->paymentDataHelper = $paymentDataHelper;
        $this->searchCriteriaBuilder = $searchCriteriaHelper;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @return OrderInterface[]
     */
    public function execute(): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(OrderInterface::STATE, Order::STATE_PENDING_PAYMENT)
            ->create();
        $orders = $this->orderRepository->getList($searchCriteria);

        $result = [];
        foreach ($orders->getItems() as $order) {
            $payment = $order->getPayment();
            if ($this->paymentDataHelper->isAvardaPayment($payment) &&
                (time() - strtotime($order->getCreatedAt())) > 120 // check that order is at least 2 minutes old
            ) {
                $result[] = $order;
            }
        }

        return $result;
    }
}
