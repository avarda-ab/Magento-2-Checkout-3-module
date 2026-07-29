<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

declare(strict_types=1);

namespace Avarda\Checkout3\Gateway\Validator;

use Avarda\Checkout3\Helper\PaymentMethod;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;

class CaptureAmountValidator extends AbstractValidator
{
    public const AMOUNT_THRESHOLD = 0.0001;

    public function validate(array $validationSubject): ResultInterface
    {
        $paymentDataObject = SubjectReader::readPayment($validationSubject);
        $order = $paymentDataObject->getOrder();
        $paymentMethod = (string) $paymentDataObject->getPayment()->getMethodInstance()->getCode();

        // A zero-amount Avarda purchase can only cover a zero order. If the order total
        // is non-zero yet the payment is the Avarda zero-amount method, the capture would
        // succeed against a purchase that holds no money, marking the order falsely paid.
        if ((float) $order->getGrandTotalAmount() > self::AMOUNT_THRESHOLD
            && $paymentMethod === PaymentMethod::$codes[PaymentMethod::ZERO_AMOUNT]
        ) {
            return $this->createResult(
                false,
                [
                    __(
                        'Refusing to capture order %1: it is assigned to the Avarda zero-amount '
                        . 'purchase but its total is not zero.',
                        $order->getOrderIncrementId()
                    )
                ]
            );
        }

        return $this->createResult(true);
    }
}
