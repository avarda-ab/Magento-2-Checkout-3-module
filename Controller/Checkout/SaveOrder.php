<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Controller\Checkout;

use Avarda\Checkout3\Api\AvardaOrderRepositoryInterface;
use Avarda\Checkout3\Api\QuotePaymentManagementInterface;
use Avarda\Checkout3\Controller\AbstractCheckout;
use Avarda\Checkout3\Gateway\Config\Config;
use Exception;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\PaymentException;
use Psr\Log\LoggerInterface;

class SaveOrder extends AbstractCheckout
{
    /** @var \Magento\Checkout\Model\Session */
    protected $checkoutSession;

    /** @var Session */
    protected $customerSession;

    /** @var QuotePaymentManagementInterface */
    protected $quotePaymentManagement;

    /** @var AvardaOrderRepositoryInterface */
    protected $avardaOrderRepository;

    public function __construct(
        Context $context,
        LoggerInterface $logger,
        Config $config,
        \Magento\Checkout\Model\Session $checkoutSession,
        Session $customerSession,
        QuotePaymentManagementInterface $quotePaymentManagement,
        AvardaOrderRepositoryInterface $avardaOrderRepository
    ) {
        parent::__construct($context, $logger, $config);
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->quotePaymentManagement = $quotePaymentManagement;
        $this->avardaOrderRepository = $avardaOrderRepository;
    }

    /**
     * Order success action or if user canceled payment
     *
     * @return ResultInterface|ResponseInterface
     */
    public function execute()
    {
        try {
            if (($purchaseId = $this->getPurchaseId()) === null) {
                throw new Exception(
                    __('Failed to save order with purchase ID "%purchase_id"', [
                        'purchase_id' => $purchaseId
                    ])
                );
            }

            try {
                $this->avardaOrderRepository->save($purchaseId);
            } catch (AlreadyExistsException $alreadyExistsException) {
                $this->messageManager->addWarningMessage(__('Order already saved'));
                $this->logger->warning("Order with purchase $purchaseId already saved");
                return $this->resultRedirectFactory->create()->setPath(
                    'checkout/onepage/success'
                );
            }

            $quoteId = $this->quotePaymentManagement->getQuoteIdByPurchaseId($purchaseId);
            $this->quotePaymentManagement->updatePaymentStatus($quoteId);

            $this->quotePaymentManagement->placeOrder($quoteId);

            return $this->resultRedirectFactory->create()->setPath(
                'checkout/onepage/success'
            );
        } catch (PaymentException $e) {
            $message = $e->getMessage();
            $this->logger->error($e);
        } catch (Exception $e) {
            // log stacktrace to get why saving fails
            $this->logger->error($e, $e->getTrace());
            $message = __('Failed to save Avarda order. Please try again later.');
        }

        $this->messageManager->addErrorMessage($message);
        return $this->resultRedirectFactory->create()->setPath(
            'checkout/cart'
        );
    }
}
