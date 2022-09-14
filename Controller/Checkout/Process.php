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
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class Process extends AbstractCheckout
{
    /** @var PageFactory */
    protected $resultPageFactory;

    /** @var QuotePaymentManagementInterface */
    protected $quotePaymentManagement;

    /** @var PaymentData */
    protected $paymentData;

    /** @var OrderRepositoryInterface */
    protected $orderRepository;

    /** @var AvardaOrderRepositoryInterface */
    protected $avardaOrderRepository;

    public function __construct(
        Context $context,
        LoggerInterface $logger,
        Config $config,
        PageFactory $resultPageFactory,
        QuotePaymentManagementInterface $quotePaymentManagement,
        PaymentData $paymentData,
        OrderRepositoryInterface $orderRepository,
        AvardaOrderRepositoryInterface $avardaOrderRepository
    ) {
        parent::__construct($context, $logger, $config);
        $this->resultPageFactory = $resultPageFactory;
        $this->quotePaymentManagement = $quotePaymentManagement;
        $this->paymentData = $paymentData;
        $this->orderRepository = $orderRepository;
        $this->avardaOrderRepository = $avardaOrderRepository;
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
                        'purchase_id' => $purchaseId
                    ])
                );
            }

            $quoteId = $this->quotePaymentManagement->getQuoteIdByPurchaseId($purchaseId);
            $this->quotePaymentManagement->updatePaymentStatus($quoteId);
            return $this->resultPageFactory->create();
        } catch (Exception $e) {
            $this->logger->error($e);
            $message = $e->getMessage();
        }

        $this->messageManager->addErrorMessage($message);
        return $this->resultRedirectFactory
            ->create()->setPath('avarda3/checkout');
    }
}
