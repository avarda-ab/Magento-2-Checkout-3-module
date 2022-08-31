<?php
/**
 * Created by avarda.
 * User: juhni
 * Date: 23.8.2022
 * Time: 14.01
 */

namespace Avarda\Checkout3\Gateway\Response;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order;

class PendingPaymentHandler implements HandlerInterface
{
    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();
        $stateObject = SubjectReader::readStateObject($handlingSubject);

        $payment->getOrder()->setCanSendNewEmailFlag(false);
        $stateObject->setState(Order::STATE_PENDING_PAYMENT);
        $stateObject->setIsNotified(true);
    }
}
