<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Gateway\Request;

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

    /**
     * Helper for payment info instances, eg. to generate transaction IDs.
     *
     * @var \Avarda\Checkout3\Helper\PaymentData
     */
    protected $paymentDataHelper;

    /**
     * TranIdDataBuilder constructor.
     *
     * @param \Avarda\Checkout3\Helper\PaymentData $paymentDataHelper
     */
    public function __construct(
        \Avarda\Checkout3\Helper\PaymentData $paymentDataHelper
    ) {
        $this->paymentDataHelper = $paymentDataHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function build(array $buildSubject)
    {
        $paymentDO     = SubjectReader::readPayment($buildSubject);
        $transactionId = $this->paymentDataHelper->getTransactionId();
        $paymentDO->getPayment()->setTransactionId($transactionId);

        return [self::TRAN_ID => $transactionId];
    }
}
