<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Controller;

use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\ResultFactory;

abstract class AbstractCheckout extends Action
{
    const CALLBACK_FAILURE = 'Failure';
    const CALLBACK_SUCCESS = 'Success';

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Avarda\Checkout3\Gateway\Config\Config
     */
    protected $config;

    /**
     * Index constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Avarda\Checkout3\Gateway\Config\Config $config
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Avarda\Checkout3\Gateway\Config\Config $config
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
     * @return \Magento\Framework\Controller\ResultInterface
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
