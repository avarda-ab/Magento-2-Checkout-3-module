<?php

namespace Avarda\Checkout3\Controller;

use Magento\Framework\App\Action\Forward as ForwardAction;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Store\Model\ScopeInterface;

class Router implements RouterInterface
{
    protected const string MODULE_NAME = 'avarda3';
    protected const string CONTROLLER_NAME = 'certificate';
    protected const string ACTION_NAME = 'index';
    protected const string PATH = '/.well-known/apple-developer-merchantid-domain-association.txt';

    protected ActionFactory $actionFactory;
    protected ScopeConfigInterface $scopeConfig;

    public function __construct(
        ActionFactory $actionFactory,
        ScopeConfigInterface $scopeConfig,
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->actionFactory = $actionFactory;
    }

    /**
     * @param RequestInterface $request
     * @return ActionInterface|null
     */
    public function match(RequestInterface $request): ?ActionInterface
    {
        /* @var Http $request */
        if (!$this->isEnabled()) {
            return null;
        }

        if ($this->isRouted($request)) {
            return null;
        }

        if ($request->getRequestUri() != self::PATH) {
            return null;
        }

        $request->setModuleName(self::MODULE_NAME)
            ->setControllerName(self::CONTROLLER_NAME)
            ->setActionName(self::ACTION_NAME);

        return $this->actionFactory->create(ForwardAction::class, ['request' => $request]);
    }

    private function isRouted(RequestInterface $request): bool
    {
        return $request->getModuleName() == self::MODULE_NAME
            && $request->getControllerName() == self::CONTROLLER_NAME
            && $request->getActionName() == self::ACTION_NAME;
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag('payment/avarda_checkout3_section/certificate/active', ScopeInterface::SCOPE_STORE);
    }
}
