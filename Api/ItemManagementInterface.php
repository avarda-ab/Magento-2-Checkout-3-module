<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Api;

use Avarda\Checkout3\Api\Data\ItemDetailsListInterface;

/**
 * Interface for managing Avarda item information
 *
 * @api
 */
interface ItemManagementInterface
{
    /**
     * Get quote items additional information not provided by Magento Webapi
     *
     * @return ItemDetailsListInterface
     */
    public function getItemDetailsList();
}
