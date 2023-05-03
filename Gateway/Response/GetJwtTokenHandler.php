<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Gateway\Response;

use Avarda\Checkout3\Api\Data\PaymentDetailsInterface;
use Avarda\Checkout3\Api\PaymentQueueRepositoryInterface;
use Magento\Framework\Exception\PaymentException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

/**
 * Class InitializePaymentHandler
 */
class GetJwtTokenHandler implements HandlerInterface
{
    /** @var PaymentQueueRepositoryInterface */
    protected $paymentQueueRepository;

    public function __construct(
        PaymentQueueRepositoryInterface $paymentQueueRepository
    ) {
        $this->paymentQueueRepository = $paymentQueueRepository;
    }

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

        $purchaseData = $payment->getAdditionalInformation(PaymentDetailsInterface::PURCHASE_DATA);
        $purchaseData['jwt'] = $response['jwt'];
        $payment->setAdditionalInformation(PaymentDetailsInterface::PURCHASE_DATA, $purchaseData);
    }
}
