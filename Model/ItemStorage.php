<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Model;

use Avarda\Checkout3\Api\ItemStorageInterface;
use Avarda\Checkout3\Gateway\Data\ItemDataObjectInterface;

class ItemStorage implements ItemStorageInterface
{
    /**
     * @var ItemDataObjectInterface[]
     */
    protected $items;

    /**
     * {@inheritdoc}
     */
    public function setItems($items)
    {
        if ($items !== null) {
            $this->items = $items;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addItem(ItemDataObjectInterface $item)
    {
        $this->items = array_merge(
            $this->getItems(),
            [$item]
        );

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getItems()
    {
        if (isset($this->items)) {
            return $this->items;
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $this->items = [];

        return $this;
    }
}
