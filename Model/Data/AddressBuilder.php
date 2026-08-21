<?php

namespace Avarda\Checkout3\Model\Data;


use Magento\Payment\Gateway\Data\AddressAdapterInterface;

class AddressBuilder
{
    /**
     * @param $address1 AddressAdapterInterface
     * @param $address2 AddressAdapterInterface
     * @return bool
     */
    public function isAddressDifferent(AddressAdapterInterface $address1, AddressAdapterInterface $address2): bool
    {
        if (
            $address1->getFirstname() != $address2->getFirstname() ||
            $address1->getLastname() != $address2->getLastname() ||
            $address1->getStreetLine1() != $address2->getStreetLine1() ||
            $address1->getStreetLine2() != $address2->getStreetLine2() ||
            $address1->getCity() != $address2->getCity() ||
            $this->getPostcode($address1) != $this->getPostcode($address2) ||
            (
                null !== $address1->getCountryId() &&
                $address1->getCountryId() != $address2->getCountryId()
            )
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * A wildcard postcode is a placeholder for country-only shipping and tax estimation,
     * not real address data.
     */
    public function getPostcode(AddressAdapterInterface $address): string
    {
        $postcode = (string)$address->getPostcode();

        return $postcode === '*' ? '' : $postcode;
    }
}
