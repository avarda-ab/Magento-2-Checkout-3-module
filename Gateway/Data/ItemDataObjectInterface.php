<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Gateway\Data;

/**
 * Interface ItemDataObjectInterface
 *
 * @api
 * @since 0.2.0
 */
interface ItemDataObjectInterface
{
    /**
     * Returns order item
     *
     * @return ItemAdapterInterface
     */
    public function getItem();

    /**
     * Returns subject data for builders
     *
     * @return array
     */
    public function getSubject();
}
