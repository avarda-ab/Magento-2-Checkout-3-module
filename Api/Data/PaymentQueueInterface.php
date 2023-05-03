<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Api\Data;

/**
 * PaymentQueue interface.
 *
 * @api
 */
interface PaymentQueueInterface
{
    /**#@+
     * Constants defined for keys of  data array
     */
    const QUEUE_ID = 'queue_id';
    const PURCHASE_ID = 'purchase_id';
    const QUOTE_ID = 'quote_id';
    const JWT = 'jwt';
    const EXPIRES = 'expires';
    const UPDATED_AT = 'updated_at';
    const IS_PROCESSED = 'is_processed';
    /**#@-*/

    /**
     * Queue id
     *
     * @return int|null
     */
    public function getId();

    /**
     * Set queue id
     *
     * @param int $id
     * @return $this
     */
    public function setId($id);

    /**
     * Purchase id
     *
     * @return string|null
     */
    public function getPurchaseId();

    /**
     * Set purchase id
     *
     * @param string $purchaseId
     * @return $this
     */
    public function setPurchaseId($purchaseId);

    /**
     * Quote id
     *
     * @return int|null
     */
    public function getQuoteId();

    /**
     * Set quote id
     *
     * @param int $quoteId
     * @return $this
     */
    public function setQuoteId($quoteId);

    /**
     * Jwt token
     *
     * @return string|null
     */
    public function getJwt();

    /**
     * Set jwt
     *
     * @param string $jwt
     * @return $this
     */
    public function setJwt($jwt);

    /**
     * Expires
     *
     * @return string|null
     */
    public function getExpires();

    /**
     * Set expires
     *
     * @param string $expires
     * @return $this
     */
    public function setExpires($expires);

    /**
     * Queue updated date
     *
     * @return string|null
     */
    public function getUpdatedAt();

    /**
     * Set queue updated date
     *
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt($updatedAt);

    /**
     * Queue is processed
     *
     * @return int
     */
    public function getIsProcessed();

    /**
     * Set queue is processed
     *
     * @param int $isProcessed
     * @return $this
     */
    public function setIsProcessed($isProcessed);
}
