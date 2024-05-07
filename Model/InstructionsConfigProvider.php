<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Url;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Payment\Model\MethodInterface;
use Magento\Store\Model\ScopeInterface;

class InstructionsConfigProvider implements ConfigProviderInterface
{
    protected Escaper $escaper;
    protected ScopeConfigInterface $scopeConfig;
    protected Url $url;
    protected string $methodCode;
    protected ?MethodInterface $methodInstance = null;

    public function __construct(
        PaymentHelper $paymentHelper,
        Escaper $escaper,
        ScopeConfigInterface $scopeConfig,
        Url $url,
        $methodCode = ''
    ) {
        $this->escaper = $escaper;
        $this->scopeConfig = $scopeConfig;
        $this->url = $url;
        $this->methodCode = $methodCode;
        $this->methodInstance = $paymentHelper->getMethodInstance($this->methodCode);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = [];
        if ($this->methodInstance->isAvailable()) {
            $config['payment']['instructions'][$this->methodCode] = [
                'instructions' => $this->getInstructions($this->methodCode),
            ];
        }

        return $config;
    }

    /**
     * Get instructions text from config
     *
     * @param string $code
     * @return string
     */
    protected function getInstructions($code)
    {
        return nl2br($this->escaper->escapeHtml($this->scopeConfig->getValue('payment/' . $code . '/instructions', ScopeInterface::SCOPE_STORE)));
    }

    /**
     * Get instructions text from config
     *
     * @param string $code
     * @return string
     */
    protected function getConfigValue($code, $conf)
    {
        return nl2br($this->escaper->escapeHtml($this->scopeConfig->getValue('payment/' . $code . '/' . $conf, ScopeInterface::SCOPE_STORE)));
    }
}
