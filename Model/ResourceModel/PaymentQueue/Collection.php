<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Model\ResourceModel\PaymentQueue;

use Avarda\Checkout3\Model\ResourceModel\PaymentQueue;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Payment queue collection.
 */
class Collection extends AbstractCollection
{
    /**
     * Initializes collection
     *
     * @return void
     */
    protected function _construct()
    {
        $this->addFilterToMap('queue_id', 'main_table.queue_id');
        $this->addFilterToMap('purchase_id', 'main_table.purchase_id');
        $this->_init(
            \Avarda\Checkout3\Model\PaymentQueue::class,
            PaymentQueue::class
        );
    }
}
