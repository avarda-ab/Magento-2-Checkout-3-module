<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

declare(strict_types=1);

namespace Avarda\Checkout3\Model;

use Avarda\Checkout3\Gateway\Config\Config;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\Quote\QuoteAdapter;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartInterfaceFactory;
use Magento\Quote\Model\Quote;

class AlternativeApi
{
    protected Config $config;
    protected CartInterfaceFactory $cartInterfaceFactory;

    public function __construct(
        Config $config,
        CartInterfaceFactory $cartInterfaceFactory
    ) {
        $this->config = $config;
        $this->cartInterfaceFactory = $cartInterfaceFactory;
    }

    public function isUsedForOrder(OrderAdapterInterface $order): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $items = $order->getItems();
        if (!$items && $order instanceof QuoteAdapter) {
            // If cart is not active cart items will not load,
            // but cartRepository->get calls are cached without items loaded so that doesn't help either and
            // $order is an adapter, so it doesn't have getItemsCollection method
            // thus we load the quote with model so that we get the items loaded properly
            $quote = $this->cartInterfaceFactory->create()->loadByIdWithoutStore($order->getId());
            $items = $quote->getItemsCollection()->getItems();
        }

        return $this->isUsedForItems($items ?: []);
    }

    /**
     * @param CartInterface|Quote $quote
     */
    public function isUsedForQuote(CartInterface $quote): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        return $this->isUsedForItems($quote->getItemsCollection()->getItems());
    }

    protected function isConfigured(): bool
    {
        return $this->getProductTypes() || $this->config->getAlternativeMinimumTotal() !== null;
    }

    protected function isUsedForItems(array $items): bool
    {
        $productTypes = $this->getProductTypes();
        if ($productTypes && !$this->hasMatchingItem($items, $productTypes)) {
            return false;
        }

        $minimumTotal = $this->config->getAlternativeMinimumTotal();

        return $minimumTotal === null || $this->getItemsTotal($items) > $minimumTotal;
    }

    // Excludes shipping so changing the shipping method won't switch credentials
    protected function getItemsTotal(array $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            if ($item->isDeleted() || $item->getParentItem() || $item->getParentItemId()) {
                continue;
            }

            $total += (float)$item->getBaseRowTotalInclTax() - (float)$item->getBaseDiscountAmount();
        }

        return $total;
    }

    protected function getProductTypes(): array
    {
        return array_filter(explode(',', $this->config->getAlternativeProductTypes() ?: ''));
    }

    protected function hasMatchingItem(iterable $items, array $productTypes): bool
    {
        foreach ($items as $item) {
            if ($item->isDeleted()) {
                continue;
            }

            if (in_array($item->getProductType(), $productTypes)) {
                return true;
            }

            foreach ($this->getChildItems($item) as $childItem) {
                if (in_array($childItem->getProductType(), $productTypes)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Quote items expose children as getChildren(), order items as getChildrenItems().
     */
    protected function getChildItems($item): array
    {
        $children = method_exists($item, 'getChildrenItems') ? $item->getChildrenItems() : $item->getChildren();

        return is_array($children) ? $children : [];
    }
}
