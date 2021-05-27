<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Model;

use Avarda\Checkout3\Api\QuotePaymentManagementInterface;
use Magento\Framework\Exception\PaymentException;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * QuotePaymentManagement
 * @see \Avarda\Checkout3\Api\QuotePaymentManagementInterface
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class QuotePaymentManagement implements QuotePaymentManagementInterface
{
    const ERROR_QUOTE_MISSING_PURCHASE = 'Cart ID %cart_id does not have an active Avarda payment.';

    /**
     * Required for GET /avarda3-items.
     *
     * @var \Avarda\Checkout3\Api\ItemManagementInterface $itemManagement
     */
    protected $itemManagement;

    /**
     * Required for populating requests with item data.
     *
     * @var \Avarda\Checkout3\Api\ItemStorageInterface $itemStorage
     */
    protected $itemStorage;

    /**
     * Helper for reading payment info instances, e.g. getting purchase ID
     * from quote payment.
     *
     * @var \Avarda\Checkout3\Helper\PaymentData
     */
    protected $paymentDataHelper;

    /**
     * Helper to determine Avarda's purchase state.
     *
     * @var \Avarda\Checkout3\Helper\PurchaseState
     */
    protected $purchaseStateHelper;

    /**
     * Command pool for API requests to Avarda.
     *
     * @var \Magento\Payment\Gateway\Command\CommandPoolInterface
     */
    protected $commandPool;

    /**
     * Required for executing API requests from command pool.
     *
     * @var \Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface
     */
    protected $paymentDataObjectFactory;

    /**
     * Repository to load quote from database.
     *
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * Repository for Avarda's payment queue which links Avarda's purchase ID to
     * Magento's quote ID.
     *
     * @var \Avarda\Checkout3\Api\PaymentQueueRepositoryInterface
     */
    protected $paymentQueueRepository;

    /**
     * Required to operate with payment queue repository.
     *
     * @var \Avarda\Checkout3\Api\Data\PaymentQueueInterfaceFactory
     */
    protected $paymentQueueFactory;

    /**
     * Required for placing order in Magento.
     *
     * @var \Magento\Quote\Api\CartManagementInterface
     */
    protected $cartManagement;

    /**
     * Temporary quote object to limit calls to repository.
     *
     * @var CartInterface
     */
    protected $quote;

    /** @var \Magento\Sales\Model\Order\Email\Sender\OrderSender */
    protected $orderSender;

    /** @var OrderRepositoryInterface */
    protected $orderRepository;

    public function __construct(
        \Avarda\Checkout3\Api\ItemManagementInterface $itemManagement,
        \Avarda\Checkout3\Api\ItemStorageInterface $itemStorage,
        \Avarda\Checkout3\Helper\PaymentData $paymentDataHelper,
        \Avarda\Checkout3\Helper\PurchaseState $purchaseStateHelper,
        \Magento\Payment\Gateway\Command\CommandPoolInterface $commandPool,
        \Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface $paymentDataObjectFactory,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Avarda\Checkout3\Api\PaymentQueueRepositoryInterface $paymentQueueRepository,
        \Avarda\Checkout3\Api\Data\PaymentQueueInterfaceFactory $paymentQueueFactory,
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->itemManagement = $itemManagement;
        $this->itemStorage = $itemStorage;
        $this->paymentDataHelper = $paymentDataHelper;
        $this->purchaseStateHelper = $purchaseStateHelper;
        $this->commandPool = $commandPool;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->quoteRepository = $quoteRepository;
        $this->paymentQueueRepository = $paymentQueueRepository;
        $this->paymentQueueFactory = $paymentQueueFactory;
        $this->cartManagement = $cartManagement;
        $this->orderSender = $orderSender;
        $this->orderRepository = $orderRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getPurchaseData($cartId)
    {
        $quote = $this->getQuote($cartId);
        $purchaseData = $this->paymentDataHelper->getPurchaseData(
            $quote->getPayment()
        );

        if (!$purchaseData) {
            /** We have to manually collect totals to populate the item storage */
            $quote->collectTotals();
            $purchaseData = $this->initializePurchase($quote);
        }

        return $purchaseData;
    }

    /**
     * {@inheritdoc}
     *
     * @param CartInterface|\Magento\Quote\Model\Quote $quote
     */
    public function initializePurchase(CartInterface $quote)
    {
        $quote->reserveOrderId();

        $this->executeCommand('avarda_initialize_payment', $quote);

        /**
         * Save the additional data to quote payment and retrieve purchase ID
         * @see \Avarda\Checkout3\Gateway\Response\InitializePaymentHandler
         */
        $quote->save();
        $purchaseData = $this->paymentDataHelper->getPurchaseData($quote->getPayment());

        /** Save purchase ID link to quote ID in payment queue */
        $paymentQueue = $this->paymentQueueFactory->create();
        $paymentQueue->setPurchaseId($purchaseData['purchaseId']);
        $paymentQueue->setJwt($purchaseData['jwt']);
        $paymentQueue->setExpires(strtotime($purchaseData['expiredUtc']));
        $paymentQueue->setQuoteId($quote->getId());
        try {
            $this->paymentQueueRepository->save($paymentQueue);
        } catch (\Magento\Framework\Exception\AlreadyExistsException $e) {
            // Simple fix to not fail on already exists error
        }

        return $purchaseData;
    }

    /**
     * {@inheritdoc}
     */
    public function getItemDetailsList($cartId)
    {
        $quote = $this->getQuote($cartId);
        $this->itemStorage->setItems($quote->getItems());
        return $this->itemManagement->getItemDetailsList();
    }

    /**
     * {@inheritdoc}
     */
    public function updateItems(CartInterface $quote)
    {
        $this->executeCommand('avarda_update_items', $quote);
    }

    /**
     * {@inheritdoc}
     */
    public function setQuoteIsActive($cartId, $isActive)
    {
        $quote = $this->getQuote($cartId);
        try {
            $this->isAvardaPayment($quote);
        } catch (PaymentException $e) {
            // isAvardaPayment check fails if payment method is something else than avarda
            $quote->getPayment()->setMethod('')->save();
            $this->isAvardaPayment($quote);
        }
        if ($quote->getIsActive() !== $isActive) {
            $quote->setIsActive($isActive);
            $quote->save();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updatePaymentStatus($cartId)
    {
        $this->isAvardaPayment($quote = $this->getQuote($cartId));
        $this->executeCommand('avarda_get_payment_status', $quote);
    }

    /**
     * {@inheritdoc}
     */
    public function placeOrder($cartId)
    {
        $quote = $this->getQuote($cartId);
        $this->isAvardaPayment($quote);

        /** Unfreeze cart before placing the order */
        $this->setQuoteIsActive($cartId, true);

        /** Must set checkout method for guests */
        if (!$quote->getCustomerId()) {
            $quote->setCheckoutMethod(CartManagementInterface::METHOD_GUEST);
        }

        $orderId = $this->cartManagement->placeOrder($cartId);

        // Clean payment queue
        $purchaseData = $this->paymentDataHelper->getPurchaseData(
            $quote->getPayment()
        );
        $paymentQueue = $this->paymentQueueRepository->get($purchaseData['purchaseId']);
        $this->paymentQueueRepository->delete($paymentQueue);

        $order = $this->orderRepository->get($orderId);
        $this->orderSender->send($order);
    }

    /**
     * {@inheritdoc}
     */
    public function getQuoteIdByPurchaseId($purchaseId)
    {
        $paymentQueue = $this->paymentQueueRepository->get($purchaseId);
        if ($paymentQueue->getQuoteId() === null) {
            throw new PaymentException(__('No cart linked with purchase ID "%purchase_id"', [
                'purchase_id' => $purchaseId
            ]));
        }

        $payment = $this->getQuote($paymentQueue->getQuoteId())->getPayment();
        if ($this->paymentDataHelper->getPurchaseData($payment)['purchaseId'] !== $purchaseId) {
            throw new PaymentException(__('Purchase ID "%purchase_id" is outdated', [
                'purchase_id' => $purchaseId
            ]));
        }

        return $paymentQueue->getQuoteId();
    }

    /**
     * Execute command for request to Avarda API based on quote.
     *
     * @param string $commandCode
     * @param CartInterface|\Magento\Quote\Model\Quote $quote
     * @return void
     */
    protected function executeCommand($commandCode, CartInterface $quote)
    {
        $arguments['amount'] = $quote->getGrandTotal();

        /** @var InfoInterface|null $payment */
        $payment = $quote->getPayment();
        if ($payment !== null && $payment instanceof InfoInterface) {
            $arguments['payment'] = $this->paymentDataObjectFactory
                ->create($payment);
        }

        $this->commandPool->get($commandCode)
            ->execute($arguments);
    }

    /**
     * Get quote by cart/quote ID
     *
     * @param int $cartId
     * @return CartInterface|\Magento\Quote\Model\Quote
     */
    protected function getQuote($cartId)
    {
        if (!isset($this->quote) || $this->quote->getId() !== $cartId) {
            /** @var CartInterface|\Magento\Quote\Model\Quote $quote */
            $this->quote = $this->quoteRepository->get($cartId);
        }

        return $this->quote;
    }

    /**
     * Check if quote has a valid Avarda payment.
     *
     * @param CartInterface|\Magento\Quote\Model\Quote $quote
     * @throws PaymentException
     * @return void
     */
    protected function isAvardaPayment(CartInterface $quote)
    {
        if (!$this->paymentDataHelper->isAvardaPayment($quote->getPayment())) {
            throw new PaymentException(__(self::ERROR_QUOTE_MISSING_PURCHASE, [
                'cart_id' => $quote->getId()
            ]));
        }
    }
}
