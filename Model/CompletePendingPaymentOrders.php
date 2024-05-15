<?php

namespace Avarda\Checkout3\Model;

use Avarda\Checkout3\Api\AvardaOrderRepositoryInterface;
use Avarda\Checkout3\Api\PaymentCompleteInterface;
use Magento\Framework\Exception\PaymentException;

class CompletePendingPaymentOrders
{
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
            // Check that purchaseId is set, it means user has been redirected to Avarda
            if ($avardaOrder->getPurchaseId()) {
                $this->paymentComplete->execute($avardaOrder->getPurchaseId());
            }
        }
    }
}
