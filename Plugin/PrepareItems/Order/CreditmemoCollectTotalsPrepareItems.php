<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Plugin\PrepareItems\Order;

use Avarda\Checkout3\Api\ItemStorageInterface;
use Avarda\Checkout3\Gateway\Data\ItemDataObjectFactory;
use Avarda\Checkout3\Gateway\Data\ItemAdapter\ArrayDataItemFactory;
use Avarda\Checkout3\Gateway\Data\ItemAdapter\OrderItemFactory;
use Avarda\Checkout3\Helper\PaymentData;
use Exception;
use Magento\Catalog\Model\Product\Type;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Item;
use Psr\Log\LoggerInterface;

class CreditmemoCollectTotalsPrepareItems
{
    /** @var LoggerInterface */
    protected $logger;

    /** @var ItemStorageInterface */
    protected $itemStorage;

    /** @var ItemDataObjectFactory */
    protected $itemDataObjectFactory;

    /** @var OrderItemFactory */
    protected $orderItemAdapterFactory;

    /** @var ArrayDataItemFactory */
    protected $arrayDataItemAdapterFactory;

    /** @var PaymentData */
    protected $paymentDataHelper;

    /** @var bool */
    protected $collectTotalsFlag = false;

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
     * @param CreditmemoInterface $subject
     * @param CreditmemoInterface $result
     * @return CreditmemoInterface
     */
    public function afterCollectTotals(
        CreditmemoInterface $subject,
        CreditmemoInterface $result
    ) {
        try {
            $payment = $subject->getOrder()->getPayment();
            if (!$this->collectTotalsFlag &&
                $this->paymentDataHelper->isAvardaPayment($payment)
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
     * @param CreditmemoInterface $subject
     */
    public function prepareItemStorage(CreditmemoInterface $subject)
    {
        $this->itemStorage->reset();
        $this->prepareItems($subject);
        $this->prepareShipment($subject);
        $this->prepareGiftCards($subject);
    }

    /**
     * Create item data objects from invoice items
     *
     * @param CreditmemoInterface|Creditmemo $subject
     */
    protected function prepareItems(CreditmemoInterface $subject)
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
     * @param CreditmemoInterface|Creditmemo $subject
     */
    protected function prepareShipment(CreditmemoInterface $subject)
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
     * @param CreditmemoInterface|Creditmemo $subject
     */
    protected function prepareGiftCards(CreditmemoInterface $subject)
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
