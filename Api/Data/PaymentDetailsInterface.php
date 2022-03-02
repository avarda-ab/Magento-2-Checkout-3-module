<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Api\Data;

/**
 * Interface PaymentDetailsInterface
 * @api
 */
interface PaymentDetailsInterface
{
    /**
     * Constants defined for keys of array, makes typos less likely
     */
    const PURCHASE_DATA = 'purchase_data';

    /**
     * Return the generated purchase ID
     *
     * @return string
     */
    public function getPurchaseData();

    /**
     * Return the generated purchase ID
     *
     * @param string $purchaseData
     * @return $this
     */
    public function setPurchaseData($purchaseData);
}
