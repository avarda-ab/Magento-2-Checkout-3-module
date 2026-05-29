<?php

namespace Avarda\Checkout3\Plugin\Gateway;

use Avarda\Checkout3\Helper\AvardaCheckBoxTypeValues;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class CheckoutSetupDataBuilderPlugin
{
    protected ScopeConfigInterface $scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    public function afterBuild($subject, $result)
    {
        $isEnabled = $this->scopeConfig->isSetFlag('payment/avarda_checkout3_checkout/hide_avarda_checkout_shipping_fields', ScopeInterface::SCOPE_STORE);
        if ($isEnabled) {
            $result['checkoutSetup']['differentDeliveryAddress'] = AvardaCheckBoxTypeValues::VALUE_HIDDEN;
        }

        return $result;
    }
}
