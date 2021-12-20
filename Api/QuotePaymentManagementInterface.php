<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Api;

use Avarda\Checkout3\Api\Data\ItemDetailsListInterface;
use Magento\Framework\Exception\PaymentException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * Interface for managing Avarda payment information
 * @api
 */
interface QuotePaymentManagementInterface
{
    /**
     * Get purchase ID for quote
     *
     * @param string|int $cartId
     * @param bool   $renew
     * @return string
     */
    public function getPurchaseData($cartId, bool $renew = false);

    /**
     * Make Avarda InitializePurchase call and return purchase ID
     *
     * @param CartInterface|Quote $quote
     * @return string
     */
    public function initializePurchase(CartInterface $quote);

    /**
     * Get quote items additional information not provided by Magento
     *
     * @param string|int $cartId
     * @return ItemDetailsListInterface
     */
    public function getItemDetailsList($cartId);

    /**
     * Make Avarda UpdateItems call and return purchase ID
     *
     * @param CartInterface|Quote $quote
     * @return void
     */
    public function updateItems(CartInterface $quote);

    /**
     * Setting the quote is_active to false hides it from the frontend and
     * renders the customer unable to manipulate the cart while payment is
     * processed.
     *
     * @param string|int  $cartId
     * @param bool $isActive
     * @return void
     */
    public function setQuoteIsActive($cartId, $isActive);

    /**
     * Update order (quote) from Avarda
     *
     * @param string|int $cartId
     * @return void
     */
    public function updatePaymentStatus($cartId);

    /**
     * Update order (quote) payment status from Avarda.
     *
     * @param string $quote
     * @return void
     */
    public function updateOnlyPaymentStatus($quote);

    /**
     * Prepare and save order to Magento.
     *
     * @param string|int $cartId
     * @throws PaymentException
     * @return void
     */
    public function placeOrder($cartId);

    /**
     * Get quote ID by Avarda purchase ID
     *
     * @param string $purchaseId
     * @throws PaymentException
     * @return int
     */
    public function getQuoteIdByPurchaseId($purchaseId);
}
