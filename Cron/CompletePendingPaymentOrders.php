<?php

namespace Avarda\Checkout3\Cron;

use Avarda\Checkout3\Api\AvardaOrderRepositoryInterface;
use Avarda\Checkout3\Api\PaymentCompleteInterface;
use Avarda\Checkout3\Model\GetPendingPaymentOrders;
use Magento\Framework\Exception\PaymentException;

class CompletePendingPaymentOrders {

    protected PaymentCompleteInterface $paymentComplete;
    protected GetPendingPaymentOrders $getPendingPaymentOrders;
    protected AvardaOrderRepositoryInterface $avardaOrderRepository;

    public function __construct(
        PaymentCompleteInterface $paymentComplete,
        GetPendingPaymentOrders $getPendingPaymentOrders,
        AvardaOrderRepositoryInterface $avardaOrderRepository
    ) {
        $this->paymentComplete = $paymentComplete;
        $this->getPendingPaymentOrders = $getPendingPaymentOrders;
        $this->avardaOrderRepository = $avardaOrderRepository;
    }

    /**
     * @throws PaymentException
     */
    public function execute()
    {
        $orders = $this->getPendingPaymentOrders->execute();
        foreach ($orders as $order) {
            $avardaOrder = $this->avardaOrderRepository->getByOrderId($order->getId());
            $this->paymentComplete->execute($avardaOrder->getPurchaseId());
        }
    }
}
