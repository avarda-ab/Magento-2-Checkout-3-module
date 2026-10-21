<?php

namespace Avarda\Checkout3\Plugin\Checkout;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\InventoryInStorePickupShippingApi\Model\Carrier\InStorePickup;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartInterface;

class ShippingAddressDataPlugin
{
    protected CartRepositoryInterface $cartRepository;

    public function __construct(CartRepositoryInterface $cartRepository)
    {
        $this->cartRepository = $cartRepository;
    }

    /**
     *  For some reason switching back from In Store Pickup breaks extension attribute saving to quote address
     */
    public function beforeSaveAddressInformation(
        ShippingInformationManagement $subject,
        $cartId,
        ShippingInformationInterface $addressInformation
    ) {
        $address = $addressInformation->getShippingAddress();
        $quote = $this->cartRepository->getActive($cartId);

        if ($this->isLeavingPickup($addressInformation, $address, $quote)) {
            $this->replacePickupAddress($addressInformation, $address, $quote);
        }

        $extAttributes = $address->getExtensionAttributes();

        if (!$extAttributes) {
            return;
        }

        $extData = array_filter($extAttributes->__toArray(), function ($value) {
            return $value !== null && $value !== '';
        });

        if (empty($extData)) {
            return;
        }

        $quote->getShippingAddress()->addData($extData);
    }

    protected function isLeavingPickup(
        ShippingInformationInterface $addressInformation,
        AddressInterface $address,
        CartInterface $quote,
    ): bool {
        $method = $addressInformation->getShippingCarrierCode() . '_' . $addressInformation->getShippingMethodCode();
        if ($method === InStorePickup::DELIVERY_METHOD) {
            return false;
        }

        $quoteAddress = $quote->getShippingAddress();
        $quoteExtension = $quoteAddress->getExtensionAttributes();
        $payloadExtension = $address->getExtensionAttributes();

        return $quoteAddress->getShippingMethod() === InStorePickup::DELIVERY_METHOD
            || ($quoteExtension && $quoteExtension->getPickupLocationCode())
            || ($payloadExtension && $payloadExtension->getPickupLocationCode());
    }

    /**
     * Magento keeps the pickup store as the shipping address after the method is changed, and the
     * payload sent by the checkout still holds it, so a pickup address is replaced with the billing address.
     */
    protected function replacePickupAddress(
        ShippingInformationInterface $addressInformation,
        AddressInterface $address,
        CartInterface $quote,
    ): void {
        if ($this->isPickupAddress($address, $quote)) {
            $this->copyBillingToAddress($addressInformation, $address, $quote);
        }

        $payloadExtension = $address->getExtensionAttributes();
        if ($payloadExtension) {
            $payloadExtension->setPickupLocationCode(null);
        }

        $quoteExtension = $quote->getShippingAddress()->getExtensionAttributes();
        if ($quoteExtension) {
            $quoteExtension->setPickupLocationCode(null);
        }
    }

    /**
     * A shipping address the customer chose or typed in the same request must be kept.
     */
    protected function isPickupAddress(AddressInterface $address, CartInterface $quote): bool
    {
        $payloadExtension = $address->getExtensionAttributes();
        if ($payloadExtension && $payloadExtension->getPickupLocationCode()) {
            return true;
        }

        $payloadStreet = trim((string)($address->getStreet()[0] ?? ''));
        if ($payloadStreet === '') {
            return true;
        }

        $quoteAddress = $quote->getShippingAddress();
        $quoteStreet = trim((string)($quoteAddress->getStreet()[0] ?? ''));

        return $payloadStreet === $quoteStreet
            && (string)$address->getPostcode() === (string)$quoteAddress->getPostcode();
    }

    /**
     * Without billing the postcode, city and country stay, because rate collection and the Matkahuolto
     * agent search need them; the builders treat an address without a street as empty.
     */
    protected function copyBillingToAddress(
        ShippingInformationInterface $addressInformation,
        AddressInterface $address,
        CartInterface $quote,
    ): void {
        $billing = null;
        foreach ([$addressInformation->getBillingAddress(), $quote->getBillingAddress()] as $candidate) {
            if ($candidate && trim((string)($candidate->getStreet()[0] ?? '')) !== '') {
                $billing = $candidate;
                break;
            }
        }
        $hasBilling = $billing !== null;

        $address->setFirstname($hasBilling ? $billing->getFirstname() : '');
        $address->setLastname($hasBilling ? $billing->getLastname() : '');
        $address->setCompany($hasBilling ? $billing->getCompany() : '');
        $address->setStreet($hasBilling ? $billing->getStreet() : ['']);
        $address->setTelephone($hasBilling ? $billing->getTelephone() : '');
        $address->setCustomerAddressId(null);

        if (!$hasBilling) {
            return;
        }

        $address->setCity($billing->getCity());
        $address->setPostcode($billing->getPostcode());
        $address->setRegion($billing->getRegion());
        $address->setRegionId($billing->getRegionId());
        $address->setRegionCode($billing->getRegionCode());
        if ($billing->getCountryId()) {
            $address->setCountryId($billing->getCountryId());
        }
    }
}
