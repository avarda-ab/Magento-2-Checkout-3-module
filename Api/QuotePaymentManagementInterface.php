<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Api;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Interface for managing Avarda payment information
 *
 * @api
 */
interface QuotePaymentManagementInterface
{
    /**
     * Get the data of the cart's Avarda purchase, initializing a new purchase when needed
     *
     * @param string|int $cartId
     * @param bool $renew
     * @return array
     */
    public function getPurchaseData($cartId, bool $renew = false);

    /**
     * Make Avarda InitializePurchase call and return the data of the new purchase
     *
     * @param \Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote $quote
     * @return array
     */
    public function initializePurchase(CartInterface $quote);

    /**
     * Get quote items additional information not provided by Magento
     *
     * @param string|int $cartId
     * @return \Avarda\Checkout3\Api\Data\ItemDetailsListInterface
     */
    public function getItemDetailsList($cartId);

    /**
     * Make Avarda UpdateItems call
     *
     * @param \Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote $quote
     * @return void
     */
    public function updateItems(CartInterface $quote);

    /**
     * Push current delivery address visibility to Avarda
     *
     * @param \Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote $quote
     * @return void
     */
    public function updateDeliveryAddress(CartInterface $quote);

    /**
     * Setting the quote is_active to false hides it from the frontend and
     * renders the customer unable to manipulate the cart while payment is
     * processed.
     *
     * @param string|int $cartId
     * @param bool $isActive
     * @return void
     */
    public function setQuoteIsActive($cartId, $isActive);

    /**
     * Update order (quote) from Avarda
     *
     * @param \Magento\Quote\Api\Data\CartInterface|string|int $cartId
     * @return void
     */
    public function updatePaymentStatus($cartId);

    /**
     * Update order (quote) payment status from Avarda.
     *
     * @param \Magento\Quote\Api\Data\CartInterface|string|int $quote
     * @return void
     */
    public function updateOnlyPaymentStatus($quote);

    /**
     * Update quote payment status and the total Avarda holds for the purchase.
     *
     * @param \Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote $quote
     * @return void
     */
    public function updatePurchaseStatus(CartInterface $quote);

    /**
     * Update order payment status and info from Avarda.
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return void
     */
    public function updateOrderPaymentStatus($order);

    /**
     * Update order payment status from Avarda.
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return void
     */
    public function updateOnlyOrderPaymentStatus($order);

    /**
     * Get quote ID by Avarda purchase ID
     *
     * @param string $purchaseId
     * @return int
     * @throws \Magento\Framework\Exception\PaymentException
     */
    public function getQuoteIdByPurchaseId($purchaseId);
}
