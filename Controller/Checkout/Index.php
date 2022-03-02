<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Controller\Checkout;

use Avarda\Checkout3\Controller\AbstractCheckout;
use Avarda\Checkout3\Gateway\Config\Config;
use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PurchaseState;
use Avarda\Checkout3\Model\QuotePaymentManagement;
use Exception;
use Magento\Checkout\Helper\Data;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;

class Index extends AbstractCheckout
{
    /** @var PageFactory */
    protected $resultPageFactory;

    /** @var Data */
    protected $checkoutHelper;

    /** @var CustomerSession */
    protected $customerSession;

    /** @var CheckoutSession */
    protected $checkoutSession;

    /** @var CartRepositoryInterface */
    protected $quoteRepository;

    /** @var RequestInterface */
    protected $request;

    /** @var QuotePaymentManagement */
    protected $quotePaymentManagement;

    /** @var PurchaseState */
    protected $purchaseState;

    /** @var PaymentData */
    protected $paymentData;

    public function __construct(
        Context $context,
        LoggerInterface $logger,
        Config $config,
        PageFactory $resultPageFactory,
        Data $checkoutHelper,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        CartRepositoryInterface $quoteRepository,
        RequestInterface $request,
        QuotePaymentManagement $quotePaymentManagement,
        PurchaseState $purchaseState,
        PaymentData $paymentData
    ) {
        parent::__construct($context, $logger, $config);
        $this->resultPageFactory = $resultPageFactory;
        $this->checkoutHelper = $checkoutHelper;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->quoteRepository = $quoteRepository;
        $this->request = $request;
        $this->quotePaymentManagement = $quotePaymentManagement;
        $this->purchaseState = $purchaseState;
        $this->paymentData = $paymentData;
    }

    /**
     * @return ResultInterface
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
        } catch (Exception $e) {
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

        $state = $this->paymentData->getState($quote->getPayment());
        if ($this->purchaseState->isComplete($state) || $this->purchaseState->isDead($state)) {
            $quote->collectTotals();
            $this->quotePaymentManagement->initializePurchase($quote);
        }

        $this->customerSession->regenerateId();
        $this->checkoutSession->setCartWasUpdated(false);

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Checkout'));
        return $resultPage;
    }
}
