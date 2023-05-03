<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Gateway\Data;

/**
 * Class ItemAdapter
 */
interface ItemAdapterInterface
{
    /**
     * Get product ID
     *
     * @return integer|null
     */
    public function getProductId();

    /**
     * Get parent item ID
     *
     * @return integer|null
     */
    public function getParentItemId();

    /**
     * Get the item product name
     *
     * @return string
     */
    public function getName();

    /**
     * Get the item options text
     *
     * @return string
     */
    public function getItemOptionsText();

    /**
     * Get the item SKU
     *
     * @return string
     */
    public function getSku();

    /**
     * Get additional data
     *
     * @return array
     */
    public function getAdditionalData();

    /**
     * Get product type
     *
     * @return string
     */
    public function getProductType();

    /**
     * Get is qty decimal
     *
     * @return boolean
     */
    public function getIsQtyDecimal();

    /**
     * Get min sale qty
     *
     * @return float
     */
    public function getMinSaleQty();

    /**
     * Get max sale qty
     *
     * @return float
     */
    public function getMaxSaleQty();

    /**
     * Get qty increments
     *
     * @return float
     */
    public function getQtyIncrements();

    /**
     * Get tax amount
     *
     * @return float
     */
    public function getTaxAmount();

    /**
     * Get tax percent/code
     *
     * @return float
     */
    public function getTaxPercent();

    /**
     * Get row total
     *
     * @return float
     */
    public function getRowTotal();

    /**
     * Get row total including tax
     *
     * @return float
     */
    public function getRowTotalInclTax();
}
