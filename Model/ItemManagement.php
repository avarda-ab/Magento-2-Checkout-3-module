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
use Magento\Catalog\Helper\ImageFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Quote\Api\Data\CartItemInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ItemManagement implements ItemManagementInterface
{
    const IMAGE_THUMBNAIL = 'cart_page_product_thumbnail';

    protected ItemStorageInterface $itemStorage;
    protected ItemDetailsInterfaceFactory $itemDetailsFactory;
    protected ItemDetailsListInterfaceFactory $itemDetailsListFactory;
    protected ImageFactory $imageHelperFactory;

    public function __construct(
        ItemStorageInterface $itemStorage,
        ItemDetailsInterfaceFactory $itemDetailsFactory,
        ItemDetailsListInterfaceFactory $itemDetailsListFactory,
        ImageFactory $imageHelperFactory
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
        $itemDetailsList = $this->itemDetailsListFactory->create();
        $items = [];
        foreach ($this->itemStorage->getItems() as $item) {
            $itemDetails = $this->itemDetailsFactory->create();
            $itemDetails->setItemId($item->getItemId());
            $itemDetails->setProductUrl($this->getProductUrl($item));
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
     * @param CartItemInterface $item
     * @return string
     */
    protected function getName($item)
    {
        return $item->getName();
    }

    /**
     * Get options text for item
     *
     * @param CartItemInterface $item
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
     * @param CartItemInterface $item
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
     * @param CartItemInterface $item
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
}
