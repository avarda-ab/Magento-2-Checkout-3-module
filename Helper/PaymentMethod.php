<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   avarda_checkout3_Checkout
 */
namespace Avarda\Checkout3\Helper;

/**
 * Class PaymentMethod
 */
class PaymentMethod
{
    const INVOICE = "Invoice";
    const LOAN = "Loan";
    const CARD = "Card";
    const DIRECT_PAYMENT = "DirectPayment";
    const PART_PAYMENT = "PartPayment";
    const SWISH = "Swish";
    const HIGH_AMOUNT_LOAN = "HighAmountLoan";
    const PAYPAL = "PayPal";
    const PAY_ON_DELIVERY = "PayOnDelivery";
    const B2B_INVOICE = "B2BInvoice";
    const DIRECT_INVOICE = "DirectInvoice";
    const MASTERPASS = "Masterpass";
    const MOBILE_PAY = "MobilePay";
    const VIPPS = "Vipps";
    const ZERO_AMOUNT = "ZeroAmount";
    const UNKNOWN = "Unknown";

    /**
     * PaymentMethod payment codes
     *
     * @var array
     */
    public static $codes = [
        self::INVOICE => "avarda_checkout3_invoice",
        self::LOAN => "avarda_checkout3_loan",
        self::CARD => "avarda_checkout3_card",
        self::DIRECT_PAYMENT => "avarda_checkout3_direct_payment",
        self::PART_PAYMENT => "avarda_checkout3_part_payment",
        self::SWISH => "avarda_checkout3_swish",
        self::HIGH_AMOUNT_LOAN => "avarda_checkout3_high_amount_loan",
        self::PAYPAL => "avarda_checkout3_paypal",
        self::PAY_ON_DELIVERY => "avarda_checkout3_pay_on_delivery",
        self::B2B_INVOICE => "avarda_checkout3_b2b_invoice",
        self::DIRECT_INVOICE => "avarda_checkout3_direct_invoice",
        self::MASTERPASS => "avarda_checkout3_masterpass",
        self::MOBILE_PAY => "avarda_checkout3_mobile_pay",
        self::VIPPS => "avarda_checkout3_vipps",
        self::ZERO_AMOUNT => "avarda_checkout3_zero_amount",
    ];

    /**
     * Get payment method code for Magento order
     *
     * @param int $paymentMethod
     * @return string
     */
    public function getPaymentMethod($paymentMethod)
    {
        if (array_key_exists($paymentMethod, self::$codes)) {
            return self::$codes[$paymentMethod];
        }

        return self::$codes[self::UNKNOWN];
    }
}
