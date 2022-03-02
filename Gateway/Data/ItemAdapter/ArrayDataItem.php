<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Gateway\Data\ItemAdapter;

use Avarda\Checkout3\Gateway\Data\ItemAdapterInterface;

/**
 * Class ItemAdapter\ShipmentItem
 */
class ArrayDataItem implements ItemAdapterInterface
{
    /**
     * @var array
     */
    protected $data = [];

    /**
     * ArrayDataItem constructor.
     *
     * @param array $data
     */
    public function __construct(
        array $data
    ) {
        $this->data = $data;
    }

    /**
     * Get product ID
     *
     * @return integer|null
     */
    public function getProductId()
    {
        return null;
    }

    /**
     * Get parent item ID
     *
     * @return integer|null
     */
    public function getParentItemId()
    {
        return null;
    }

    /**
     * Get the item product name
     *
     * @return string
     */
    public function getName()
    {
        if (array_key_exists('name', $this->data)) {
            return $this->data['name'];
        }

        return '';
    }

    /**
     * Get the item options text
     *
     * @return string
     */
    public function getItemOptionsText()
    {
        if (array_key_exists('item_options_text', $this->data)) {
            return $this->data['item_options_text'];
        }

        return '';
    }

    /**
     * Get the item SKU
     *
     * @return string
     */
    public function getSku()
    {
        if (array_key_exists('sku', $this->data)) {
            return $this->data['sku'];
        }

        return '';
    }

    /**
     * Get additional data
     *
     * @return array
     */
    public function getAdditionalData()
    {
        return [];
    }

    /**
     * Get product type
     *
     * @return string
     */
    public function getProductType()
    {
        if (array_key_exists('product_type', $this->data)) {
            return $this->data['product_type'];
        }

        return 'undefined';
    }

    /**
     * Get is qty decimal
     *
     * @return string
     */
    public function getIsQtyDecimal()
    {
        if (array_key_exists('is_qty_decimal', $this->data)) {
            return $this->data['is_qty_decimal'];
        }

        return 'undefined';
    }

    /**
     * Get min qty
     *
     * @return float
     */
    public function getMinSaleQty()
    {
        if (array_key_exists('min_sale_qty', $this->data)) {
            return $this->data['min_sale_qty'];
        }

        return 1.0;
    }

    /**
     * Get max qty
     *
     * @return float
     */
    public function getMaxSaleQty()
    {
        if (array_key_exists('max_sale_qty', $this->data)) {
            return $this->data['max_sale_qty'];
        }

        return 10000.0;
    }

    /**
     * Get qty increments
     *
     * @return float
     */
    public function getQtyIncrements()
    {
        if (array_key_exists('qty_increments', $this->data)) {
            return $this->data['qty_increments'];
        }

        return 1.0;
    }

    /**
     * Get tax amount
     *
     * @return float
     */
    public function getTaxAmount()
    {
        if (array_key_exists('tax_amount', $this->data)) {
            return $this->data['tax_amount'];
        }

        return 0.0;
    }

    /**
     * Get tax percent/code
     *
     * @return float
     */
    public function getTaxPercent()
    {
        if (array_key_exists('tax_percent', $this->data)) {
            return $this->data['tax_percent'];
        }

        return 0.0;
    }

    /**
     * Get row total
     *
     * @return float
     */
    public function getRowTotal()
    {
        if (array_key_exists('row_total', $this->data)) {
            return $this->data['row_total'];
        }

        return 0.0;
    }

    /**
     * Get row total including tax
     *
     * @return float
     */
    public function getRowTotalInclTax()
    {
        if (array_key_exists('row_total_incl_tax', $this->data)) {
            return $this->data['row_total_incl_tax'];
        }

        return 0.0;
    }
}
