<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
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

class GetPaymentStatusHandler implements HandlerInterface
{
    /** @var CartRepositoryInterface */
    protected $quoteRepository;

    /** @var AddressInterfaceFactory */
    protected $addressFactory;

    /** @var PaymentFactory */
    protected $paymentFactory;

    /** @var PaymentMethod */
    protected $methodHelper;

    public function __construct(
        CartRepositoryInterface $quoteRepository,
        AddressInterfaceFactory $addressFactory,
        PaymentMethod $paymentMethod,
        PaymentFactory $paymentFactory
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->addressFactory = $addressFactory;
        $this->paymentFactory = $paymentFactory;
        $this->methodHelper = $paymentMethod;
    }
    /**
     * {@inheritdoc}
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();
        $order = $paymentDO->getOrder();

        $entityId = $order->getId();
        $quote = $this->quoteRepository->get($entityId);

        $mode = $response['mode'] == 'B2B' ? 'b2B' : 'b2C';

        $telephone = $response[$mode]['userInputs']['phone'];
        $email = $response[$mode]['userInputs']['email'];
        $quote->setCustomerEmail($email);

        $billingAddress = $this->addressFactory->create();
        $billingAddress->setTelephone($telephone);
        $billingAddress->setEmail($email);
        if ($mode == 'b2C') {
            $billingAddress->setFirstname($response[$mode]['invoicingAddress']['firstName']);
            $billingAddress->setLastname($response[$mode]['invoicingAddress']['lastName']);
        } else {
            $billingAddress->setFirstname($response[$mode]['invoicingAddress']['name']);
            $billingAddress->setLastname($response[$mode]['invoicingAddress']['name']);
        }
        $billingAddress->setStreet($response[$mode]['invoicingAddress']['address1']);
        $billingAddress->setPostcode($response[$mode]['invoicingAddress']['zip']);
        $billingAddress->setCity($response[$mode]['invoicingAddress']['city']);
        $billingAddress->setCountryId($response[$mode]['invoicingAddress']['country']);
        $quote->setBillingAddress($billingAddress);

        if ($response[$mode]['deliveryAddress']['firstName']) {
            $shippingAddress = $this->addressFactory->create();
            $shippingAddress->setTelephone($telephone);
            $shippingAddress->setEmail($email);
            $shippingAddress->setFirstname($response[$mode]['deliveryAddress']['firstName']);
            $shippingAddress->setLastname($response[$mode]['deliveryAddress']['lastName']);
            $shippingAddress->setStreet($response[$mode]['deliveryAddress']['address1']);
            $shippingAddress->setPostcode($response[$mode]['deliveryAddress']['zip']);
            $shippingAddress->setCity($response[$mode]['deliveryAddress']['city']);
            $shippingAddress->setCountryId($response[$mode]['deliveryAddress']['country']);
            $quote->setShippingAddress($shippingAddress);
        } else {
            $quote->setShippingAddress($billingAddress);
        }

        // Set payment method
        if (isset($response['paymentMethods']['selectedPayment']['type'])) {
            $paymentMethod = $this->methodHelper->getPaymentMethod($response['paymentMethods']['selectedPayment']['type']);
            $quote->getPayment()->setMethod($paymentMethod);
        }

        // Set payment state
        $quote->getPayment()->setAdditionalInformation(
            PaymentData::STATE,
            $response[$mode]['step']['current']
        );
    }
}
