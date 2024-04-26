<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Observer;

use Avarda\Checkout3\Api\AvardaOrderRepositoryInterface;
use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PurchaseState;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;

class OrderCancelObserver implements ObserverInterface
{
    protected PaymentData $paymentDataHelper;
    protected PurchaseState $purchaseState;
    protected CommandPoolInterface $commandPool;
    protected PaymentDataObjectFactoryInterface $paymentDataObjectFactory;
    protected AvardaOrderRepositoryInterface $avardaOrderRepository;

    public function __construct(
        PaymentData $paymentDataHelper,
        PurchaseState $purchaseState,
        CommandPoolInterface $commandPool,
        PaymentDataObjectFactoryInterface $paymentDataObjectFactory,
        AvardaOrderRepositoryInterface $avardaOrderRepository
    ) {
        $this->paymentDataHelper = $paymentDataHelper;
        $this->purchaseState = $purchaseState;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->commandPool = $commandPool;
        $this->avardaOrderRepository = $avardaOrderRepository;
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
                $arguments = [];
                /** @var InfoInterface|null $payment */
                if ($payment instanceof InfoInterface) {
                    $arguments['payment'] = $this->paymentDataObjectFactory
                        ->create($payment);
                }

                // If order is partly invoiced, we need to use refund remaining not cancel
                if ($order->getBaseTotalInvoiced() > 0) {
                    $this->commandPool->get('avarda_refund_remaining')->execute($arguments);
                } else {
                    $this->commandPool->get('avarda_cancel')->execute($arguments);
                }
            } else {
                // If pending payment was canceled delete the order complete row
                try {
                    $avardaOrder = $this->avardaOrderRepository->getByOrderId($order->getId());
                    $this->avardaOrderRepository->delete($avardaOrder);
                } catch (Exception $e) {
                    // Do nothing
                }
            }
        }
    }
}
