<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Gateway\Request;

use Avarda\Checkout3\Helper\PaymentData;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Class TranIdDataBuilder
 */
class TranIdDataBuilder implements BuilderInterface
{
    /**
     * A unique transaction ID
     */
    const TRAN_ID = 'tranId';

    protected PaymentData $paymentDataHelper;

    public function __construct(
        PaymentData $paymentDataHelper
    ) {
        $this->paymentDataHelper = $paymentDataHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function build(array $buildSubject)
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $transactionId = $this->paymentDataHelper->getTransactionId();
        $paymentDO->getPayment()->setTransactionId($transactionId);

        return [self::TRAN_ID => $transactionId];
    }
}
