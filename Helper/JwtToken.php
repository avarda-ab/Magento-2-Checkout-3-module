<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Helper;

use Avarda\Checkout3\Api\Data\PaymentDetailsInterface;
use Avarda\Checkout3\Api\PaymentQueueRepositoryInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\CartRepositoryInterface;

class JwtToken
{
    protected CommandPoolInterface $commandPool;
    protected PaymentDataObjectFactoryInterface $paymentDataObjectFactory;
    protected PaymentQueueRepositoryInterface $paymentQueueRepository;
    protected CartRepositoryInterface $quoteRepository;

    public function __construct(
        CommandPoolInterface $commandPool,
        PaymentDataObjectFactoryInterface $paymentDataObjectFactory,
        PaymentQueueRepositoryInterface $paymentQueueRepository,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->commandPool = $commandPool;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->paymentQueueRepository = $paymentQueueRepository;
        $this->quoteRepository = $quoteRepository;
    }

    public function getNewJwtToken($purchaseId)
    {
        $arguments = [
            'purchase_id' => $purchaseId,
        ];

        $paymentQueue = $this->paymentQueueRepository->get($purchaseId);
        $quote = $this->quoteRepository->get($paymentQueue->getQuoteId());

        /** @var InfoInterface|null $payment */
        $payment = $quote->getPayment();
        if ($payment !== null && $payment instanceof InfoInterface) {
            $arguments['payment'] = $this->paymentDataObjectFactory
                ->create($payment);
        }

        $this->commandPool
            ->get('avarda_get_jwt_token')
            ->execute($arguments);

        $purchaseData = $payment->getAdditionalInformation(PaymentDetailsInterface::PURCHASE_DATA);

        return $purchaseData['jwt'];
    }
}
