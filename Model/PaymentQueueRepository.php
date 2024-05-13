<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Model;

use Avarda\Checkout3\Api\Data\PaymentQueueInterface;
use Avarda\Checkout3\Api\Data\PaymentQueueInterfaceFactory;
use Avarda\Checkout3\Api\PaymentQueueRepositoryInterface;
use Avarda\Checkout3\Model\ResourceModel\PaymentQueue as PaymentQueueResource;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\AlreadyExistsException;

/**
 * Payment queue repository
 */
class PaymentQueueRepository implements PaymentQueueRepositoryInterface
{
    protected PaymentQueueResource $resource;
    protected PaymentQueueInterfaceFactory $paymentQueueFactory;

    public function __construct(
        PaymentQueueResource $resource,
        PaymentQueueInterfaceFactory $paymentQueueFactory
    ) {
        $this->resource = $resource;
        $this->paymentQueueFactory = $paymentQueueFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function save(PaymentQueueInterface $paymentQueue)
    {
        try {
            $paymentQueueId = $paymentQueue->getId();
            if ($paymentQueueId) {
                $this->getById($paymentQueueId);
            }
            $this->resource->save($paymentQueue);
        } catch (AlreadyExistsException|NoSuchEntityException $e) {
            throw $e;
        } catch (LocalizedException $e) {
            throw new CouldNotSaveException(__($e->getMessage()));
        }

        return $paymentQueue;
    }

    /**
     * {@inheritdoc}
     */
    public function get($purchaseId)
    {
        $queueId = $this->resource->getIdByPurchaseId($purchaseId);
        if (!$queueId) {
            // payment queue does not exist
            throw NoSuchEntityException::singleField('purchaseId', $purchaseId);
        }

        return $this->getById($queueId);
    }

    /**
     * {@inheritdoc}
     */
    public function getById($queueId)
    {
        $paymentQueueModel = $this->paymentQueueFactory->create()->load($queueId);
        if (!$paymentQueueModel->getId()) {
            // payment queue does not exist
            throw NoSuchEntityException::singleField('queueId', $queueId);
        }

        return $paymentQueueModel;
    }

    /**
     * {@inheritdoc}
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        // not used
    }

    /**
     * {@inheritdoc}
     */
    public function delete(PaymentQueueInterface $paymentQueue)
    {
        $this->resource->delete($paymentQueue);
    }
}
