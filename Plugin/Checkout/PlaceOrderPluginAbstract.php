<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Plugin\Checkout;

use Avarda\Checkout3\Api\AvardaOrderRepositoryInterface;
use Avarda\Checkout3\Api\Data\PaymentDetailsInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;

abstract class PlaceOrderPluginAbstract
{
    /** @var AvardaOrderRepositoryInterface */
    protected $avardaOrderRepository;

    /** @var AddressFactory */
    protected $addressFactory;

    public function __construct(
        AvardaOrderRepositoryInterface $avardaOrderRepository,
        AddressFactory $addressFactory
    ) {
        $this->avardaOrderRepository = $avardaOrderRepository;
        $this->addressFactory = $addressFactory;
    }

    /**
     * @param $quote CartInterface
     * @param $additionalData array
     * @return void
     */
    public function setShippingAddress($quote, $additionalData)
    {
        $shippingAddress = $quote->getShippingAddress();

        // If b2b address there should be company name,
        // but if no delivery address given it might not be there
        if ($additionalData['mode'] == 'B2B' && isset($additionalData['deliveryAddress']['name'])) {
            $shippingAddress->setFirstname($additionalData['deliveryAddress']['name']);
            $shippingAddress->setLastname($additionalData['deliveryAddress']['name']);
            $shippingAddress->setCompany($additionalData['deliveryAddress']['name']);
        } else {
            $shippingAddress->setFirstname($additionalData['deliveryAddress']['firstName']);
            $shippingAddress->setLastname($additionalData['deliveryAddress']['lastName']);
        }

        $shippingAddress->setStreet([$additionalData['deliveryAddress']['address1'], $additionalData['deliveryAddress']['address2']]);
        $shippingAddress->setCity($additionalData['deliveryAddress']['city']);
        $shippingAddress->setPostcode($additionalData['deliveryAddress']['zip']);
        $shippingAddress->setCountryId($additionalData['deliveryAddress']['country']);

        // @todo fix this when phone number is available
        // We don't have customer phone number, so we add dummy here
        // After order is paid it will be updated by status update
        $shippingAddress->setTelephone('010123123');
        $quote->setShippingAddress($shippingAddress);
    }

    /**
     * @param $billingAddress AddressInterface
     * @param $additionalData array
     * @return AddressInterface
     */
    public function setBillingAddress($billingAddress, $additionalData)
    {
        if (!$billingAddress) {
            $billingAddress = $this->addressFactory->create();
        }

        if ($additionalData['mode'] == 'B2B') {
            $billingAddress->setFirstname($additionalData['invoicingAddress']['name']);
            $billingAddress->setLastname($additionalData['invoicingAddress']['name']);
            $billingAddress->setCompany($additionalData['invoicingAddress']['name']);
        } else {
            $billingAddress->setFirstname($additionalData['invoicingAddress']['firstName']);
            $billingAddress->setLastname($additionalData['invoicingAddress']['lastName']);
        }

        $billingAddress->setStreet([$additionalData['invoicingAddress']['address1'], $additionalData['invoicingAddress']['address2']]);
        $billingAddress->setCity($additionalData['invoicingAddress']['city']);
        $billingAddress->setPostcode($additionalData['invoicingAddress']['zip']);
        $billingAddress->setCountryId($additionalData['invoicingAddress']['country']);

        // @todo fix this when phone number is available
        // We don't have customer phone number, so we add dummy here
        // After order is paid it will be updated by status update
        $billingAddress->setTelephone('010123123');
        return $billingAddress;
    }

    /**
     * @param $orderId int|string
     * @param $order Quote|CartInterface|Order|OrderInterface
     * @return void
     * @throws AlreadyExistsException
     */
    public function saveOrderCreated($orderId, $order)
    {
        $purchaseId = $order->getPayment()->getAdditionalInformation(PaymentDetailsInterface::PURCHASE_DATA)['purchaseId'];
        $this->avardaOrderRepository->save($purchaseId, $orderId);
    }

    /**
     * @param string $email
     * @param array $additionalData
     * @return string
     */
    public function checkEmail($email, $additionalData)
    {
        if (!$email) {
            $email = $additionalData['email'];
        }
        return $email;
    }

    /**
     * Validate that frontend purchaseId matches purchaseId saved in quote
     *
     * @param $quote Quote|CartInterface
     * @param $data array
     * @return true
     * @throws LocalizedException
     */
    public function validatePurchase($quote, $data): bool
    {
        $purchaseId = $quote->getPayment()->getAdditionalInformation(PaymentDetailsInterface::PURCHASE_DATA)['purchaseId'];
        if ($purchaseId != $data['purchaseId']) {
            throw new LocalizedException(__('Validation error, please try again'));
        }
        return true;
    }
}
