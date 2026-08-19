<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

declare(strict_types=1);

namespace Avarda\Checkout3\Gateway\Response;

use Avarda\Checkout3\Helper\PaymentData;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class PurchaseTotalHandler implements HandlerInterface
{
    public function handle(array $handlingSubject, array $response)
    {
        $payment = SubjectReader::readPayment($handlingSubject)->getPayment();

        $payment->setAdditionalInformation(
            PaymentData::PURCHASE_TOTAL,
            isset($response['totalPrice']) ? (float)$response['totalPrice'] : null
        );
        $payment->setAdditionalInformation(
            PaymentData::PURCHASE_CURRENCY,
            $response['checkoutSite']['currencyCode'] ?? null
        );
    }
}
