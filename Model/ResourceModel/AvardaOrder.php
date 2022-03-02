<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class AvardaOrder extends AbstractDb
{
    /**
     * Define main table
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('avarda3_order_created', 'entity_id');
    }
}
