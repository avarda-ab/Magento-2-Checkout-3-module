<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Api;

use Magento\Framework\Exception\AlreadyExistsException;

interface AvardaOrderRepositoryInterface
{
    /**
     * Save info that purchaseId has order
     *
     * @param string $purchaseId
     * @throws AlreadyExistsException
     */
    public function save($purchaseId);

    /**
     * @param $avardaOrder
     * @return mixed
     */
    public function delete($avardaOrder);
}
