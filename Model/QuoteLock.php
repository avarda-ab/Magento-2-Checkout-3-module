<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Model;

use Magento\Framework\Lock\LockManagerInterface;

/**
 * Serializes Avarda purchase initialization and updates per quote, because Avarda
 * rejects concurrent modifications and parallel initializations desync the frontend.
 * Reentrant within a request: getPurchaseData collects totals inside the lock, which
 * triggers the collect totals plugin locking the same quote.
 */
class QuoteLock
{
    const LOCK_PREFIX = 'AVARDA_QUOTE_';
    const LOCK_TIMEOUT = 10;

    protected LockManagerInterface $lockManager;
    protected array $held = [];

    public function __construct(LockManagerInterface $lockManager)
    {
        $this->lockManager = $lockManager;
    }

    /**
     * Returns true also when this request already holds the lock; false means the
     * wait timed out and the caller proceeds unlocked instead of failing the checkout.
     */
    public function lock($quoteId): bool
    {
        $name = self::LOCK_PREFIX . $quoteId;
        if (!empty($this->held[$name])) {
            $this->held[$name]++;
            return true;
        }

        $locked = $this->lockManager->lock($name, self::LOCK_TIMEOUT);
        if ($locked) {
            $this->held[$name] = 1;
        }

        return $locked;
    }

    public function unlock($quoteId): void
    {
        $name = self::LOCK_PREFIX . $quoteId;
        if (empty($this->held[$name])) {
            return;
        }

        if (--$this->held[$name] === 0) {
            unset($this->held[$name]);
            $this->lockManager->unlock($name);
        }
    }
}
