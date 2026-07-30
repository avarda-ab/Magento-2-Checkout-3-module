<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Plugin\PrepareItems\Quote;

use Avarda\Checkout3\Api\Data\PaymentDetailsInterface;
use Avarda\Checkout3\Api\QuotePaymentManagementInterface;
use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PurchaseState;
use Avarda\Checkout3\Model\QuoteLock;
use Exception;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\InventoryInStorePickupQuote\Model\ResourceModel\GetPickupLocationCodeByQuoteAddressId;
use Magento\InventoryInStorePickupShippingApi\Model\Carrier\InStorePickup;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

class QuoteCollectTotalsUpdateItems
{
    protected QuotePaymentManagementInterface $quotePaymentManagement;
    protected PaymentData $paymentDataHelper;
    protected PurchaseState $purchaseStateHelper;
    protected Http $request;
    protected ConfigInterface $config;
    protected GetPickupLocationCodeByQuoteAddressId $getPickupLocationCodeByQuoteAddressId;
    protected QuoteLock $quoteLock;

    static bool $collectTotalsFlag = false;

    public function __construct(
        QuotePaymentManagementInterface $quotePaymentManagement,
        PaymentData $paymentDataHelper,
        PurchaseState $purchaseStateHelper,
        Http $request,
        ConfigInterface $config,
        GetPickupLocationCodeByQuoteAddressId $getPickupLocationCodeByQuoteAddressId,
        QuoteLock $quoteLock,
    ) {
        $this->quotePaymentManagement = $quotePaymentManagement;
        $this->paymentDataHelper = $paymentDataHelper;
        $this->purchaseStateHelper = $purchaseStateHelper;
        $this->request = $request;
        $this->config = $config;
        $this->getPickupLocationCodeByQuoteAddressId = $getPickupLocationCodeByQuoteAddressId;
        $this->quoteLock = $quoteLock;
    }

    /**
     * Collect totals is triggered when quote is updated in any way, making it a
     * safe function to utilize and guarantee item updates to Avarda.
     *
     * @param CartInterface|Quote $subject
     * @param CartInterface|Quote $result
     *
     * @return CartInterface
     */
    public function afterCollectTotals(CartInterface $subject, CartInterface $result)
    {
        if (!$this->config->isActive()) {
            return $result;
        }

        $payment = $subject->getPayment();
        if (!self::$collectTotalsFlag &&
            $subject->getItemsCount() > 0 &&
            $subject->getItems() !== null &&
            $this->paymentDataHelper->isAvardaPayment($payment)
        ) {
            // avoid infinite loops, because the calls here might call also collectTotals
            self::$collectTotalsFlag = true;
            $renew = false;
            // Avarda rejects concurrent modifications of the same purchase
            $locked = $this->quoteLock->lock($subject->getId());
            try {
                // Update payment status to determine if session is outdated and needs to be initialized
                $this->quotePaymentManagement->updateOnlyPaymentStatus($subject);

                $state = $this->getState($subject);
                if ($this->purchaseStateHelper->isComplete($state)) {
                    // A completed purchase holds a fixed amount at Avarda. If the cart total
                    // no longer matches what was last synced (e.g. a fully gift-card-covered
                    // cart auto-completed as ZeroAmount and then grew), reusing it would place
                    // the order at the wrong amount, so renew it instead of returning early.
                    if (!$this->paymentDataHelper->syncedTotalMismatches($subject)) {
                        return $result;
                    }
                    $renew = true;
                } elseif (($renew = $this->purchaseStateHelper->isDead($state)) === false) {
                    try {
                        $this->updateItemsWithRetry($subject);
                        if ($this->shouldResendPickupAddress($subject)) {
                            $this->quotePaymentManagement->updateDeliveryAddress($subject);
                        }
                    } catch (Exception $e) {
                        // Purchase is alive per the status update, the item sync retries on the next totals collection
                    }
                }
            } catch (Exception $e) {
                // Renewing on a transient failure would desync an already mounted checkout form
            } finally {
                self::$collectTotalsFlag = false;
                if ($locked) {
                    $this->quoteLock->unlock($subject->getId());
                }
            }
            if ($renew) {
                // Initializing here would race with the payment endpoint, so only flag for renewal
                $this->requestRenew($payment);
            }
        }

        return $result;
    }

    /**
     * One retry handles Avarda rejecting a collision with another scope, such as the customer's own form session
     *
     * @throws WebapiException
     */
    protected function updateItemsWithRetry(CartInterface $subject)
    {
        try {
            $this->quotePaymentManagement->updateItems($subject);
        } catch (WebapiException $e) {
            $this->quotePaymentManagement->updateItems($subject);
        }
    }

    /**
     * @param InfoInterface $payment
     */
    protected function requestRenew($payment)
    {
        $purchaseData = $this->paymentDataHelper->getPurchaseData($payment) ?: [];
        if (!empty($purchaseData['renew'])) {
            return;
        }
        $purchaseData['renew'] = true;
        $payment->setAdditionalInformation(PaymentDetailsInterface::PURCHASE_DATA, $purchaseData);
        try {
            $payment->save();
        } catch (Exception $e) {
            // The flag is still persisted when the surrounding operation saves the quote
        }
    }

    public function shouldResendPickupAddress(CartInterface $subject): bool
    {
        $shippingAddress = $subject->getShippingAddress();
        if (!$shippingAddress) {
            return false;
        }

        $currentIsPickup = $shippingAddress->getShippingMethod() === InStorePickup::DELIVERY_METHOD;
        $origIsPickup = $shippingAddress->getOrigData('shipping_method') === InStorePickup::DELIVERY_METHOD;

        if ($currentIsPickup !== $origIsPickup) {
            return true;
        }

        return $currentIsPickup && $this->pickupLocationChanged($shippingAddress);
    }

    public function pickupLocationChanged($shippingAddress): bool
    {
        $extensionAttributes = $shippingAddress->getExtensionAttributes();
        $currentCode = $extensionAttributes ? $extensionAttributes->getPickupLocationCode() : null;

        $addressId = (int)$shippingAddress->getId();
        $savedCode = $addressId ? $this->getPickupLocationCodeByQuoteAddressId->execute($addressId) : null;

        return $currentCode !== $savedCode;
    }

    /**
     * Get state based on payment object
     *
     * @param CartInterface|Quote $subject
     *
     * @return string
     * @throws PaymentException
     *
     */
    protected function getState(CartInterface $subject)
    {
        $payment = $subject->getPayment();
        $state = $this->paymentDataHelper->getState($payment);
        if (!$this->purchaseStateHelper->isInCheckout($state)) {
            if ($this->purchaseStateHelper->isWaiting($state)) {
                throw new PaymentException(
                    __('Avarda is processing the purchase, unable to update items.')
                );
            }
        }

        return $state;
    }
}
