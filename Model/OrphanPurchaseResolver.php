<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

declare(strict_types=1);

namespace Avarda\Checkout3\Model;

use Avarda\Checkout3\Api\AvardaOrderRepositoryInterface;
use Avarda\Checkout3\Api\Data\PaymentDetailsInterface;
use Avarda\Checkout3\Api\Data\PaymentQueueInterface;
use Avarda\Checkout3\Api\OrphanPurchaseResolverInterface;
use Avarda\Checkout3\Api\PaymentQueueRepositoryInterface;
use Avarda\Checkout3\Exception\PurchaseLockedException;
use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PurchaseState;
use Avarda\Checkout3\Model\ResourceModel\PaymentQueue\CollectionFactory as PaymentQueueCollectionFactory;
use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Data\Collection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class OrphanPurchaseResolver implements OrphanPurchaseResolverInterface
{
    const LOCK_TIMEOUT = 3;
    const AMOUNT_TOLERANCE = 0.01;

    protected PaymentQueueRepositoryInterface $paymentQueueRepository;
    protected PaymentQueueCollectionFactory $paymentQueueCollectionFactory;
    protected AvardaOrderRepositoryInterface $avardaOrderRepository;
    protected OrderRepositoryInterface $orderRepository;
    protected OrderPaymentRepositoryInterface $orderPaymentRepository;
    protected SearchCriteriaBuilder $searchCriteriaBuilder;
    protected PaymentData $paymentDataHelper;
    protected PurchaseState $purchaseStateHelper;
    protected CommandPoolInterface $commandPool;
    protected PaymentDataObjectFactoryInterface $paymentDataObjectFactory;
    protected LockManagerInterface $lockManager;
    protected LoggerInterface $logger;

    public function __construct(
        PaymentQueueRepositoryInterface $paymentQueueRepository,
        PaymentQueueCollectionFactory $paymentQueueCollectionFactory,
        AvardaOrderRepositoryInterface $avardaOrderRepository,
        OrderRepositoryInterface $orderRepository,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        PaymentData $paymentDataHelper,
        PurchaseState $purchaseStateHelper,
        CommandPoolInterface $commandPool,
        PaymentDataObjectFactoryInterface $paymentDataObjectFactory,
        LockManagerInterface $lockManager,
        LoggerInterface $logger,
    ) {
        $this->paymentQueueRepository = $paymentQueueRepository;
        $this->paymentQueueCollectionFactory = $paymentQueueCollectionFactory;
        $this->avardaOrderRepository = $avardaOrderRepository;
        $this->orderRepository = $orderRepository;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->paymentDataHelper = $paymentDataHelper;
        $this->purchaseStateHelper = $purchaseStateHelper;
        $this->commandPool = $commandPool;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->lockManager = $lockManager;
        $this->logger = $logger;
    }

    public function resolveByPurchaseId(string $purchaseId): ?OrderInterface
    {
        try {
            $paymentQueue = $this->paymentQueueRepository->get($purchaseId);
        } catch (NoSuchEntityException $e) {
            return null;
        }
        if (!$paymentQueue->getQuoteId()) {
            return null;
        }

        $order = $this->findPendingOrderForQuote((int)$paymentQueue->getQuoteId());
        if ($order === null) {
            return null;
        }

        return $this->tryPurchase($order, $paymentQueue) === OrphanPurchaseResolverInterface::RESULT_REBOUND ? $order : null;
    }

    public function rebindToNewerPurchase(OrderInterface $order): string
    {
        if (!$order->getQuoteId() || $order->getState() !== Order::STATE_PENDING_PAYMENT) {
            return OrphanPurchaseResolverInterface::RESULT_NONE;
        }

        $currentPurchaseId = $this->paymentDataHelper->getPurchaseId($order->getPayment());
        $currentQueueId = 0;
        if ($currentPurchaseId !== null) {
            try {
                $currentQueueId = (int)$this->paymentQueueRepository->get($currentPurchaseId)->getId();
            } catch (NoSuchEntityException $e) {
                $currentQueueId = 0;
            }
        }

        // updated_at changes on every row update, so only the auto-increment id orders purchases reliably
        $collection = $this->paymentQueueCollectionFactory->create();
        $collection->addFieldToFilter(PaymentQueueInterface::QUOTE_ID, (int)$order->getQuoteId());
        $collection->addFieldToFilter(PaymentQueueInterface::QUEUE_ID, ['gt' => $currentQueueId]);
        $collection->addFieldToFilter(PaymentQueueInterface::IS_PROCESSED, 0);
        $collection->setOrder(PaymentQueueInterface::QUEUE_ID, Collection::SORT_ORDER_DESC);

        $result = OrphanPurchaseResolverInterface::RESULT_NONE;
        /** @var PaymentQueueInterface $paymentQueue */
        foreach ($collection as $paymentQueue) {
            if ($paymentQueue->getPurchaseId() === $currentPurchaseId) {
                continue;
            }
            $outcome = $this->tryPurchase($order, $paymentQueue);
            if ($outcome === OrphanPurchaseResolverInterface::RESULT_REBOUND) {
                return $outcome;
            }
            if ($outcome === OrphanPurchaseResolverInterface::RESULT_NEWER_PENDING) {
                $result = $outcome;
            }
        }

        return $result;
    }

    public function findPendingOrderForQuote(int $quoteId): ?OrderInterface
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(OrderInterface::QUOTE_ID, $quoteId)
            ->addFilter(OrderInterface::STATE, Order::STATE_PENDING_PAYMENT)
            ->create();

        $candidates = [];
        foreach ($this->orderRepository->getList($searchCriteria)->getItems() as $order) {
            if ($this->paymentDataHelper->isAvardaPayment($order->getPayment())) {
                $candidates[] = $order;
            }
        }

        if (count($candidates) > 1) {
            $this->logger->warning(sprintf(
                'Avarda purchase recovery skipped: quote %d has %d pending Avarda orders',
                $quoteId,
                count($candidates)
            ));
        }
        if (count($candidates) !== 1) {
            return null;
        }

        // The repository registry instance is what the status handlers update later on
        return $this->orderRepository->get((int)$candidates[0]->getEntityId());
    }

    /**
     * RESULT_NEWER_PENDING also covers a completed purchase that does not match the order total,
     * so the order is not cancelled under it.
     *
     * @return string One of the RESULT_* constants
     * @throws PurchaseLockedException
     */
    protected function tryPurchase(OrderInterface $order, PaymentQueueInterface $paymentQueue): string
    {
        $newPurchaseId = (string)$paymentQueue->getPurchaseId();
        $payment = $order->getPayment();
        $oldPurchaseId = $this->paymentDataHelper->getPurchaseId($payment);

        $lockNames = array_values(array_unique(array_filter([$oldPurchaseId, $newPurchaseId])));
        sort($lockNames);
        if (!$this->acquireLocks($lockNames)) {
            throw new PurchaseLockedException(__(
                'Avarda purchase recovery skipped for order %1: purchase %2 or %3 is being processed elsewhere',
                $order->getIncrementId(),
                (string)$oldPurchaseId,
                $newPurchaseId
            ));
        }

        try {
            $mapped = $this->avardaOrderRepository->getByPurchaseId($newPurchaseId);
            if ($mapped->getOrderId() && (int)$mapped->getOrderId() !== (int)$order->getEntityId()) {
                return OrphanPurchaseResolverInterface::RESULT_NONE;
            }

            $probe = $this->probePurchase($order, $newPurchaseId, $paymentQueue);
            if ($probe === null) {
                return $this->isExpired($paymentQueue) ? OrphanPurchaseResolverInterface::RESULT_NONE : OrphanPurchaseResolverInterface::RESULT_NEWER_PENDING;
            }
            $state = $this->paymentDataHelper->getState($probe);
            if (!$this->purchaseStateHelper->isComplete($state)) {
                $this->logger->info(sprintf(
                    'Avarda purchase %s is in state %s, not rebinding order %s',
                    $newPurchaseId,
                    $state,
                    $order->getIncrementId()
                ));
                return $this->purchaseStateHelper->isDead($state) || $this->isExpired($paymentQueue)
                    ? OrphanPurchaseResolverInterface::RESULT_NONE
                    : OrphanPurchaseResolverInterface::RESULT_NEWER_PENDING;
            }
            if (!$this->purchaseTotalMatches($order, $probe, $newPurchaseId)) {
                return OrphanPurchaseResolverInterface::RESULT_NEWER_PENDING;
            }

            $additionalInformation = $probe->getAdditionalInformation();
            unset(
                $additionalInformation[PaymentData::PURCHASE_TOTAL],
                $additionalInformation[PaymentData::PURCHASE_CURRENCY]
            );
            $payment->setAdditionalInformation($additionalInformation);
            $payment->setMethod($probe->getMethod());
            $this->orderPaymentRepository->save($payment);

            $stale = $this->avardaOrderRepository->getByOrderId($order->getEntityId());
            if (!$mapped->getOrderId()) {
                $this->avardaOrderRepository->save($newPurchaseId, (string)$order->getEntityId());
            }
            if ($stale->getId() && $stale->getPurchaseId() !== $newPurchaseId) {
                $this->avardaOrderRepository->delete($stale);
            }

            $this->logger->info(sprintf(
                $oldPurchaseId === $newPurchaseId
                    ? 'Avarda order %s mapping restored for completed purchase %s (was %s)'
                    : 'Avarda order %s rebound from purchase %s to completed purchase %s',
                $order->getIncrementId(),
                (string)$oldPurchaseId,
                $newPurchaseId
            ));

            return OrphanPurchaseResolverInterface::RESULT_REBOUND;
        } catch (Exception $e) {
            $this->logger->error(sprintf(
                'Avarda purchase recovery failed for order %s and purchase %s: %s',
                $order->getIncrementId(),
                $newPurchaseId,
                $e->getMessage()
            ));
            return OrphanPurchaseResolverInterface::RESULT_NEWER_PENDING;
        } finally {
            $this->releaseLocks($lockNames);
        }
    }

    /**
     * Probes on a detached payment copy so a non-completed purchase leaves the real payment untouched.
     * Null when the state could not be fetched.
     */
    protected function probePurchase(
        OrderInterface $order,
        string $purchaseId,
        PaymentQueueInterface $paymentQueue,
    ): ?InfoInterface {
        $probe = clone $order->getPayment();
        $purchaseData = $this->paymentDataHelper->getPurchaseData($probe) ?: [];
        $purchaseData['purchaseId'] = $purchaseId;
        if ($paymentQueue->getJwt()) {
            $purchaseData['jwt'] = $paymentQueue->getJwt();
        }
        unset($purchaseData['renew']);
        $probe->setAdditionalInformation(PaymentDetailsInterface::PURCHASE_DATA, $purchaseData);
        $probe->setAdditionalInformation('renew', false);

        try {
            $this->commandPool->get('avarda_get_purchase_status')->execute([
                'payment' => $this->paymentDataObjectFactory->create($probe),
                'amount' => $order->getGrandTotal(),
            ]);
        } catch (Exception $e) {
            $this->logger->warning(sprintf(
                'Avarda purchase %s status could not be fetched for order %s: %s',
                $purchaseId,
                $order->getIncrementId(),
                $e->getMessage()
            ));
            return null;
        }

        return $probe;
    }

    /**
     * A purchase whose checkout session has expired can no longer be completed by the customer.
     */
    protected function isExpired(PaymentQueueInterface $paymentQueue): bool
    {
        return $paymentQueue->getExpires() && (int)$paymentQueue->getExpires() < time();
    }

    protected function purchaseTotalMatches(OrderInterface $order, InfoInterface $probe, string $purchaseId): bool
    {
        $total = $probe->getAdditionalInformation(PaymentData::PURCHASE_TOTAL);
        $currency = $probe->getAdditionalInformation(PaymentData::PURCHASE_CURRENCY);
        $orderCurrency = $order->getOrderCurrencyCode();

        $matches = $total !== null
            && abs((float)$total - (float)$order->getGrandTotal()) <= self::AMOUNT_TOLERANCE
            && $currency && $orderCurrency
            && strcasecmp((string)$currency, (string)$orderCurrency) === 0;

        if (!$matches) {
            // The purchase is completed, so money was captured for an amount nobody can ship against
            $this->logger->error(sprintf(
                'Avarda purchase %s total %s %s does not match order %s total %s %s, not rebinding',
                $purchaseId,
                $total === null ? 'unknown' : (string)$total,
                (string)$currency,
                $order->getIncrementId(),
                (string)$order->getGrandTotal(),
                (string)$orderCurrency
            ));
        }

        return $matches;
    }

    /**
     * Callers already hold one of the locks; the database lock backend is re-entrant, so it only nests.
     *
     * @param string[] $lockNames
     */
    protected function acquireLocks(array $lockNames): bool
    {
        $acquired = [];
        try {
            foreach ($lockNames as $lockName) {
                if (!$this->lockManager->lock($lockName, self::LOCK_TIMEOUT)) {
                    $this->releaseLocks($acquired);
                    return false;
                }
                $acquired[] = $lockName;
            }
        } catch (Exception $e) {
            $this->releaseLocks($acquired);
            return false;
        }

        return true;
    }

    /**
     * @param string[] $lockNames
     */
    protected function releaseLocks(array $lockNames): void
    {
        foreach ($lockNames as $lockName) {
            try {
                $this->lockManager->unlock($lockName);
            } catch (Exception $e) {
                $this->logger->warning(sprintf('Avarda purchase lock %s could not be released', $lockName));
            }
        }
    }
}
