<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Api;

/**
 * Interface for managing Avarda item information
 * @api
 */
interface ItemManagementInterface
{
    /**
     * Get quote items additional information not provided by Magento Webapi
     *
     * @return \Avarda\Checkout3\Api\Data\ItemDetailsListInterface
     */
    public function getItemDetailsList();
}
