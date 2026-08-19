<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

declare(strict_types=1);

namespace Avarda\Checkout3\Api;

use Avarda\Checkout3\Exception\PurchaseLockedException;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * @api
 */
interface OrphanPurchaseResolverInterface
{
    const RESULT_REBOUND = 'rebound';
    const RESULT_NEWER_PENDING = 'newer_pending';
    const RESULT_NONE = 'none';

    /**
     * Binds a completed purchase without an avarda3_order_created row to the pending order of its quote.
     *
     * @throws PurchaseLockedException
     */
    public function resolveByPurchaseId(string $purchaseId): ?OrderInterface;

    /**
     * RESULT_NEWER_PENDING means a newer purchase is neither completed nor dead, so the order may still be paid.
     *
     * @return string One of the RESULT_* constants
     * @throws PurchaseLockedException
     */
    public function rebindToNewerPurchase(OrderInterface $order): string;

    /**
     * Null when the quote has no pending Avarda order or more than one.
     */
    public function findPendingOrderForQuote(int $quoteId): ?OrderInterface;
}
