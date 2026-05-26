<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Gateway\Request;

use Avarda\Checkout3\Helper\AvardaCheckBoxTypeValues;
use Avarda\Checkout3\Model\Data\AddressBuilder;
use Magento\InventoryInStorePickupShippingApi\Model\Carrier\InStorePickup;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Quote\Api\Data\CartInterface;

class AddressUpdateBuilder implements BuilderInterface
{
    protected B2cDataBuilder $b2cDataBuilder;
    protected AddressBuilder $addressBuilder;

    public function __construct(
        B2cDataBuilder $b2cDataBuilder,
        AddressBuilder $addressBuilder
    ) {
        $this->b2cDataBuilder = $b2cDataBuilder;
        $this->addressBuilder = $addressBuilder;
    }

    public function build(array $buildSubject)
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder();
        $quote = $paymentDO->getPayment()->getQuote();

        return [
            "differentDeliveryAddress" => $this->getDifferentDeliveryAddressValue($order, $quote),
            "deliveryAddress" => $this->b2cDataBuilder->getShippingAddress($order),
        ];
    }

    protected function getDifferentDeliveryAddressValue(OrderAdapterInterface $order, CartInterface $quote): string
    {
        if ($this->isInStorePickup($quote)) {
            return AvardaCheckBoxTypeValues::VALUE_HIDDEN;
        }
        if ($this->addressBuilder->isAddressDifferent($order->getBillingAddress(), $order->getShippingAddress())) {
            return AvardaCheckBoxTypeValues::VALUE_CHECKED;
        }
        return AvardaCheckBoxTypeValues::VALUE_UNCHECKED;
    }

    protected function isInStorePickup(CartInterface $quote): bool
    {
        $shippingAddress = $quote->getShippingAddress();
        return $shippingAddress && $shippingAddress->getShippingMethod() === InStorePickup::DELIVERY_METHOD;
    }
}
