<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Model;

use Avarda\Checkout3\Api\Data\PaymentQueueInterface;
use Magento\Framework\Model\AbstractModel;

/**
 * Payment queue model
 */
class PaymentQueue extends AbstractModel implements PaymentQueueInterface
{
    /**
     * Initialize model
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init(\Avarda\Checkout3\Model\ResourceModel\PaymentQueue::class);
    }

    /**
     * Purchase id
     *
     * @return string|null
     */
    public function getPurchaseId()
    {
        return $this->getData(self::PURCHASE_ID);
    }

    /**
     * Set purchase id
     *
     * @param string $purchaseId
     * @return $this
     */
    public function setPurchaseId($purchaseId)
    {
        $this->setData(self::PURCHASE_ID, $purchaseId);

        return $this;
    }

    /**
     * Quote id
     *
     * @return int|null
     */
    public function getQuoteId()
    {
        return $this->getData(self::QUOTE_ID);
    }

    /**
     * Set quote id
     *
     * @param int $quoteId
     * @return $this
     */
    public function setQuoteId($quoteId)
    {
        $this->setData(self::QUOTE_ID, $quoteId);

        return $this;
    }

    /**
     * @return string|null
     */
    public function getJwt()
    {
        return $this->getData(self::JWT);
    }

    public function setJwt($jwt)
    {
        $this->setData(self::JWT, $jwt);

        return $this;
    }

    /**
     * @return string|int|null
     */
    public function getExpires()
    {
        return $this->getData(self::EXPIRES);
    }

    public function setExpires($expires)
    {
        $this->setData(self::EXPIRES, $expires);

        return $this;
    }

    /**
     * Queue updated date
     *
     * @return string|null
     */
    public function getUpdatedAt()
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * Set queue updated date
     *
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->setData(self::UPDATED_AT, $updatedAt);

        return $this;
    }

    /**
     * @inheridoc
     */
    public function getIsProcessed()
    {
        return $this->getData(self::IS_PROCESSED);
    }

    /**
     * @inheridoc
     */
    public function setIsProcessed($isProcessed)
    {
        $this->setData(self::IS_PROCESSED, $isProcessed);

        return $this;
    }
}
