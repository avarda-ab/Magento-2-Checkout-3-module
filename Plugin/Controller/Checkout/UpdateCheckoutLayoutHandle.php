<?php

namespace Avarda\Checkout3\Plugin\Controller\Checkout;

use Avarda\Checkout3\Gateway\Config\Config;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\LayoutInterface;

class UpdateCheckoutLayoutHandle
{
    protected RequestInterface $request;
    protected Config $config;
    protected LayoutInterface $layout;

    public function __construct(
        RequestInterface $request,
        Config $config,
        LayoutInterface $layout,
    ) {
        $this->request = $request;
        $this->config = $config;
        $this->layout = $layout;
    }

    public function beforeExecute($subject)
    {
        if ($this->request->getParam('fromCheckout') == 1 && $this->config->useAvardaAsPaymentStep()) {
            $this->layout->getUpdate()->addHandle('avarda3_checkout_index_fromcheckout');
        }
    }
}
