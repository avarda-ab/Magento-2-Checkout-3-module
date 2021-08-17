<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Api;

use Avarda\Checkout3\Api\Data\PaymentQueueInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Payment Queue CRUD interface.
 * @api
 */
interface PaymentQueueRepositoryInterface
{
    /**
     * Create or update a payment queue item.
     *
     * @param PaymentQueueInterface $paymentQueue
     * @return PaymentQueueInterface
     * @throws LocalizedException
     */
    public function save(PaymentQueueInterface $paymentQueue);

    /**
     * Retrieve payment queue item.
     *
     * @param string $purchaseId
     * @return PaymentQueueInterface
     * @throws NoSuchEntityException If purchase ID doesn't exist.
     * @throws LocalizedException
     */
    public function get($purchaseId);

    /**
     * Get payment queue item by queue ID.
     *
     * @param int $queueId
     * @return PaymentQueueInterface
     * @throws NoSuchEntityException If purchase ID doesn't exist.
     * @throws LocalizedException
     */
    public function getById($queueId);

    /**
     * Retrieve payment queue items which match a specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     * @throws LocalizedException
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * Delete payment queue item.
     *
     * @param PaymentQueueInterface $paymentQueue
     * @return bool true on success
     * @throws LocalizedException
     */
    public function delete(PaymentQueueInterface $paymentQueue);
}
