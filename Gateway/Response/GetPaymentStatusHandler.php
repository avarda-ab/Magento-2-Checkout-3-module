<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Gateway\Response;

use Avarda\Checkout3\Helper\AvardaCheckBoxTypeValues;
use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PaymentMethod;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Newsletter\Model\SubscriptionManagerInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Model\Quote\PaymentFactory;

class GetPaymentStatusHandler implements HandlerInterface
{
    protected CartRepositoryInterface $quoteRepository;
    protected AddressInterfaceFactory $addressFactory;
    protected PaymentFactory $paymentFactory;
    protected PaymentMethod $methodHelper;
    protected SubscriberFactory $subscriberFactory;

    public function __construct(
        CartRepositoryInterface $quoteRepository,
        AddressInterfaceFactory $addressFactory,
        PaymentMethod $paymentMethod,
        PaymentFactory $paymentFactory,
        SubscriberFactory $subscriberFactory
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->addressFactory = $addressFactory;
        $this->paymentFactory = $paymentFactory;
        $this->methodHelper = $paymentMethod;
        $this->subscriberFactory = $subscriberFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
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
        $street2 = $response[$mode]['invoicingAddress']['address2'];
        $billingAddress->setStreet(
            $response[$mode]['invoicingAddress']['address1'] .
            (isset($street2) && $street2 ? "\n" . $street2 : '')
        );
        $billingAddress->setPostcode($response[$mode]['invoicingAddress']['zip']);
        $billingAddress->setCity($response[$mode]['invoicingAddress']['city']);
        $billingAddress->setCountryId($response[$mode]['invoicingAddress']['country']);
        $quote->setBillingAddress($billingAddress);

        if ($response[$mode]['deliveryAddress']['firstName']) {
            $shippingAddress = $this->addressFactory->create();
            $shippingAddress->setTelephone($response[$mode]['deliveryAddress']['phone'] ?: $telephone);
            $shippingAddress->setEmail($response[$mode]['deliveryAddress']['email'] ?: $email);
            $shippingAddress->setFirstname($response[$mode]['deliveryAddress']['firstName']);
            $shippingAddress->setLastname($response[$mode]['deliveryAddress']['lastName']);
            $street2 = $response[$mode]['deliveryAddress']['address2'];
            $shippingAddress->setStreet(
                $response[$mode]['deliveryAddress']['address1'] .
                (isset($street2) && $street2 ? "\n" . $street2 : '')
            );
            $shippingAddress->setPostcode($response[$mode]['deliveryAddress']['zip'] ?: $response[$mode]['invoicingAddress']['zip']);
            $shippingAddress->setCity($response[$mode]['deliveryAddress']['city'] ?: $response[$mode]['invoicingAddress']['city']);
            $shippingAddress->setCountryId($response[$mode]['deliveryAddress']['country'] ?: $response[$mode]['invoicingAddress']['country']);
            $quote->setShippingAddress($shippingAddress);
        } else {
            $quote->setShippingAddress($billingAddress);
        }

        // Set payment method
        if (isset($response['paymentMethods']['selectedPayment']['type'])) {
            $paymentMethod = $this->methodHelper->getPaymentMethod($response['paymentMethods']['selectedPayment']['type']);
            $quote->getPayment()->setMethod($paymentMethod);
        }

        if ($response[$mode]['step']['emailNewsletterSubscription'] == AvardaCheckBoxTypeValues::VALUE_CHECKED) {
            // @todo when magento 2.3. support ends change to use SubscriptionManager::subscribe
            $this->subscriberFactory->create()->subscribe($email);
        }

        // Set payment state
        $quote->getPayment()->setAdditionalInformation(
            PaymentData::STATE,
            $response[$mode]['step']['current']
        );
    }
}
