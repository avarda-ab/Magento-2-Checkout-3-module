<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Model;

use Avarda\Checkout3\Api\Data\AvardaOrderInterface;
use Magento\Framework\Model\AbstractModel;

class AvardaOrder extends AbstractModel implements AvardaOrderInterface
{
    protected function _construct()
    {
        $this->_init(\Avarda\Checkout3\Model\ResourceModel\AvardaOrder::class);
    }

    /**
     * Purchase id
     *
     * @return string
     */
    public function getPurchaseId()
    {
        return $this->getData(self::PURCHASE_ID);
    }

    /**
     * Set purchase id
     *
     * @param string $purchaseId
     * @return $this
     */
    public function setPurchaseId(string $purchaseId)
    {
        $this->setData(self::PURCHASE_ID, $purchaseId);

        return $this;
    }
}
