<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Plugin\PrepareItems\Quote;

use Avarda\Checkout3\Api\ItemStorageInterface;
use Avarda\Checkout3\Gateway\Data\ItemAdapter\ArrayDataItemFactory;
use Avarda\Checkout3\Gateway\Data\ItemAdapter\QuoteItemFactory;
use Avarda\Checkout3\Gateway\Data\ItemDataObjectFactory;
use Avarda\Checkout3\Helper\PaymentData;
use Exception;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Psr\Log\LoggerInterface;

class QuoteCollectTotalsPrepareItems
{
    protected LoggerInterface $logger;
    protected ItemStorageInterface $itemStorage;
    protected ItemDataObjectFactory $itemDataObjectFactory;
    protected QuoteItemFactory $quoteItemAdapterFactory;
    protected ArrayDataItemFactory $arrayDataItemAdapterFactory;
    protected PaymentData $paymentDataHelper;
    protected ConfigInterface $config;

    protected bool $collectTotalsFlag = false;

    public function __construct(
        LoggerInterface $logger,
        ItemStorageInterface $itemStorage,
        ItemDataObjectFactory $itemDataObjectFactory,
        QuoteItemFactory $quoteItemAdapterFactory,
        ArrayDataItemFactory $arrayDataItemAdapterFactory,
        PaymentData $paymentDataHelper,
        ConfigInterface $config
    ) {
        $this->logger = $logger;
        $this->itemStorage = $itemStorage;
        $this->itemDataObjectFactory = $itemDataObjectFactory;
        $this->quoteItemAdapterFactory = $quoteItemAdapterFactory;
        $this->arrayDataItemAdapterFactory = $arrayDataItemAdapterFactory;
        $this->paymentDataHelper = $paymentDataHelper;
        $this->config = $config;
    }

    /**
     * @param CartInterface $subject
     * @param CartInterface $result
     * @return CartInterface
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCollectTotals(CartInterface $subject, CartInterface $result)
    {
        if (!$this->config->isActive()) {
            return $result;
        }

        try {
            if (!$this->collectTotalsFlag &&
                count($subject->getAllVisibleItems()) > 0 &&
                $subject->getAllVisibleItems() !== null
            ) {
                $this->prepareItemStorage($subject);
                $this->collectTotalsFlag = true;
            }
        } catch (Exception $e) {
            $this->logger->error($e);
        }

        return $result;
    }

    /**
     * Populate the item storage with Avarda items needed for request building
     *
     * @param CartInterface $subject
     *
     * @return void
     */
    public function prepareItemStorage(CartInterface $subject)
    {
        $this->itemStorage->reset();
        $this->prepareItems($subject);
        $this->prepareShipment($subject);
        $this->prepareGiftCards($subject);
    }

    /**
     * Create item data objects from quote items
     *
     * @param CartInterface|Quote $subject
     *
     * @return void
     */
    public function prepareItems(CartInterface $subject)
    {
        $addedBundleProductIds = [];
        /** @var Item $item */
        foreach ($subject->getItemsCollection() as $item) {
            if ($item->getData('product_id') === null ||
                (
                    $item->getData('parent_item_id') !== null &&
                    ($item->getData('parent_item_id') && !isset($addedBundleProductIds[$item->getData('parent_item_id')]))
                ) ||
                $item->isDeleted()
            ) {
                continue;
            }
            // if bundle and grouped with dynamic pricing discount amount affects its child product
            if ($item->getChildren() && $item->getProduct()->getPriceType() == '0') {
                $addedBundleProductIds[$item->getId()] = true;
                continue;
            }

            $itemAdapter = $this->quoteItemAdapterFactory->create([
                'quoteItem' => $item
            ]);
            $itemDataObject = $this->itemDataObjectFactory->create(
                $itemAdapter,
                $item->getQty(),
                $item->getRowTotalInclTax() -
                    $item->getDiscountAmount(),
                $item->getTaxAmount() +
                    $item->getDiscountTaxCompensationAmount() +
                    $item->getWeeeTaxAppliedAmount()
            );

            $this->itemStorage->addItem($itemDataObject);
        }
    }

    /**
     * Create item data object from shipment information
     *
     * @param CartInterface|Quote $subject
     *
     * @return void
     */
    public function prepareShipment(CartInterface $subject)
    {
        if ($subject->isVirtual()) {
            return;
        }

        $shippingAddress = $subject->getShippingAddress();
        if ($shippingAddress && $shippingAddress->getShippingInclTax() > 0) {
            $itemAdapter = $this->arrayDataItemAdapterFactory->create([
                'data' => [
                    'name' => $shippingAddress->getShippingDescription(),
                    'sku'  => $shippingAddress->getShippingMethod(),
                ],
            ]);
            $itemDataObject = $this->itemDataObjectFactory->create(
                $itemAdapter,
                1,
                $shippingAddress->getShippingInclTax() - $shippingAddress->getShippingDiscountAmount(),
                $shippingAddress->getShippingTaxAmount()
            );

            $this->itemStorage->addItem($itemDataObject);
        }
    }

    /**
     * Create item data object from gift card information
     *
     * @param CartInterface|Quote $subject
     *
     * @return void
     */
    public function prepareGiftCards(CartInterface $subject)
    {
        $giftCardsAmountUsed = $subject->getData('gift_cards_amount_used');
        if ($giftCardsAmountUsed !== null && $giftCardsAmountUsed > 0) {
            $itemAdapter = $this->arrayDataItemAdapterFactory->create([
                'data' => [
                    'name' => __('Gift Card'),
                    'sku'  => __('giftcard'),
                ],
            ]);
            $itemDataObject = $this->itemDataObjectFactory->create(
                $itemAdapter,
                1,
                $giftCardsAmountUsed * -1,
                0
            );

            $this->itemStorage->addItem($itemDataObject);
        }
    }
}
