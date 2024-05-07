<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Controller;

use Avarda\Checkout3\Gateway\Config\Config;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractCheckout extends Action
{
    const CALLBACK_FAILURE = 'Failure';
    const CALLBACK_SUCCESS = 'Success';

    protected LoggerInterface $logger;
    protected Config $config;

    public function __construct(
        Context $context,
        LoggerInterface $logger,
        Config $config
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Check if the URL is a callback.
     *
     * @return bool
     */
    public function isCallback()
    {
        return (
            (bool) $this->_request->getParam('callback', false) === true ||
            $this->_request->getParam('PaymentStatus', self::CALLBACK_FAILURE) === self::CALLBACK_SUCCESS
        );
    }

    /**
     * Show no route with warning for webmaster.
     *
     * @param string $route
     * @return ResultInterface
     */
    public function noroute($route = '/avarda3/checkout')
    {
        $this->logger->warning(
            sprintf(
                'No route display at %s, because Avarda checkout payment is disabled.',
                $route
            )
        );

        return $this->resultFactory->create(ResultFactory::TYPE_FORWARD)
            ->forward('noroute');
    }

    /**
     * Get purchase ID from request if available
     *
     * @return string|null
     */
    public function getPurchaseId()
    {
        $purchaseId = $this->_request->getParam('purchase', '');
        if (!empty($purchaseId) && ctype_alnum($purchaseId)) {
            return $purchaseId;
        }

        return null;
    }
}
