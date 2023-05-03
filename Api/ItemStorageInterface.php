<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Api;

use Avarda\Checkout3\Gateway\Data\ItemDataObjectInterface;

/**
 * Interface for storing Avarda item information
 *
 * @api
 */
interface ItemStorageInterface
{
    /**
     * @param ItemDataObjectInterface[] $items
     * @return $this
     */
    public function setItems($items);

    /**
     * @param ItemDataObjectInterface $item
     * @return $this
     */
    public function addItem(ItemDataObjectInterface $item);

    /**
     * @return ItemDataObjectInterface[]
     */
    public function getItems();

    /**
     * @return $this
     */
    public function reset();
}
