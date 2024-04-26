<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Gateway\Data;

/**
 * Service for creation transferable item object from model
 *
 * @api
 * @since 0.2.0
 */
interface ItemDataObjectFactoryInterface
{
    /**
     * Creates Item Data Object
     *
     * @param ItemAdapterInterface $item
     * @param float $qty
     * @param float $amount
     * @param float $taxAmount
     *
     * @return ItemDataObjectInterface
     */
    public function create(
        ItemAdapterInterface $item,
        $qty,
        $amount,
        $taxAmount
    );
}
