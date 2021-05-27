<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Controller\Checkout;

use Avarda\Checkout3\Api\QuotePaymentManagementInterface;
use Avarda\Checkout3\Controller\AbstractCheckout;
use Avarda\Checkout3\Gateway\Config\Config;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;

class Process extends AbstractCheckout
{
    /** @var PageFactory */
    protected $resultPageFactory;

    /** @var QuotePaymentManagementInterface */
    protected $quotePaymentManagement;

    public function __construct(
        Context $context,
        LoggerInterface $logger,
        Config $config,
        PageFactory $resultPageFactory,
        QuotePaymentManagementInterface $quotePaymentManagement
    ) {
        parent::__construct($context, $logger, $config);
        $this->resultPageFactory = $resultPageFactory;
        $this->quotePaymentManagement = $quotePaymentManagement;
    }

    /**
     * @return ResultInterface|ResponseInterface
     */
    public function execute()
    {
        // Show no route if Avarda is inactive and notify webmaster in logs.
        if (!$this->isCallback() && !$this->config->isActive()) {
            return $this->noroute('/checkout/avarda3/process');
        }

        try {
            if (($purchaseId = $this->getPurchaseId()) === null) {
                throw new \Exception(
                    __('Failed to save order with purchase ID "%purchase_id"', [
                        'purchase_id' => $purchaseId
                    ])
                );
            }

            $quoteId = $this->quotePaymentManagement
                ->getQuoteIdByPurchaseId($purchaseId);

            $this->quotePaymentManagement->updatePaymentStatus($quoteId);
            $this->quotePaymentManagement->setQuoteIsActive($quoteId, true);
            return $this->resultPageFactory->create();

        } catch (PaymentException $e) {
            $message = $e->getMessage();
        } catch (\Exception $e) {
            $this->logger->error($e);
            $message = __('Failed to save Avarda order. Please try again later.');
        }

        $this->messageManager->addErrorMessage($message);
        return $this->resultRedirectFactory
            ->create()->setPath('avarda3/checkout');
    }
}
