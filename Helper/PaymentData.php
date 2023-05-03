<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Helper;

use Avarda\Checkout3\Api\Data\PaymentDetailsInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\Free;
use Magento\Sales\Api\Data\OrderPaymentInterface;

/**
 * Class PaymentData
 */
class PaymentData
{
    /**
     * Payment additional information field name for state
     */
    const STATE = 'state';

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

        return PurchaseState::INITIALIZED;
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
