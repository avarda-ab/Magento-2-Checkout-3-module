<?php

namespace Avarda\Checkout3\Plugin\Controller\Checkout;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;
use Magento\Store\Model\ScopeInterface;

class UpdateAvardaDeliveryAddress
{
    protected Session $checkoutSession;
    protected CommandPoolInterface $commandPool;
    protected PaymentDataObjectFactoryInterface $paymentDataObjectFactory;

    public function __construct(
        Session $checkoutSession,
        CommandPoolInterface $commandPool,
        PaymentDataObjectFactoryInterface $paymentDataObjectFactory,
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->commandPool = $commandPool;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
    }


    public function afterExecute($subject, $result)
    {
        $quote = $this->checkoutSession->getQuote();
        $payment = $quote->getPayment();
        $argument = [
            'payment' => $this->paymentDataObjectFactory->create($payment),
        ];

        try {
            $this->commandPool->get('update_delivery_address')->execute($argument);
        } catch (Exception $e) {
            // Update fails if avarda order has timed out
        }

        return $result;
    }
}
