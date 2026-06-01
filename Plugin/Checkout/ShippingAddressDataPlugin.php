<?php

namespace Avarda\Checkout3\Plugin\Checkout;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Quote\Api\CartRepositoryInterface;

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

        $quote = $this->cartRepository->getActive($cartId);
        $quote->getShippingAddress()->addData($extData);
    }
}
