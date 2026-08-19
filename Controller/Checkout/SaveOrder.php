<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Controller\Checkout;

use Avarda\Checkout3\Api\AvardaOrderRepositoryInterface;
use Avarda\Checkout3\Api\OrphanPurchaseResolverInterface;
use Avarda\Checkout3\Api\QuotePaymentManagementInterface;
use Avarda\Checkout3\Controller\AbstractCheckout;
use Avarda\Checkout3\Cron\CompletePendingPaymentOrdersCron;
use Avarda\Checkout3\Exception\PurchaseLockedException;
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
    protected OrphanPurchaseResolverInterface $orphanPurchaseResolver;

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
        OrderRepositoryInterface $orderRepository,
        OrphanPurchaseResolverInterface $orphanPurchaseResolver,
    ) {
        parent::__construct($context, $logger, $config);
        $this->quotePaymentManagement = $quotePaymentManagement;
        $this->avardaOrderRepository = $avardaOrderRepository;
        $this->cartRepository = $cartRepository;
        $this->paymentData = $paymentData;
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->orderRepository = $orderRepository;
        $this->orphanPurchaseResolver = $orphanPurchaseResolver;
    }

    /**
     * Order success action or if user canceled payment
     *
     * @return ResultInterface
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $order = null;
        try {
            if (($purchaseId = $this->getPurchaseId()) === null) {
                throw new Exception(
                    __('Missing purchase ID "%purchase_id"', ['purchase_id' => $purchaseId])
                );
            }

            $orderId = $this->avardaOrderRepository->getByPurchaseId($purchaseId);
            if ($orderId && $orderId->getOrderId()) {
                $order = $this->orderRepository->get($orderId->getOrderId());
            } else {
                $order = $this->orphanPurchaseResolver->resolveByPurchaseId($purchaseId);
            }
            if (!$order) {
                throw new Exception(
                    __('No order found for purchase ID "%purchase_id"', ['purchase_id' => $purchaseId])
                );
            }

            $this->quotePaymentManagement->updateOrderPaymentStatus($order);

            if (!$this->config->getConfigValue(CompletePendingPaymentOrdersCron::XML_PATH_ENABLED)) {
                $this->quotePaymentManagement->finalizeOrder($order);
            }

            // Set order and quote information to the session, so we can redirect to the success page
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
        } catch (PurchaseLockedException $e) {
            $this->logger->warning($e->getMessage());
            $message = __('Your payment is still being processed. Please check your order status in a moment.');
        } catch (Exception $e) {
            // log stacktrace to get why saving fails
            $this->logger->critical($e, $e->getTrace());
            $message = __('Failed to save Avarda order. Please try again later.');
        }

        if ($order) {
            $quote = $this->cartRepository->get($order->getQuoteId());
            if ($quote && $quote->getIsActive()) {
                $quote->setIsActive(false);
                $quote->save();
            }
        }

        $this->messageManager->addErrorMessage($message);

        return $this->resultRedirectFactory->create()->setPath(
            'checkout/cart'
        );
    }
}
