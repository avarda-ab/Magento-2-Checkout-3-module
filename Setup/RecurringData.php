<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Setup;

use Avarda\Checkout3\Helper\PaymentMethod;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class RecurringData implements InstallDataInterface
{
    protected ScopeConfigInterface $config;
    protected WriterInterface $configWriter;

    public function __construct(
        ScopeConfigInterface $config,
        WriterInterface $configWriter
    ) {
        $this->config = $config;
        $this->configWriter = $configWriter;
    }

    /**
     * {@inheritdoc}
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $value = $this->config->getValue('payment/avarda_checkout3_checkout/order_status');
        if ($value === null) {
            return;
        }
        foreach (PaymentMethod::$codes as $code) {
            $path = "payment/{$code}/order_status";
            if ($this->config->getValue($path) !== $value) {
                $this->configWriter->save($path, $value);
            }
        }
    }
}
