<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Controller\Checkout;

use Avarda\Checkout3\Controller\AbstractCheckout;

class Index extends AbstractCheckout
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var \Magento\Checkout\Helper\Data
     */
    protected $checkoutHelper;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * Request instance
     *
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * Index constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Avarda\Checkout3\Gateway\Config\Config $config
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Checkout\Helper\Data $checkoutHelper
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Avarda\Checkout3\Gateway\Config\Config $config,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Framework\App\RequestInterface $request
    ) {
        parent::__construct($context, $logger, $config);
        $this->resultPageFactory = $resultPageFactory;
        $this->checkoutHelper = $checkoutHelper;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->quoteRepository = $quoteRepository;
        $this->request = $request;
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        // Show no route if Avarda is inactive and notify webmaster in logs.
        if (!$this->config->isActive()) {
            return $this->noroute();
        }

        if (!$this->config->isOnepageRedirectActive() && !$this->request->getParam('fromCheckout')) {
            return $this->resultRedirectFactory
                ->create()->setPath('checkout/');
        }

        // Check if quote is valid, otherwise return to cart.
        $quote = $this->checkoutSession->getQuote();
        if (!$quote->hasItems() ||
            $quote->getHasError() ||
            !$quote->validateMinimumAmount()
        ) {
            return $this->resultRedirectFactory
                ->create()->setPath('checkout/cart');
        }

        if (!$this->customerSession->isLoggedIn() &&
            !$this->checkoutHelper->isAllowedGuestCheckout($quote)
        ) {
            $this->messageManager->addErrorMessage(
                __('Guest checkout is disabled.')
            );
            return $this->resultRedirectFactory
                ->create()->setPath('checkout/cart');
        }

        $needsSave = false;
        if ($this->customerSession->isLoggedIn()) {
            $quote->assignCustomer(
                $this->customerSession->getCustomerDataObject()
            );
            $needsSave = true;
        }

        $paymentCode = '';
        try {
            $paymentCode = $quote->getPayment()->getMethod();
        } catch (\Exception $e) {
            // pass
        }
        // Remove payment method if it's not avarda payment already
        if ($paymentCode != '' && strpos($paymentCode, 'avarda_checkout3') === false) {
            $quote->getPayment()->setMethod('avarda_checkout3_checkout')->save();
        }

        if ($needsSave) {
            // for unknown reason shipping method is not saved with repository save
            $quote->save();
        }

        $this->customerSession->regenerateId();
        $this->checkoutSession->setCartWasUpdated(false);

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Checkout'));
        return $resultPage;
    }
}
