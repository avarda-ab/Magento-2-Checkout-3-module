<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Controller\Certificate;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Store\Model\ScopeInterface;

class Index implements HttpGetActionInterface
{
    protected RawFactory $resultRawFactory;
    protected ScopeConfigInterface $config;

    public function __construct(
        RawFactory $resultRawFactory,
        ScopeConfigInterface $config,
    ) {
        $this->resultRawFactory = $resultRawFactory;
        $this->config = $config;
    }

    public function execute()
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $result = $this->resultRawFactory->create();
        $result->setHeader('Content-Type', 'text/plain');
        $result->setContents($this->getContent());
        return $result;
    }

    public function isEnabled(): bool
    {
        return $this->config->isSetFlag('payment/avarda_checkout3_section/certificate/active', ScopeInterface::SCOPE_STORE);
    }

    public function getContent(): string
    {
        return $this->config->getValue('payment/avarda_checkout3_section/certificate/content', ScopeInterface::SCOPE_STORE) ?? '';
    }
}
