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
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class UpdateOrderStatusHandler implements HandlerInterface
{
    protected OrderRepositoryInterface $orderRepository;
    protected PaymentMethod $methodHelper;
    protected SubscriberFactory $subscriberFactory;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        PaymentMethod $paymentMethod,
        SubscriberFactory $subscriberFactory
    ) {
        $this->orderRepository = $orderRepository;
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
        /** @var Order|OrderInterface $order */
        $order = $this->orderRepository->get($entityId);
        $mode = $response['mode'] == 'B2B' ? 'b2B' : 'b2C';

        // Initially phone number is set as dummy so update it to correct one
        $telephone = $response[$mode]['userInputs']['phone'];
        $email = $response[$mode]['userInputs']['email'];
        $billingAddress = $order->getBillingAddress();
        $billingAddress->setTelephone($telephone);
        $billingAddress->setEmail($email);
        if ($mode == 'b2C') {
            $billingAddress->setFirstname($response[$mode]['invoicingAddress']['firstName']);
            $billingAddress->setLastname($response[$mode]['invoicingAddress']['lastName']);
            $order->setCustomerFirstname($response[$mode]['invoicingAddress']['firstName']);
            $order->setCustomerLastname($response[$mode]['invoicingAddress']['lastName']);
        } else {
            // B2B customer set Company name to name fields
            $billingAddress->setFirstname($response[$mode]['customerInfo']['firstName']);
            $billingAddress->setLastname($response[$mode]['customerInfo']['lastName']);
            $billingAddress->setCompany($response[$mode]['invoicingAddress']['name']);
            $order->setCustomerFirstname($response[$mode]['customerInfo']['firstName']);
            $order->setCustomerLastname($response[$mode]['customerInfo']['lastName']);
        }
        $street2 = $response[$mode]['invoicingAddress']['address2'];
        $billingAddress->setStreet(
            $response[$mode]['invoicingAddress']['address1'] .
            (isset($street2) && $street2 ? "\n" . $street2 : '')
        );
        $billingAddress->setPostcode($response[$mode]['invoicingAddress']['zip']);
        $billingAddress->setCity($response[$mode]['invoicingAddress']['city']);
        $billingAddress->setCountryId($response[$mode]['invoicingAddress']['country']);

        if ($order->getIsNotVirtual()) {
            $shippingAddress = $order->getShippingAddress();
            if ($response[$mode]['deliveryAddress']['firstName']) {
                $shippingAddress->setFirstname($response[$mode]['deliveryAddress']['firstName']);
                $shippingAddress->setLastname($response[$mode]['deliveryAddress']['lastName']);
                $street2 = $response[$mode]['deliveryAddress']['address2'];
                $shippingAddress->setStreet(
                    $response[$mode]['deliveryAddress']['address1'] .
                    (isset($street2) && $street2 ? "\n" . $street2 : '')
                );
                $shippingAddress->setEmail($response[$mode]['deliveryAddress']['email'] ?? $email);
                $shippingAddress->setTelephone($response[$mode]['deliveryAddress']['phone'] ?? $telephone);
                $shippingAddress->setPostcode($response[$mode]['deliveryAddress']['zip'] ?? $response[$mode]['invoicingAddress']['zip']);
                $shippingAddress->setCity($response[$mode]['deliveryAddress']['city'] ?? $response[$mode]['invoicingAddress']['city']);
                $shippingAddress->setCountryId($response[$mode]['deliveryAddress']['country'] ?? $response[$mode]['invoicingAddress']['country']);
            } else {
                $shippingAddress->setEmail($billingAddress->getEmail());
                $shippingAddress->setTelephone($billingAddress->getTelephone());
                $shippingAddress->setFirstname($billingAddress->getFirstname());
                $shippingAddress->setLastname($billingAddress->getLastname());
                $shippingAddress->setCity($billingAddress->getCity());
                $shippingAddress->setPostcode($billingAddress->getPostcode());
                $shippingAddress->setStreet($billingAddress->getStreet());
                $shippingAddress->setCountryId($billingAddress->getCountryId());
            }
        }

        // Set payment method
        if (isset($response['paymentMethods']['selectedPayment']['type'])) {
            $paymentMethod = $this->methodHelper->getPaymentMethod($response['paymentMethods']['selectedPayment']['type']);
            $order->getPayment()->setMethod($paymentMethod);
        }

        if ($response[$mode]['step']['emailNewsletterSubscription'] == AvardaCheckBoxTypeValues::VALUE_CHECKED) {
            // @todo when magento 2.3. support ends change to use SubscriptionManager::subscribe
            $this->subscriberFactory->create()->subscribe($email);
        }

        // Set payment state
        $order->getPayment()->setAdditionalInformation(
            PaymentData::STATE,
            $response[$mode]['step']['current']
        );

        // Make sure state and new addresses info is saved db
        $this->orderRepository->save($order);
    }
}
