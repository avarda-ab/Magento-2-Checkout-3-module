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
use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\PaymentException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
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

    /** @var Session */
    protected $checkoutSession;

    /** @var OrderFactory */
    protected $orderFactory;

    public function __construct(
        Context $context,
        LoggerInterface $logger,
        Config $config,
        QuotePaymentManagementInterface $quotePaymentManagement,
        AvardaOrderRepositoryInterface $avardaOrderRepository,
        CartRepositoryInterface $cartRepository,
        PaymentData $paymentData,
        Session $checkoutSession,
        OrderFactory $orderFactory
    ) {
        parent::__construct($context, $logger, $config);
        $this->quotePaymentManagement = $quotePaymentManagement;
        $this->avardaOrderRepository = $avardaOrderRepository;
        $this->cartRepository = $cartRepository;
        $this->paymentData = $paymentData;
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
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

            $quoteId = $this->quotePaymentManagement->getQuoteIdByPurchaseId($purchaseId);
            $quote = $this->cartRepository->get($quoteId);

            try {
                $this->avardaOrderRepository->save($purchaseId);
            } catch (AlreadyExistsException $alreadyExistsException) {
                $this->logger->warning("Order with purchase $purchaseId already saved");

                $orderNro = $quote->getReservedOrderId();
                $order = $this->orderFactory->create()
                    ->loadByIncrementIdAndStoreId($orderNro, $quote->getStoreId());
                // Set order and quote information to session, so we can redirect to success page
                $this->checkoutSession
                    ->setLastOrderId($order->getId())
                    ->setLastRealOrderId($order->getIncrementId())
                    ->setLastOrderStatus($order->getStatus())
                    ->setLastQuoteId($quoteId)
                    ->setLastSuccessQuoteId($quoteId);

                return $this->resultRedirectFactory->create()->setPath(
                    'checkout/onepage/success'
                );
            }


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
