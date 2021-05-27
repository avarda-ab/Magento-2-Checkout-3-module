<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Observer;

use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PurchaseState;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;

class OrderCancelObserver implements ObserverInterface
{
    /** @var PaymentData */
    protected $paymentDataHelper;

    /** @var PurchaseState */
    protected $purchaseState;

    /** @var CommandPoolInterface */
    protected $commandPool;

    /** @var PaymentDataObjectFactoryInterface */
    protected $paymentDataObjectFactory;

    public function __construct(
        PaymentData $paymentDataHelper,
        PurchaseState $purchaseState,
        CommandPoolInterface $commandPool,
        PaymentDataObjectFactoryInterface $paymentDataObjectFactory
    ) {
        $this->paymentDataHelper = $paymentDataHelper;
        $this->purchaseState = $purchaseState;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->commandPool = $commandPool;
    }

    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getData('order');
        $payment = $order->getPayment();
        if ($this->paymentDataHelper->isAvardaPayment($payment)) {
            $payment = $order->getPayment();
            $state = $this->paymentDataHelper->getState($payment);

            // Only cancel online if status is completed
            if ($this->purchaseState->isComplete($state)) {

                /** @var InfoInterface|null $payment */
                if ($payment !== null && $payment instanceof InfoInterface) {
                    $arguments['payment'] = $this->paymentDataObjectFactory
                        ->create($payment);
                }

                $arguments['amount'] = $payment->getAmountOrdered();
                $this->commandPool->get('avarda_cancel')->execute($arguments);
            }
        }
    }
}
