<?php

namespace Avarda\Checkout3\Cron;

use Avarda\Checkout3\Model\CompletePendingPaymentOrders;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\PaymentException;

class CompletePendingPaymentOrdersCron
{
    const XML_PATH_ENABLED = 'payment/avarda_checkout3_checkout/cron_enabled';

    protected ScopeConfigInterface $config;
    protected CompletePendingPaymentOrders $completePendingPaymentOrders;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CompletePendingPaymentOrders $completePendingPaymentOrders,
    ) {
        $this->config = $scopeConfig;
        $this->completePendingPaymentOrders = $completePendingPaymentOrders;
    }

    /**
     * @throws PaymentException
     */
    public function execute()
    {
        if ($this->config->isSetFlag(self::XML_PATH_ENABLED) === false) {
            return;
        }

        $this->completePendingPaymentOrders->execute();
    }
}
