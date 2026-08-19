<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Helper;

use Avarda\Checkout3\Api\Data\PaymentDetailsInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\Free;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class PaymentData
{
    /**
     * Payment additional information field name for state
     */
    const STATE = 'state';

    /**
     * Payment additional information field name for the total last synced to Avarda
     */
    const SYNCED_TOTAL = 'synced_total';

    /**
     * Payment additional information field names for the total and currency Avarda reports for the purchase
     */
    const PURCHASE_TOTAL = 'purchase_total';
    const PURCHASE_CURRENCY = 'purchase_currency';

    /**
     * Get purchase from payment info
     *
     * @param InfoInterface $payment
     * @return string|bool
     */
    public function getPurchaseData(InfoInterface $payment)
    {
        $additionalInformation = $payment->getAdditionalInformation();
        if (is_array($additionalInformation) &&
            array_key_exists(
                PaymentDetailsInterface::PURCHASE_DATA,
                $additionalInformation
            )
        ) {
            return $additionalInformation[PaymentDetailsInterface::PURCHASE_DATA];
        }

        return false;
    }

    public function getPurchaseId(InfoInterface $payment): ?string
    {
        $purchaseData = $this->getPurchaseData($payment);

        return is_array($purchaseData) && !empty($purchaseData['purchaseId'])
            ? (string)$purchaseData['purchaseId']
            : null;
    }

    /**
     * Get state from payment info
     *
     * @param InfoInterface $payment
     * @return string
     */
    public function getState(InfoInterface $payment)
    {
        $additionalInformation = $payment->getAdditionalInformation();
        if (is_array($additionalInformation) &&
            array_key_exists(self::STATE, $additionalInformation)
        ) {
            $state = $additionalInformation[self::STATE];
            if (!in_array($state, PurchaseState::$states)) {
                return PurchaseState::UNKNOWN;
            }

            return $state;
        }

        return PurchaseState::OUTDATED;
    }

    /**
     * Get the total last synced to Avarda from payment info
     *
     * @param InfoInterface $payment
     * @return float|null
     */
    public function getSyncedTotal(InfoInterface $payment)
    {
        $additionalInformation = $payment->getAdditionalInformation();
        if (is_array($additionalInformation) &&
            array_key_exists(self::SYNCED_TOTAL, $additionalInformation) &&
            $additionalInformation[self::SYNCED_TOTAL] !== null
        ) {
            return (float)$additionalInformation[self::SYNCED_TOTAL];
        }

        return null;
    }

    /**
     * Check if the quote grand total no longer matches the amount last synced to Avarda
     *
     * @param CartInterface $quote
     * @return bool
     */
    public function syncedTotalMismatches(CartInterface $quote)
    {
        $payment = $quote->getPayment();
        $syncedTotal = $this->getSyncedTotal($payment);
        if ($syncedTotal !== null) {
            // Compare in the same (store) currency the amount was sent to Avarda in.
            return abs($syncedTotal - (float)$quote->getGrandTotal()) >= 0.0001;
        }

        // Legacy quotes that predate synced_total: fall back to the zero-amount heuristic.
        return $payment->getMethod() === PaymentMethod::$codes[PaymentMethod::ZERO_AMOUNT]
            && $quote->getBaseGrandTotal() >= 0.0001;
    }

    /**
     * Check if payment is an Avarda Checkout3, simply by searching for the purchase and payment method
     *
     * @param InfoInterface|OrderPaymentInterface $payment
     * @return bool
     */
    public function isAvardaPayment(InfoInterface $payment)
    {
        $paymentCode = '';
        try {
            $paymentCode = $payment->getMethod();
        } catch (\Exception $e) {
            // pass
        }

        $purchaseData = $this->getPurchaseData($payment);

        return $purchaseData && count($purchaseData)>=1 && (
            !$paymentCode ||
            strpos($paymentCode, 'avarda_checkout3') !== false ||
            // free method is automatically set in checkout, but it will be changed to avarda zero_sum on status update
            $paymentCode == Free::PAYMENT_METHOD_FREE_CODE
        );
    }

    /**
     * Generate a GUID v4 transaction ID
     *
     * @see http://php.net/manual/en/function.com-create-guid.php
     * @return string
     */
    public function getTransactionId()
    {
        return sprintf(
            '%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(16384, 20479),
            mt_rand(32768, 49151),
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(0, 65535)
        );
    }
}
