<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Model;

use Avarda\Checkout3\Api\AvardaOrderRepositoryInterface;
use Avarda\Checkout3\Api\Data\AvardaOrderInterface;

class AvardaOrderRepository implements AvardaOrderRepositoryInterface
{
    /** @var ResourceModel\AvardaOrder */
    protected $resource;

    /** @var AvardaOrderFactory */
    protected $avardaOrderFactory;

    public function __construct(
        ResourceModel\AvardaOrder $resource,
        AvardaOrderFactory $avardaOrderFactory
    ) {
        $this->resource           = $resource;
        $this->avardaOrderFactory = $avardaOrderFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function save($purchaseId)
    {
        /** @var AvardaOrder|AvardaOrderInterface $avardaOrder */
        $avardaOrder = $this->avardaOrderFactory->create();
        $avardaOrder->setPurchaseId($purchaseId);
        $this->resource->save($avardaOrder);
        return $avardaOrder;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($avardaOrder)
    {
        $this->resource->delete($avardaOrder);
    }
}
