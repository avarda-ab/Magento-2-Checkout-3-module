<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Controller\Checkout;

use Avarda\Checkout3\Api\AvardaOrderRepositoryInterface;
use Avarda\Checkout3\Api\QuotePaymentManagementInterface;
use Avarda\Checkout3\Controller\AbstractCheckout;
use Avarda\Checkout3\Gateway\Config\Config;
use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PurchaseState;
use Exception;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;

class Process extends AbstractCheckout
{
    protected PageFactory $resultPageFactory;
    protected QuotePaymentManagementInterface $quotePaymentManagement;
    protected PaymentData $paymentData;
    protected AvardaOrderRepositoryInterface $avardaOrderRepository;
    protected PurchaseState $purchaseStateHelper;

    public function __construct(
        Context $context,
        LoggerInterface $logger,
        Config $config,
        PageFactory $resultPageFactory,
        QuotePaymentManagementInterface $quotePaymentManagement,
        PaymentData $paymentData,
        AvardaOrderRepositoryInterface $avardaOrderRepository,
        PurchaseState $purchaseStateHelper,
    ) {
        parent::__construct($context, $logger, $config);
        $this->resultPageFactory = $resultPageFactory;
        $this->quotePaymentManagement = $quotePaymentManagement;
        $this->paymentData = $paymentData;
        $this->avardaOrderRepository = $avardaOrderRepository;
        $this->purchaseStateHelper = $purchaseStateHelper;
    }

    /**
     * @return ResultInterface|ResponseInterface
     */
    public function execute()
    {
        // Show no route if Avarda is inactive
        if (!$this->isCallback() && !$this->config->isActive()) {
            return $this->noroute('/checkout/avarda3/process');
        }

        try {
            if (($purchaseId = $this->getPurchaseId()) === null) {
                throw new Exception(
                    __('Failed to save order with purchase ID "%purchase_id"', [
                        'purchase_id' => $purchaseId,
                    ])
                );
            }

            $quoteId = $this->quotePaymentManagement->getQuoteIdByPurchaseId($purchaseId);
            $this->quotePaymentManagement->updateOnlyPaymentStatus($quoteId);
            $quote = $this->quotePaymentManagement->getQuote($quoteId);
            $state = $this->paymentData->getState($quote->getPayment());

            // If purchase is complete, redirect straight to save order
            if ($this->purchaseStateHelper->isComplete($state)) {
                return $this->resultRedirectFactory->create()
                    ->setPath('avarda3/checkout/saveOrder/purchase/' . $purchaseId);
            }

            return $this->resultPageFactory->create();
        } catch (Exception $e) {
            $this->logger->error($e);
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('avarda3/checkout');
    }
}
