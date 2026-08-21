<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

declare(strict_types=1);

namespace Avarda\Checkout3\Model;

/**
 * Avarda accepts item updates until the bank payment is created, so an amount that moves while the
 * order is being placed reprices a purchase the customer already confirmed.
 */
class OrderPlacementState
{
    protected bool $placingOrder = false;

    public function start(): void
    {
        $this->placingOrder = true;
    }

    public function stop(): void
    {
        $this->placingOrder = false;
    }

    public function isPlacingOrder(): bool
    {
        return $this->placingOrder;
    }
}
