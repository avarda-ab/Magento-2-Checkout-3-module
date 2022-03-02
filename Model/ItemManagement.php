<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Model;

use Avarda\Checkout3\Api\Data\ItemDetailsInterface;
use Avarda\Checkout3\Api\Data\ItemDetailsInterfaceFactory;
use Avarda\Checkout3\Api\Data\ItemDetailsListInterface;
use Avarda\Checkout3\Api\Data\ItemDetailsListInterfaceFactory;
use Avarda\Checkout3\Api\ItemManagementInterface;
use Avarda\Checkout3\Api\ItemStorageInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ItemManagement implements ItemManagementInterface
{
    const IMAGE_THUMBNAIL = 'cart_page_product_thumbnail';

    /**
     * @var ItemStorageInterface
     */
    protected $itemStorage;

    /**
     * @var ItemDetailsInterfaceFactory
     */
    protected $itemDetailsFactory;

    /**
     * @var ItemDetailsListInterfaceFactory
     */
    protected $itemDetailsListFactory;

    /**
     * @var \Magento\Catalog\Helper\ImageFactory
     */
    protected $imageHelperFactory;

    /**
     * ItemManagement constructor.
     *
     * @param ItemStorageInterface $itemStorage
     * @param ItemDetailsInterfaceFactory $itemDetailsFactory
     * @param ItemDetailsListInterfaceFactory $itemDetailsListFactory
     * @param \Magento\Catalog\Helper\ImageFactory $imageHelperFactory
     */
    public function __construct(
        ItemStorageInterface $itemStorage,
        ItemDetailsInterfaceFactory $itemDetailsFactory,
        ItemDetailsListInterfaceFactory $itemDetailsListFactory,
        \Magento\Catalog\Helper\ImageFactory $imageHelperFactory
    ) {
        $this->itemStorage = $itemStorage;
        $this->itemDetailsFactory = $itemDetailsFactory;
        $this->itemDetailsListFactory = $itemDetailsListFactory;
        $this->imageHelperFactory = $imageHelperFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getItemDetailsList()
    {
        /**
         * @var ItemDetailsListInterface $itemDetailsList
         */
        $itemDetailsList = $this->itemDetailsListFactory->create();
        $items = [];
        foreach ($this->itemStorage->getItems() as $item) {
            /**
             * @var ItemDetailsInterface $itemDetails
             */
            $itemDetails = $this->itemDetailsFactory->create();
            $itemDetails->setItemId($item->getItemId());
            $itemDetails->setProductUrl($this->getProductUrl($item));
            $itemDetails->setQtyUsesDecimals($this->getQtyUsesDecimals($item));

            //Logics for getting and setting min + max qty and qty increments
            $itemDetails->setMinSaleQty($this->getMinSaleQty($item));
            $itemDetails->setMaxSaleQty($this->getMaxSaleQty($item));
            $itemDetails->setQtyIncrements($this->getQtyIncrements($item));
            $itemDetails->setName($this->getName($item));
            $itemDetails->setItemOptionsText($this->getItemOptionsText($item));

            $imageUrl = $this->getImageUrl($item, self::IMAGE_THUMBNAIL, [
                'type' => 'small_image',
                'width' => 165,
                'height' => 165
            ]);
            $itemDetails->setImageUrl($imageUrl);

            $items[] = $itemDetails;
        }

        $itemDetailsList->setItems($items);
        return $itemDetailsList;
    }

    /**
     * Get item name
     *
     * @param \Magento\Quote\Api\Data\CartItemInterface $item
     * @return string
     */
    protected function getName($item)
    {
        return $item->getName();
    }

    /**
     * Get options text for item
     *
     * @param \Magento\Quote\Api\Data\CartItemInterface $item
     * @return string
     */
    protected function getItemOptionsText($item)
    {
        $optionText = '';

        //Add selected product options for configurable product if any
        if ($item->getProductType() === Configurable::TYPE_CODE) {
            $product = $item->getProduct();
            $options = $product->getTypeInstance(true)->getOrderOptions($product);
            if (isset($options['attributes_info']) && count($options['attributes_info']) > 0) {
                foreach ($options['attributes_info'] as $attributeInfo) {
                    if($optionText != '') {
                        $optionText .= ', ';
                    }
                    $optionText .= $attributeInfo['label'] . ': ' .$attributeInfo['value'];
                }
            }
        }
        return $optionText;
    }

    /**
     * Retrieve URL to item Product
     *
     * @param \Magento\Quote\Api\Data\CartItemInterface $item
     * @return string
     */
    protected function getProductUrl($item)
    {
        if ($item->getRedirectUrl()) {
            return $item->getRedirectUrl();
        }

        $product = $item->getProduct();
        $option = $item->getOptionByCode('product_type');
        if ($option) {
            $product = $option->getProduct();
        }

        return $product->getUrlModel()->getUrl($product);
    }

    /**
     * Retrieve product image
     *
     * @param \Magento\Quote\Api\Data\CartItemInterface $item
     * @param string $imageId
     * @param array $attributes
     *
     * @return string
     */
    public function getImageUrl($item, $imageId, array $attributes = [])
    {
        $product = $item->getProduct();
        if ($item->getProductType() === Configurable::TYPE_CODE) {
            if ($item->getOptionByCode('simple_product')->getProduct()->getThumbnail() != 'no_selection') {
                $product = $item->getOptionByCode('simple_product')->getProduct();
            }
        }
        $helper = $this->imageHelperFactory->create()
            ->init($product, $imageId, $attributes);

        return $helper->getUrl();
    }

    /**
     * Get if decimals are allowed for product
     *
     * @param \Magento\Quote\Api\Data\CartItemInterface $item
     *
     * @return boolean
     */
    public function getQtyUsesDecimals($item)
    {
        $product = $item->getProduct();

        if ($item->getProductType() === Configurable::TYPE_CODE) {
            $is_qty_decimal = $item->getOptionByCode('simple_product')->getProduct()->getExtensionAttributes()->getStockItem()->getIsQtyDecimal();
        } else {
            $is_qty_decimal = $product->getExtensionAttributes()->getStockItem()->getData('is_qty_decimal');
        }

        return $is_qty_decimal;
    }

    /**
     * Get min sale qty for product
     *
     * @param \Magento\Quote\Api\Data\CartItemInterface $item
     *
     * @return float
     */
    public function getMinSaleQty($item)
    {
        $product = $item->getProduct();

        if ($item->getProductType() === Configurable::TYPE_CODE) {
            $minQty = $item->getOptionByCode('simple_product')->getProduct()->getExtensionAttributes()->getStockItem()->getMinSaleQty();
        } else {
            $minQty = $product->getExtensionAttributes()->getStockItem()->getMinSaleQty();
        }

        return $minQty;
    }

    /**
     * Get max sale qty for product
     *
     * @param \Magento\Quote\Api\Data\CartItemInterface $item
     *
     * @return float
     */
    public function getMaxSaleQty($item)
    {
        $product = $item->getProduct();

        if ($item->getProductType() === Configurable::TYPE_CODE) {
            $maxQty = $item->getOptionByCode('simple_product')->getProduct()->getExtensionAttributes()->getStockItem()->getMaxSaleQty();
        } else {
            $maxQty = $product->getExtensionAttributes()->getStockItem()->getMaxSaleQty();
        }

        return $maxQty;
    }

    /**
     * Get qty increments for product
     *
     * @param \Magento\Quote\Api\Data\CartItemInterface $item
     *
     * @return float
     */
    public function getQtyIncrements($item)
    {
        $product = $item->getProduct();

        if ($item->getProductType() === Configurable::TYPE_CODE) {
            $qtyIncrements = $item->getOptionByCode('simple_product')->getProduct()->getExtensionAttributes()->getStockItem()->getQtyIncrements();
        } else {
            $qtyIncrements = $product->getExtensionAttributes()->getStockItem()->getQtyIncrements();
        }

        return $qtyIncrements;
    }
}
