<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Api\Data;

/**
 * Interface ItemDetailsListInterface
 *
 * @api
 */
interface ItemDetailsListInterface
{
    /**
     * Constants defined for keys of array, makes typos less likely
     */
    const ITEMS = 'items';

    /**
     * Get quote items
     *
     * @return ItemDetailsInterface[]
     */
    public function getItems();

    /**
     * Set quote items
     *
     * @param ItemDetailsInterface[] $items
     * @return $this
     */
    public function setItems($items);

    /**
     * Set quote items
     *
     * @param ItemDetailsInterface $item
     * @return $this
     */
    public function addItem(ItemDetailsInterface $item);
}
