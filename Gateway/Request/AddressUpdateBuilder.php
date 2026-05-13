<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Gateway\Request;

use Avarda\Checkout3\Helper\AvardaCheckBoxTypeValues;
use Avarda\Checkout3\Model\Data\AddressBuilder;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\InventoryInStorePickupShippingApi\Model\Carrier\InStorePickup;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class AddressUpdateBuilder implements BuilderInterface
{
    protected B2cDataBuilder $b2cDataBuilder;
    protected AddressBuilder $addressBuilder;
    protected CheckoutSession $checkoutSession;

    public function __construct(
        B2cDataBuilder $b2cDataBuilder,
        AddressBuilder $addressBuilder,
        CheckoutSession $checkoutSession
    ) {
        $this->b2cDataBuilder = $b2cDataBuilder;
        $this->addressBuilder = $addressBuilder;
        $this->checkoutSession = $checkoutSession;
    }

    public function build(array $buildSubject)
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder();

        return [
            "differentDeliveryAddress" => $this->getDifferentDeliveryAddressValue($order),
            "deliveryAddress" => $this->b2cDataBuilder->getShippingAddress($order),
        ];
    }

    protected function getDifferentDeliveryAddressValue(OrderAdapterInterface $order): string
    {
        if ($this->isInStorePickup()) {
            return AvardaCheckBoxTypeValues::VALUE_HIDDEN;
        }
        if ($this->addressBuilder->isAddressDifferent($order->getBillingAddress(), $order->getShippingAddress())) {
            return AvardaCheckBoxTypeValues::VALUE_CHECKED;
        }
        return AvardaCheckBoxTypeValues::VALUE_UNCHECKED;
    }

    protected function isInStorePickup(): bool
    {
        $shippingAddress = $this->checkoutSession->getQuote()->getShippingAddress();
        return $shippingAddress && $shippingAddress->getShippingMethod() === InStorePickup::DELIVERY_METHOD;
    }
}
