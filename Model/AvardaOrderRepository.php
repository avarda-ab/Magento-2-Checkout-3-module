<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Model;

use Avarda\Checkout3\Api\AvardaOrderRepositoryInterface;
use Avarda\Checkout3\Api\Data\AvardaOrderInterface;
use Avarda\Checkout3\Model\ResourceModel\AvardaOrder as AvardaOrderResource;

class AvardaOrderRepository implements AvardaOrderRepositoryInterface
{
    protected AvardaOrderResource $resource;
    protected AvardaOrderFactory $avardaOrderFactory;

    public function __construct(
        AvardaOrderResource $resource,
        AvardaOrderFactory $avardaOrderFactory
    ) {
        $this->resource = $resource;
        $this->avardaOrderFactory = $avardaOrderFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function save($purchaseId, $orderId)
    {
        $avardaOrder = $this->avardaOrderFactory->create();
        $avardaOrder->setPurchaseId($purchaseId);
        $avardaOrder->setOrderId($orderId);
        $this->resource->save($avardaOrder);

        return $avardaOrder;
    }

    /**
     * {@inheritdoc}
     */
    public function getByOrderId($orderId)
    {
        $avardaOrder = $this->avardaOrderFactory->create();
        return $avardaOrder->load($orderId, 'order_id');
    }

    /**
     * {@inheritdoc}
     */
    public function getByPurchaseId($purchaseId)
    {
        $avardaOrder = $this->avardaOrderFactory->create();
        return $avardaOrder->load($purchaseId, 'purchase_id');
    }

    /**
     * {@inheritdoc}
     */
    public function delete($avardaOrder)
    {
        $this->resource->delete($avardaOrder);
    }
}
