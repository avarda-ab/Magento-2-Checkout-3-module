<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Controller\Checkout;

use Avarda\Checkout3\Api\AvardaOrderRepositoryInterface;
use Avarda\Checkout3\Api\QuotePaymentManagementInterface;
use Avarda\Checkout3\Controller\AbstractCheckout;
use Avarda\Checkout3\Cron\CompletePendingPaymentOrdersCron;
use Avarda\Checkout3\Gateway\Config\Config;
use Avarda\Checkout3\Helper\PaymentData;
use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\PaymentException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;

class SaveOrder extends AbstractCheckout
{
    protected QuotePaymentManagementInterface $quotePaymentManagement;
    protected AvardaOrderRepositoryInterface $avardaOrderRepository;
    protected CartRepositoryInterface $cartRepository;
    protected PaymentData $paymentData;
    protected Session $checkoutSession;
    protected OrderFactory $orderFactory;
    protected OrderRepositoryInterface $orderRepository;

    public function __construct(
        Context $context,
        LoggerInterface $logger,
        Config $config,
        QuotePaymentManagementInterface $quotePaymentManagement,
        AvardaOrderRepositoryInterface $avardaOrderRepository,
        CartRepositoryInterface $cartRepository,
        PaymentData $paymentData,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct($context, $logger, $config);
        $this->quotePaymentManagement = $quotePaymentManagement;
        $this->avardaOrderRepository = $avardaOrderRepository;
        $this->cartRepository = $cartRepository;
        $this->paymentData = $paymentData;
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Order success action or if user canceled payment
     *
     * @return ResultInterface
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        try {
            if (($purchaseId = $this->getPurchaseId()) === null) {
                throw new Exception(
                    __('Missing purchase ID "%purchase_id"', ['purchase_id' => $purchaseId])
                );
            }

            $orderId = $this->avardaOrderRepository->getByPurchaseId($purchaseId);
            $order = $this->orderRepository->get($orderId->getOrderId());
            $this->quotePaymentManagement->updateOrderPaymentStatus($order);

            if (!$this->config->getConfigValue(CompletePendingPaymentOrdersCron::XML_PATH_ENABLED)) {
                $this->quotePaymentManagement->finalizeOrder($order);
            }

            // Set order and quote information to session, so we can redirect to success page
            $this->checkoutSession
                ->setLastOrderId($order->getId())
                ->setLastRealOrderId($order->getIncrementId())
                ->setLastOrderStatus($order->getStatus())
                ->setLastQuoteId($order->getQuoteId())
                ->setLastSuccessQuoteId($order->getQuoteId());

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

        $quote = $this->cartRepository->get($order->getQuoteId());
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
