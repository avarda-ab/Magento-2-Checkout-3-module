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
use Avarda\Checkout3\Helper\PaymentData;
use Exception;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\PaymentException;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;

class SaveOrder extends AbstractCheckout
{
    /** @var QuotePaymentManagementInterface */
    protected $quotePaymentManagement;

    /** @var AvardaOrderRepositoryInterface */
    protected $avardaOrderRepository;

    /** @var CartRepositoryInterface */
    protected $cartRepository;

    /** @var PaymentData */
    protected $paymentData;

    public function __construct(
        Context $context,
        LoggerInterface $logger,
        Config $config,
        QuotePaymentManagementInterface $quotePaymentManagement,
        AvardaOrderRepositoryInterface $avardaOrderRepository,
        CartRepositoryInterface $cartRepository,
        PaymentData $paymentData
    ) {
        parent::__construct($context, $logger, $config);
        $this->quotePaymentManagement = $quotePaymentManagement;
        $this->avardaOrderRepository = $avardaOrderRepository;
        $this->cartRepository = $cartRepository;
        $this->paymentData = $paymentData;
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
            $quote = $this->cartRepository->get($quoteId);
            // make sure payment method is avarda
            if (!$this->paymentData->isAvardaPayment($quote->getPayment())) {
                $quote->getPayment()->setMethod('avarda_checkout3_checkout')->save();
            }
            $this->quotePaymentManagement->updatePaymentStatus($quoteId);
            $this->quotePaymentManagement->placeOrder($quoteId);

            return $this->resultRedirectFactory->create()->setPath(
                'checkout/onepage/success'
            );
        } catch (PaymentException $e) {
            $message = $e->getMessage();
            $this->logger->critical($e);
        } catch (Exception $e) {
            // log stacktrace to get why saving fails
            $this->logger->critical($e, $e->getTrace());
            $message = __('Failed to save Avarda order. Please try again later.');
        }

        if ($quote && $quote->getIsActive()) {
            $quote->setIsActive(false);
            $quote->save();
        }

        $this->messageManager->addErrorMessage($message);
        return $this->resultRedirectFactory->create()->setPath(
            'checkout/cart'
        );
    }
}
