<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Payment queue resource model
 */
class PaymentQueue extends AbstractDb
{
    /**
     * Define main table
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('avarda3_payment_queue', 'queue_id');
    }

    /**
     * Get payment queue identifier by purchase ID
     *
     * @param string $purchaseId
     * @return int|false
     */
    public function getIdByPurchaseId($purchaseId)
    {
        $connection = $this->getConnection();

        $select = $connection->select()->from('avarda3_payment_queue', 'queue_id')->where('purchase_id = :purchase_id');

        $bind = [':purchase_id' => (string)$purchaseId];

        return $connection->fetchOne($select, $bind);
    }
}
