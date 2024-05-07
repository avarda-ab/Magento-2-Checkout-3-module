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
use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;
use Magento\Sales\Model\Spi\OrderResourceInterface;

/**
 * QuotePaymentManagement
 * @see QuotePaymentManagementInterface
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class QuotePaymentManagement implements QuotePaymentManagementInterface
{
    const ERROR_QUOTE_MISSING_PURCHASE = 'Cart ID %cart_id does not have an active Avarda payment.';

    protected ItemManagementInterface $itemManagement;
    protected ItemStorageInterface $itemStorage;
    protected PaymentData $paymentDataHelper;
    protected PurchaseState $purchaseStateHelper;
    protected CommandPoolInterface $commandPool;
    protected PaymentDataObjectFactoryInterface $paymentDataObjectFactory;
    protected CartRepositoryInterface $quoteRepository;
    protected PaymentQueueRepositoryInterface $paymentQueueRepository;
    protected PaymentQueueInterfaceFactory $paymentQueueFactory;
    protected CartManagementInterface $cartManagement;
    protected ?CartInterface $quote = null;
    protected OrderSender $orderSender;
    protected OrderRepositoryInterface $orderRepository;
    protected CollectionFactory $statusCollectionFactory;
    protected OrderResourceInterface $orderResource;
    protected OrderFactory $orderFactory;
    protected AddressFactory $addressFactory;
    protected ManagerInterface $messageManager;

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
        OrderRepositoryInterface $orderRepository,
        CollectionFactory $statusCollectionFactory,
        OrderResourceInterface $orderResource,
        OrderFactory $orderFactory,
        AddressFactory $addressFactory,
        ManagerInterface $messageManager
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
        $this->statusCollectionFactory = $statusCollectionFactory;
        $this->orderResource = $orderResource;
        $this->orderFactory = $orderFactory;
        $this->addressFactory = $addressFactory;
        $this->messageManager = $messageManager;
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

        // If purchaseData has 'renew' then something changed so that renew is necessary
        if (isset($purchaseData['renew']) && $purchaseData['renew']) {
            $renew = true;
        }

        // If no purchaseData, it means order is not initialized yet and initialization should be done
        // Also if renew already requested the state update is unnecessary
        if (!$renew && $purchaseData && count($purchaseData) > 0) {
            $this->updateOnlyPaymentStatus($quote);
            $paymentState = $this->paymentDataHelper->getState($quote->getPayment());
            if (
                $this->purchaseStateHelper->isComplete($paymentState) ||
                $this->purchaseStateHelper->isDead($paymentState)
            ) {
                $renew = true;
            }
        }

        if (!$purchaseData || $renew) {
            // We have to manually collect totals to populate the item storage
            $quote->collectTotals();
            // Initialize order
            $purchaseData = $this->initializePurchase($quote);
        }

        return $purchaseData;
    }

    /**
     * {@inheritdoc}
     *
     * @param CartInterface $quote
     * @return bool|string
     * @throws LocalizedException
     */
    public function initializePurchase(CartInterface $quote)
    {
        $quote->reserveOrderId();

        try {
            $this->executeCommand('avarda_initialize_payment', $quote);
        } catch (Exception $e) {
            // If address has invalid data init might fail, so we try again without phone, city and postcode
            $emptyAddress = $this->addressFactory->create();
            $emptyAddress->setTelephone('');
            $emptyAddress->setCity('');
            $emptyAddress->setPostcode('');
            $quote->setBillingAddress($emptyAddress);
            $quote->setShippingAddress($emptyAddress);
            $this->executeCommand('avarda_initialize_payment', $quote);
            $this->messageManager->addWarningMessage(__('Address data was invalid, so it was cleared. Please check your saved address.'));
        }

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
        if (!$quote instanceof CartInterface) {
            $quote = $this->getQuote($quote);
        }
        $this->isAvardaPayment($quote);
        $this->executeCommand('avarda_get_only_status', $quote);
    }

    /**
     * {@inheritdoc}
     */
    public function updateOrderPaymentStatus($order)
    {
        $this->executeCommand('avarda_update_order_status', $order);
    }

    /**
     * {@inheritdoc}
     */
    public function updateOnlyOrderPaymentStatus($order)
    {
        $this->executeCommand('avarda_get_only_status', $order);
    }

    /**
     * @param $order OrderInterface|Order
     * @return void
     * @throws PaymentException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function finalizeOrder($order)
    {
        $state = $this->paymentDataHelper->getState($order->getPayment());
        if (!$this->purchaseStateHelper->isComplete($state)) {
            throw new PaymentException(__('Payment status is not Completed'));
        }

        // Clean payment queue
        $purchaseData = $this->paymentDataHelper->getPurchaseData(
            $order->getPayment()
        );
        $paymentQueue = $this->paymentQueueRepository->get($purchaseData['purchaseId']);
        if (!$paymentQueue->getIsProcessed()) {
            $paymentQueue->setIsProcessed(1);
            $this->paymentQueueRepository->save($paymentQueue);
        }

        // Change order status
        /** @var AbstractMethod $method */
        $method = $order->getPayment()->getMethodInstance();
        $newStatus = $method->getConfigData('order_status');
        if ($order->getState() == Order::STATE_PENDING_PAYMENT) {
            $order->setState($this->getState($newStatus));
            $order->setStatus($newStatus);
            $order->addCommentToStatusHistory(__('Payment has been accepted.'));

            $payment = $order->getPayment();
            $payment->setBaseAmountAuthorized($order->getBaseTotalDue());
            $payment->setAmountAuthorized($order->getTotalDue());
            $payment->setTransactionId($purchaseData['purchaseId'])
                ->setIsTransactionClosed(0);
            $payment->addTransaction(Transaction::TYPE_AUTH);

            $this->orderRepository->save($order);
        }

        // Reload order object from db to avoid sending duplicate email
        $orderResource = $this->orderFactory->create();
        $this->orderResource->load($orderResource, $order->getId());
        if (!$orderResource->getEmailSent()) {
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
     * @param string $commandCode
     * @param CartInterface|Quote|OrderInterface $quote
     * @return void
     * @throws NotFoundException
     * @throws CommandException
     */
    protected function executeCommand($commandCode, $quote)
    {
        $arguments['amount'] = $quote->getGrandTotal();

        /** @var InfoInterface|null $payment */
        $payment = $quote->getPayment();
        if ($payment instanceof InfoInterface) {
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
     * @throws NoSuchEntityException
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

    /**
     * Get state for status
     * @param $status
     * @return string
     */
    private function getState($status)
    {
        $collection = $this->statusCollectionFactory->create()->joinStates();
        $status = $collection->addAttributeToFilter('main_table.status', $status)->getFirstItem();
        return $status->getState();
    }
}
