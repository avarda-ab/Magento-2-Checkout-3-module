<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Api\Data;

interface AvardaOrderInterface
{
    const ENTITY_ID = 'entity_id';
    const PURCHASE_ID = 'purchase_id';

    /**
     * Queue id
     *
     * @return int|null
     */
    public function getId();

    /**
     * Set queue id
     *
     * @param int $id
     * @return $this
     */
    public function setId($id);

    /**
     * Purchase id
     *
     * @return string|null
     */
    public function getPurchaseId();

    /**
     * Set purchase id
     *
     * @param string $purchaseId
     * @return $this
     */
    public function setPurchaseId(string $purchaseId);
}
