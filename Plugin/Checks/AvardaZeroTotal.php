<?php

namespace Avarda\Checkout3\Plugin\Checks;

use Avarda\Checkout3\Helper\PaymentMethod;
use Magento\Payment\Model\Checks\ZeroTotal;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\Data\CartInterface;

class AvardaZeroTotal
{
    /**
     * @param $subject ZeroTotal
     * @param $result
     * @param $paymentMethod MethodInterface
     * @param $quote CartInterface
     * @return bool|mixed
     */
    public function afterIsApplicable($subject, $result, $paymentMethod, $quote)
    {
        if ($quote->getBaseGrandTotal() < 0.0001 && $paymentMethod->getCode() == PaymentMethod::$codes[PaymentMethod::ZERO_AMOUNT]) {
            return true;
        } else {
            return $result;
        }
    }
}
