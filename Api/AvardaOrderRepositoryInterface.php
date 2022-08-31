<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Api;

use Avarda\Checkout3\Model\AvardaOrder;
use Magento\Framework\Exception\AlreadyExistsException;

interface AvardaOrderRepositoryInterface
{
    /**
     * Save info that purchaseId has order
     *
     * @param string $purchaseId
     * @param int|string $orderId
     * @throws AlreadyExistsException
     */
    public function save($purchaseId, $orderId);

    /**
     * @param $orderId
     * @return AvardaOrder
     */
    public function getByOrderId($orderId);

    /**
     * To find the orderId with purchaseId
     * @param $purchaseId
     * @return AvardaOrder
     */
    public function getByPurchaseId($purchaseId);

    /**
     * @param $avardaOrder
     * @return mixed
     */
    public function delete($avardaOrder);
}
