<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Api\Data;

/**
 * Interface ItemDetailsInterface
 *
 * @api
 */
interface ItemDetailsInterface
{
    /**
     * Constants defined for keys of array, makes typos less likely
     */
    const NAME = 'name';
    const ITEM_OPTIONS_TEXT = 'item_options_text';
    const ITEM_ID = 'item_id';
    const PRODUCT_URL = 'product_url';
    const IMAGE_URL = 'image_url';
    const QTY_USES_DECIMALS = 'qty_uses_decimals';
    const MIN_SALE_QTY = 'min_sale_qty';
    const MAX_SALE_QTY = 'max_sale_qty';
    const QTY_INCREMENTS = 'qty_increments';

    /**
     * Get name
     *
     * @return string
     */
    public function getName();

    /**
     * Set name
     *
     * @param string $name
     * @return $this
     */
    public function setName($name);

    /**
     * Get name
     *
     * @return string
     */
    public function getItemOptionsText();

    /**
     * Set name
     *
     * @param string $name
     * @return $this
     */
    public function setItemOptionsText($optionsText);

    /**
     * Get item ID
     *
     * @return int
     */
    public function getItemId();

    /**
     * Set item ID
     *
     * @param int $itemId
     * @return $this
     */
    public function setItemId($itemId);

    /**
     * Get product URL
     *
     * @return string
     */
    public function getProductUrl();

    /**
     * Set product URL
     *
     * @param string $productUrl
     * @return $this
     */
    public function setProductUrl($productUrl);

    /**
     * Get product image URL
     *
     * @return string
     */
    public function getImageUrl();

    /**
     * Set product image URL
     *
     * @param string $imageUrl
     * @return $this
     */
    public function setImageUrl($imageUrl);

    /**
     * Get is decimals allowed for item
     *
     * @return boolean
     */
    public function getQtyUsesDecimals();

    /**
     * Set is decimals allowed for item
     *
     * @param bool $allowed
     * @return boolean
     */
    public function setQtyUsesDecimals($allowed);

    /**
     * Get min qty for item
     *
     * @return float
     */
    public function getMinSaleQty();

    /**
     * Set min qty for item
     *
     * @param float $allowed
     * @return float
     */
    public function setMinSaleQty($minQty);

    /**
     * Get min qty for item
     *
     * @return float
     */
    public function getMaxSaleQty();

    /**
     * Set min qty for item
     *
     * @param float $allowed
     * @return float
     */
    public function setMaxSaleQty($maxQty);

    /**
     * Get qty increments used for steps
     *
     * @return float
     */
    public function getQtyIncrements();

    /**
     * Set qty increments used for steps
     *
     * @param float $qtyIncrements
     * @return float
     */
    public function setQtyIncrements($qtyIncrements);
}
