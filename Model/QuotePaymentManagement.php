<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Model;

use Avarda\Checkout3\Api\Data\PaymentDetailsInterface;
use Avarda\Checkout3\Api\Data\PaymentQueueInterfaceFactory;
use Avarda\Checkout3\Api\ItemManagementInterface;
use Avarda\Checkout3\Api\ItemStorageInterface;
use Avarda\Checkout3\Api\PaymentQueueRepositoryInterface;
use Avarda\Checkout3\Api\QuotePaymentManagementInterface;
use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PurchaseState;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\PaymentException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;

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
     * @var ItemManagementInterface $itemManagement
     */
    protected $itemManagement;

    /**
     * Required for populating requests with item data.
     *
     * @var ItemStorageInterface $itemStorage
     */
    protected $itemStorage;

    /**
     * Helper for reading payment info instances, e.g. getting purchase ID
     * from quote payment.
     *
     * @var PaymentData
     */
    protected $paymentDataHelper;

    /**
     * Helper to determine Avarda's purchase state.
     *
     * @var PurchaseState
     */
    protected $purchaseStateHelper;

    /**
     * Command pool for API requests to Avarda.
     *
     * @var CommandPoolInterface
     */
    protected $commandPool;

    /**
     * Required for executing API requests from command pool.
     *
     * @var PaymentDataObjectFactoryInterface
     */
    protected $paymentDataObjectFactory;

    /**
     * Repository to load quote from database.
     *
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * Repository for Avarda's payment queue which links Avarda's purchase ID to
     * Magento's quote ID.
     *
     * @var PaymentQueueRepositoryInterface
     */
    protected $paymentQueueRepository;

    /**
     * Required to operate with payment queue repository.
     *
     * @var PaymentQueueInterfaceFactory
     */
    protected $paymentQueueFactory;

    /**
     * Required for placing order in Magento.
     *
     * @var CartManagementInterface
     */
    protected $cartManagement;

    /**
     * Temporary quote object to limit calls to repository.
     *
     * @var CartInterface
     */
    protected $quote;

    /** @var OrderSender */
    protected $orderSender;

    /** @var OrderRepositoryInterface */
    protected $orderRepository;

    public function __construct(
        ItemManagementInterface $itemManagement,
        ItemStorageInterface $itemStorage,
        PaymentData $paymentDataHelper,
        PurchaseState $purchaseStateHelper,
        CommandPoolInterface $commandPool,
        PaymentDataObjectFactoryInterface $paymentDataObjectFactory,
        CartRepositoryInterface $quoteRepository,
        PaymentQueueRepositoryInterface $paymentQueueRepository,
        PaymentQueueInterfaceFactory $paymentQueueFactory,
        CartManagementInterface $cartManagement,
        OrderSender $orderSender,
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
    public function getPurchaseData($cartId, $renew = false)
    {
        $quote = $this->getQuote($cartId);
        $purchaseData = $this->paymentDataHelper->getPurchaseData(
            $quote->getPayment()
        );

        $this->updatePaymentStatus($quote);
        $paymentState = $this->paymentDataHelper->getState($quote->getPayment());
        if (
            $this->purchaseStateHelper->isComplete($paymentState) ||
            $this->purchaseStateHelper->isDead($paymentState)
        ) {
            $renew = true;
        }

        if (!$purchaseData || $renew || (isset($purchaseData['renew']) && $purchaseData['renew'])) {
            /** We have to manually collect totals to populate the item storage */
            $quote->collectTotals();
            $purchaseData = $this->initializePurchase($quote);
        }

        return $purchaseData;
    }

    /**
     * {@inheritdoc}
     *
     * @param CartInterface|Quote $quote
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
        } catch (AlreadyExistsException $e) {
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
        if ($cartId instanceof CartInterface) {
            $quote = $cartId;
        } else {
            $quote = $this->getQuote($cartId);
        }
        $this->isAvardaPayment($quote);
        $this->executeCommand('avarda_get_payment_status', $quote);
    }

    /**
     * {@inheritdoc}
     */
    public function updateOnlyPaymentStatus($quote)
    {
        $this->isAvardaPayment($quote);
        $this->executeCommand('avarda_get_only_status', $quote);
    }

    /**
     * {@inheritdoc}
     */
    public function placeOrder($cartId)
    {
        $quote = $this->getQuote($cartId);
        $this->isAvardaPayment($quote);

        $state = $this->paymentDataHelper->getState($quote->getPayment());
        if (!$this->purchaseStateHelper->isComplete($state)) {
            throw new PaymentException(__('Status is not Completed'));
        }

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
        $paymentQueue->setIsProcessed(1);
        $this->paymentQueueRepository->save($paymentQueue);

        $order = $this->orderRepository->get($orderId);
        if (!$order->getEmailSent()) {
            $this->orderSender->send($order);
        }
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
        if (!$this->paymentDataHelper->getPurchaseData($payment) || $this->paymentDataHelper->getPurchaseData($payment)['purchaseId'] !== $purchaseId) {
            // sometimes initialization is done multiple times and sometimes wrong one is left for quote payment
            // when customer is going to pay and so the validation fails. This is mostly fixed, but just to make sure this is left here
            $purchaseData = $payment->getAdditionalInformation(PaymentDetailsInterface::PURCHASE_DATA);
            $purchaseData['purchaseId'] = $purchaseId;
            $payment->setAdditionalInformation(PaymentDetailsInterface::PURCHASE_DATA, $purchaseData);
            $payment->save();
        }

        return $paymentQueue->getQuoteId();
    }

    /**
     * Execute command for request to Avarda API based on quote.
     *
     * @param string              $commandCode
     * @param CartInterface|Quote $quote
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
     * @return CartInterface|Quote
     */
    public function getQuote($cartId)
    {
        if (!isset($this->quote) || $this->quote->getId() !== $cartId) {
            /** @var CartInterface|Quote $quote */
            $this->quote = $this->quoteRepository->get($cartId);
        }

        return $this->quote;
    }

    /**
     * Check if quote has a valid Avarda payment.
     *
     * @param CartInterface|Quote $quote
     * @return void
     * @throws PaymentException
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
