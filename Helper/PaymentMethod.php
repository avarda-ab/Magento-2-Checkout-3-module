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
    const string INVOICE = "Invoice";
    const string LOAN = "Loan";
    const string CARD = "Card";
    const string DIRECT_PAYMENT = "DirectPayment";
    const string PART_PAYMENT = "PartPayment";
    const string SWISH = "Swish";
    const string HIGH_AMOUNT_LOAN = "HighAmountLoan";
    const string PAYPAL = "PayPal";
    const string PAY_ON_DELIVERY = "PayOnDelivery";
    const string B2B_INVOICE = "B2BInvoice";
    const string DIRECT_INVOICE = "DirectInvoice";
    const string MASTERPASS = "Masterpass";
    const string MOBILE_PAY = "MobilePay";
    const string VIPPS = "Vipps";
    const string CARD_CHECKOUT = "CardViaCheckoutCom";
    const string APPLE_PAY = "ApplePayViaCheckoutCom";
    const string GOOGLE_PAY = "GooglePayViaCheckoutCom";
    const string ZERO_AMOUNT = "ZeroAmount";
    const string UNKNOWN = "Unknown";

    /**
     * PaymentMethod payment codes
     *
     * @var array
     */
    public static array $codes = [
        self::INVOICE          => "avarda_checkout3_invoice",
        self::LOAN             => "avarda_checkout3_loan",
        self::CARD             => "avarda_checkout3_card",
        self::DIRECT_PAYMENT   => "avarda_checkout3_direct_payment",
        self::PART_PAYMENT     => "avarda_checkout3_part_payment",
        self::SWISH            => "avarda_checkout3_swish",
        self::HIGH_AMOUNT_LOAN => "avarda_checkout3_high_amount_loan",
        self::PAYPAL           => "avarda_checkout3_paypal",
        self::PAY_ON_DELIVERY  => "avarda_checkout3_pay_on_delivery",
        self::B2B_INVOICE      => "avarda_checkout3_b2b_invoice",
        self::DIRECT_INVOICE   => "avarda_checkout3_direct_invoice",
        self::MASTERPASS       => "avarda_checkout3_masterpass",
        self::MOBILE_PAY       => "avarda_checkout3_mobile_pay",
        self::VIPPS            => "avarda_checkout3_vipps",
        self::CARD_CHECKOUT    => "avarda_checkout3_card_checkout",
        self::APPLE_PAY        => "avarda_checkout3_apple_pay",
        self::GOOGLE_PAY       => "avarda_checkout3_google_pay",
        self::ZERO_AMOUNT      => "avarda_checkout3_zero_amount",
        self::UNKNOWN          => "avarda_checkout3_unknown",
    ];

    /**
     * Get payment method code for Magento order
     *
     * @param string $paymentMethod
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
