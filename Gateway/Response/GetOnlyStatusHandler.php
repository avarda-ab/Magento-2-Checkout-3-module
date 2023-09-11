<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Gateway\Response;

use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PaymentMethod;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Model\Quote\PaymentFactory;

class GetOnlyStatusHandler implements HandlerInterface
{
    /** @var CartRepositoryInterface */
    protected $quoteRepository;

    /** @var PaymentMethod */
    protected $methodHelper;

    public function __construct(
        CartRepositoryInterface $quoteRepository,
        PaymentMethod $paymentMethod
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->methodHelper = $paymentMethod;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();

        $mode = $response['mode'] == 'B2B' ? 'b2B' : 'b2C';

        // Set payment method
        if (isset($response['paymentMethods']['selectedPayment']['type'])) {
            $paymentMethod = $this->methodHelper->getPaymentMethod($response['paymentMethods']['selectedPayment']['type']);
            $payment->setMethod($paymentMethod);
        }

        // Set payment state
        $payment->setAdditionalInformation(
            PaymentData::STATE,
            $response[$mode]['step']['current']
        )->setAdditionalInformation('renew', false);
    }
}
