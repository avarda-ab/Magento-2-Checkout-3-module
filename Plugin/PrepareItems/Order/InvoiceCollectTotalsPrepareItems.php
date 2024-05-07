<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Plugin\PrepareItems\Order;

use Avarda\Checkout3\Api\ItemStorageInterface;
use Avarda\Checkout3\Gateway\Data\ItemAdapter\ArrayDataItemFactory;
use Avarda\Checkout3\Gateway\Data\ItemAdapter\OrderItemFactory;
use Avarda\Checkout3\Gateway\Data\ItemDataObjectFactory;
use Avarda\Checkout3\Helper\PaymentData;
use Exception;
use Magento\Catalog\Model\Product\Type;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Item;
use Psr\Log\LoggerInterface;

class InvoiceCollectTotalsPrepareItems
{
    protected LoggerInterface $logger;
    protected ItemStorageInterface $itemStorage;
    protected ItemDataObjectFactory $itemDataObjectFactory;
    protected OrderItemFactory $orderItemAdapterFactory;
    protected ArrayDataItemFactory $arrayDataItemAdapterFactory;
    protected PaymentData $paymentDataHelper;
    protected array $collectTotalsFlag = [];

    public function __construct(
        LoggerInterface $logger,
        ItemStorageInterface $itemStorage,
        ItemDataObjectFactory $itemDataObjectFactory,
        OrderItemFactory $orderItemAdapterFactory,
        ArrayDataItemFactory $arrayDataItemAdapterFactory,
        PaymentData $paymentDataHelper
    ) {
        $this->logger = $logger;
        $this->itemStorage = $itemStorage;
        $this->itemDataObjectFactory = $itemDataObjectFactory;
        $this->orderItemAdapterFactory = $orderItemAdapterFactory;
        $this->arrayDataItemAdapterFactory = $arrayDataItemAdapterFactory;
        $this->paymentDataHelper = $paymentDataHelper;
    }

    /**
     * @param InvoiceInterface $subject
     * @param InvoiceInterface $result
     * @return InvoiceInterface
     */
    public function afterCollectTotals(
        InvoiceInterface $subject,
        InvoiceInterface $result
    ) {
        try {
            $payment = $subject->getOrder()->getPayment();
            if (!array_key_exists(md5($subject->toJson()), $this->collectTotalsFlag) &&
                $this->paymentDataHelper->isAvardaPayment($payment)
            ) {
                $this->prepareItemStorage($subject);
                $this->collectTotalsFlag[md5($subject->toJson())] = true;
            }
        } catch (Exception $e) {
            $this->logger->error($e);
        }

        return $result;
    }

    /**
     * Populate the item storage with Avarda items needed for request building
     *
     * @param InvoiceInterface $subject
     */
    public function prepareItemStorage(InvoiceInterface $subject)
    {
        $this->itemStorage->reset();
        $this->prepareItems($subject);
        $this->prepareShipment($subject);
        $this->prepareGiftCards($subject);
    }

    /**
     * Create item data objects from invoice items
     *
     * @param InvoiceInterface|Invoice $subject
     */
    protected function prepareItems(InvoiceInterface $subject)
    {
        $addedBundleProductIds = [];
        /** @var Item $item */
        foreach ($subject->getItems() as $item) {
            $orderItem = $item->getOrderItem();
            if (!$orderItem->getProductId() ||
                (
                    $item->getData('parent_item_id') !== null &&
                    ($item->getData('parent_item_id') && !isset($addedBundleProductIds[$item->getData('parent_item_id')]))
                ) ||
                $item->isDeleted()
            ) {
                continue;
            }
            // if bundle and grouped with dynamic pricing discount amount affects its child product
            if ($orderItem->getChildrenItems() && $orderItem->getProduct()->getPriceType() == '0') {
                $addedBundleProductIds[$item->getId()] = true;
                continue;
            }

            $itemAdapter = $this->orderItemAdapterFactory->create([
                'orderItem' => $orderItem,
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
     * @param InvoiceInterface|Invoice $subject
     */
    protected function prepareShipment(InvoiceInterface $subject)
    {
        $shippingAmount = $subject->getShippingInclTax();
        if ($shippingAmount > 0) {
            $order = $subject->getOrder();
            $itemAdapter = $this->arrayDataItemAdapterFactory->create([
                'data' => [
                    'name' => $order->getShippingDescription(),
                    'sku'  => $order->getShippingMethod(),
                ],
            ]);
            $itemDataObject = $this->itemDataObjectFactory->create(
                $itemAdapter,
                1,
                $shippingAmount,
                $subject->getShippingTaxAmount()
            );

            $this->itemStorage->addItem($itemDataObject);
        }
    }

    /**
     * Create item data object from gift card information
     *
     * @param InvoiceInterface|Invoice $subject
     */
    protected function prepareGiftCards(InvoiceInterface $subject)
    {
        $giftCardsAmount = $subject->getData('gift_cards_amount');
        if ($giftCardsAmount !== null && $giftCardsAmount > 0) {
            $itemAdapter = $this->arrayDataItemAdapterFactory->create([
                'data' => [
                    'name' => __('Gift Card'),
                    'sku'  => __('giftcard'),
                ],
            ]);
            $itemDataObject = $this->itemDataObjectFactory->create(
                $itemAdapter,
                1,
                $giftCardsAmount * -1,
                0
            );

            $this->itemStorage->addItem($itemDataObject);
        }
    }
}
