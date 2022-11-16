<?php

namespace Avarda\Checkout3\Plugin\Checks;

use Avarda\Checkout3\Helper\PaymentMethod;
use Avarda\Checkout3\Model\Ui\ConfigProviderBase;
use Magento\Payment\Model\Checks\ZeroTotal;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\Data\CartInterface;

class AvardaZeroTotal
{
    /**
     * Allow zero_amount and basic checkout payment methods if zero amount order
     *
     * @param $subject ZeroTotal
     * @param $result
     * @param $paymentMethod MethodInterface
     * @param $quote CartInterface
     * @return bool|mixed
     */
    public function afterIsApplicable($subject, $result, $paymentMethod, $quote)
    {
        if (
            $quote->getBaseGrandTotal() < 0.0001 &&
            in_array($paymentMethod->getCode(), [PaymentMethod::$codes[PaymentMethod::ZERO_AMOUNT], ConfigProviderBase::CODE])
        ) {
            return true;
        } else {
            return $result;
        }
    }
}
