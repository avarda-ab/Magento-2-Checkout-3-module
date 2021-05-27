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

        $telephone = $response['b2C']['userInputs']['phone'];
        $email = $response['b2C']['userInputs']['email'];
        $quote->setCustomerEmail($email);

        $billingAddress = $this->addressFactory->create();
        $billingAddress->setTelephone($telephone);
        $billingAddress->setEmail($email);
        $billingAddress->setFirstname($response['b2C']['invoicingAddress']['firstName']);
        $billingAddress->setLastname($response['b2C']['invoicingAddress']['lastName']);
        $billingAddress->setStreet($response['b2C']['invoicingAddress']['address1']);
        $billingAddress->setPostcode($response['b2C']['invoicingAddress']['zip']);
        $billingAddress->setCity($response['b2C']['invoicingAddress']['city']);
        $billingAddress->setCountryId($response['b2C']['invoicingAddress']['country']);
        $quote->setBillingAddress($billingAddress);

        if ($response['b2C']['deliveryAddress']['firstName']) {
            $shippingAddress = $this->addressFactory->create();
            $shippingAddress->setTelephone($telephone);
            $shippingAddress->setEmail($email);
            $shippingAddress->setFirstname($response['b2C']['deliveryAddress']['firstName']);
            $shippingAddress->setLastname($response['b2C']['deliveryAddress']['lastName']);
            $shippingAddress->setStreet($response['b2C']['deliveryAddress']['address1']);
            $shippingAddress->setPostcode($response['b2C']['deliveryAddress']['zip']);
            $shippingAddress->setCity($response['b2C']['deliveryAddress']['city']);
            $shippingAddress->setCountryId($response['b2C']['deliveryAddress']['country']);
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
            $response['b2C']['step']['current']
        );
    }
}
