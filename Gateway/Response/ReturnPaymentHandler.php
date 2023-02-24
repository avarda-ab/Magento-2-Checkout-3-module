<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Gateway\Response;

use Avarda\Checkout3\Helper\PaymentData;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment\Transaction;

class ReturnPaymentHandler implements HandlerInterface
{
    protected PaymentData $paymentDataHelper;

    public function __construct(
        PaymentData $paymentDataHelper
    ) {
        $this->paymentDataHelper = $paymentDataHelper;
    }

    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response = null)
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();
        $order = $payment->getOrder();

        /** @var Transaction $authTransaction */
        $authTransaction = $payment->getAuthorizationTransaction();
        $transactionId = $this->paymentDataHelper->getTransactionId();
        $payment->setBaseAmountCanceled($order->getBaseTotalDue());
        $payment->setAmountCanceled($order->getTotalDue());
        $payment->setTransactionId($transactionId);
        $payment->setIsTransactionClosed(1);
        $payment->setParentTransactionId($authTransaction->getTxnId());
        $payment->addTransaction(Transaction::TYPE_VOID);
        $authTransaction->closeAuthorization();
    }
}
