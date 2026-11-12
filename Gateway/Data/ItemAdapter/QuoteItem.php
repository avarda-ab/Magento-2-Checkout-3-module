<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Gateway\Data\ItemAdapter;

use Avarda\Checkout3\Gateway\Data\ItemAdapterInterface;
use Magento\Quote\Api\Data\CartItemInterface;

class QuoteItem implements ItemAdapterInterface
{
    protected CartItemInterface $quoteItem;

    public function __construct(
        CartItemInterface $quoteItem,
    ) {
        $this->quoteItem = $quoteItem;
    }

    public function getQuoteItem(): CartItemInterface
    {
        return $this->quoteItem;
    }

    public function getProductId()
    {
        return $this->quoteItem->getProductId();
    }

    public function getParentItemId()
    {
        return $this->quoteItem->getParentItemId();
    }

    public function getName()
    {
        return $this->quoteItem->getName();
    }

    public function getItemOptionsText()
    {
        return $this->quoteItem->getItemOptionsText();
    }

    public function getSku()
    {
        return $this->quoteItem->getSku();
    }

    public function getAdditionalData()
    {
        return $this->quoteItem->getAdditionalData();
    }

    public function getProductType()
    {
        return $this->quoteItem->getProductType();
    }

    public function getIsQtyDecimal()
    {
        return $this->quoteItem->getIsQtyDecimal();
    }

    public function getMinSaleQty()
    {
        return $this->quoteItem->getMinSaleQty();
    }

    public function getMaxSaleQty()
    {
        return $this->quoteItem->getMaxSaleQty();
    }

    public function getQtyIncrements()
    {
        return $this->quoteItem->getQtyIncrements();
    }

    public function getTaxAmount()
    {
        return $this->quoteItem->getTaxAmount();
    }

    public function getTaxPercent()
    {
        return $this->quoteItem->getTaxPercent();
    }

    public function getRowTotal()
    {
        return $this->quoteItem->getRowTotal();
    }

    public function getRowTotalInclTax()
    {
        return $this->quoteItem->getRowTotalInclTax();
    }
}
