<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Model\Data;

use Avarda\Checkout3\Api\Data\ItemDetailsInterface;
use Magento\Framework\Model\AbstractExtensibleModel;

/**
 * @codeCoverageIgnoreStart
 */
class ItemDetails extends AbstractExtensibleModel implements
    ItemDetailsInterface
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getData(self::NAME);
    }

    /**
     * {@inheritdoc}
     */
    public function setName($name)
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function getItemOptionsText()
    {
        return $this->getData(self::ITEM_OPTIONS_TEXT);
    }

    /**
     * {@inheritdoc}
     */
    public function setItemOptionsText($optionsText)
    {
        return $this->setData(self::ITEM_OPTIONS_TEXT, $optionsText);
    }

    /**
     * {@inheritdoc}
     */
    public function getItemId()
    {
        return $this->getData(self::ITEM_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setItemId($itemId)
    {
        return $this->setData(self::ITEM_ID, $itemId);
    }

    /**
     * {@inheritdoc}
     */
    public function getProductUrl()
    {
        return $this->getData(self::PRODUCT_URL);
    }

    /**
     * {@inheritdoc}
     */
    public function setProductUrl($productUrl)
    {
        return $this->setData(self::PRODUCT_URL, $productUrl);
    }

    /**
     * {@inheritdoc}
     */
    public function getImageUrl()
    {
        return $this->getData(self::IMAGE_URL);
    }

    /**
     * {@inheritdoc}
     */
    public function setImageUrl($imageUrl)
    {
        return $this->setData(self::IMAGE_URL, $imageUrl);
    }

    /**
     * {@inheritdoc}
     */
    public function getQtyUsesDecimals()
    {
        return $this->getData(self::QTY_USES_DECIMALS);
    }

    /**
     * {@inheritdoc}
     */
    public function setQtyUsesDecimals($allowed)
    {
        return $this->setData(self::QTY_USES_DECIMALS, $allowed);
    }

    /**
     * {@inheritdoc}
     */
    public function getMinSaleQty()
    {
        return $this->getData(self::MIN_SALE_QTY);
    }

    /**
     * {@inheritdoc}
     */
    public function setMinSaleQty($minQty)
    {
        return $this->setData(self::MIN_SALE_QTY, $minQty);
    }

    /**
     * {@inheritdoc}
     */
    public function getMaxSaleQty()
    {
        return $this->getData(self::MAX_SALE_QTY);
    }

    /**
     * {@inheritdoc}
     */
    public function setMaxSaleQty($maxQty)
    {
        return $this->setData(self::MAX_SALE_QTY, $maxQty);
    }

    /**
     * {@inheritdoc}
     */
    public function getQtyIncrements()
    {
        return $this->getData(self::QTY_INCREMENTS);
    }

    /**
     * {@inheritdoc}
     */
    public function setQtyIncrements($qtyIncrements)
    {
        return $this->setData(self::QTY_INCREMENTS, $qtyIncrements);
    }
}
