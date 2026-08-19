<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

declare(strict_types=1);

namespace Avarda\Checkout3\Api;

use Magento\Sales\Api\Data\OrderInterface;

/**
 * @api
 */
interface PendingOrderCancellationGuardInterface
{
    /**
     * Finalizes instead of cancelling when the purchase, or a newer one of the same quote, is paid.
     * $cancel runs at most once, under the purchase lock.
     */
    public function cancelUnlessPaid(OrderInterface $order, callable $cancel): void;
}
