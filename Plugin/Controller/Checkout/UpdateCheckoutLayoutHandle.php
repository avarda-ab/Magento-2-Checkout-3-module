<?php

namespace Avarda\Checkout3\Plugin\Controller\Checkout;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\ScopeInterface;

class UpdateCheckoutLayoutHandle
{
    protected RequestInterface $request;
    protected ScopeConfigInterface $scopeConfig;
    protected LayoutInterface $layout;

    public function __construct(
        RequestInterface $request,
        ScopeConfigInterface $scopeConfig,
        LayoutInterface $layout,
    ) {
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
        $this->layout = $layout;
    }

    public function beforeExecute($subject)
    {
        $fromCheckout = $this->request->getParam('fromCheckout');
        $isEnabled = $this->scopeConfig->isSetFlag(
            'payment/avarda_checkout3_checkout/hide_avarda_checkout_shipping_fields',
            ScopeInterface::SCOPE_STORE
        );
        if ($fromCheckout == 1 && $isEnabled) {
            $this->layout->getUpdate()->addHandle('avarda3_checkout_index_fromcheckout');
        }
    }
}
