<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Api;

use Avarda\Checkout3\Api\Data\ItemDetailsListInterface;
use Avarda\Checkout3\Api\Data\PaymentDetailsInterface;
use Magento\Framework\Exception\PaymentException;

/**
 * Interface for managing Avarda guest payment information
 *
 * @api
 */
interface GuestPaymentManagementInterface
{
    /**
     * Get purchase ID for Avarda payment
     *
     * @param string $cartId
     * @param bool $renew
     * @return PaymentDetailsInterface
     * @throws PaymentException
     */
    public function getPurchaseData($cartId, bool $renew = false);

    /**
     * Freeze the cart before redirected to payment. Return 200 status code if
     * everything is OK.
     *
     * @param string $cartId
     * @return void
     * @throws PaymentException
     */
    public function freezeCart($cartId);

    /**
     * Get quote items additional information not provided by Magento Webapi
     *
     * @param string $cartId
     * @return ItemDetailsListInterface
     * @throws PaymentException
     */
    public function getItemDetailsList($cartId);
}
