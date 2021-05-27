<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Gateway\Response;

use Avarda\Checkout3\Api\Data\PaymentDetailsInterface;
use Magento\Framework\Exception\PaymentException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

/**
 * Class InitializePaymentHandler
 */
class InitializePaymentHandler implements HandlerInterface
{
    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        if (!count($response)) {
            throw new PaymentException(__('No purchase ID returned from Avarda'));
        }
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();

        $payment->setAdditionalInformation(PaymentDetailsInterface::PURCHASE_DATA, $response);
    }
}
