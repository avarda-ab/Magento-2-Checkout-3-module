<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Api;

use Avarda\Checkout3\Api\Data\PaymentQueueInterface;

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
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(PaymentQueueInterface $paymentQueue);

    /**
     * Retrieve payment queue item.
     *
     * @param string $purchaseId
     * @return PaymentQueueInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException If purchase ID doesn't exist.
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function get($purchaseId);

    /**
     * Get payment queue item by queue ID.
     *
     * @param int $queueId
     * @return PaymentQueueInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException If purchase ID doesn't exist.
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getById($queueId);

    /**
     * Retrieve payment queue items which match a specified criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Magento\Framework\Api\SearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);

    /**
     * Delete payment queue item.
     *
     * @param PaymentQueueInterface $paymentQueue
     * @return bool true on success
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(PaymentQueueInterface $paymentQueue);
}
