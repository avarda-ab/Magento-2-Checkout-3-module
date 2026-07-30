<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Observer;

use Avarda\Checkout3\Api\Data\PaymentDetailsInterface;
use Avarda\Checkout3\Gateway\Config\Config;
use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Model\ResourceModel\PaymentQueue;
use InvalidArgumentException;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote\Payment;

/**
 * Restores newer purchase data before save, because a slow concurrent request would
 * otherwise overwrite a purchase another request initialized in the meantime.
 */
class QuotePaymentSaveObserver implements ObserverInterface
{
    protected Config $config;
    protected PaymentQueue $paymentQueueResource;
    protected Json $serializer;

    public function __construct(
        Config $config,
        PaymentQueue $paymentQueueResource,
        Json $serializer
    ) {
        $this->config = $config;
        $this->paymentQueueResource = $paymentQueueResource;
        $this->serializer = $serializer;
    }

    public function execute(Observer $observer)
    {
        /** @var Payment $payment */
        $payment = $observer->getEvent()->getPayment();
        if (!$payment
            || !$payment->getId()
            || $payment->getData('avarda_purchase_resync')
            || !$this->config->isActive()
        ) {
            return;
        }

        $memoryData = $payment->getAdditionalInformation(PaymentDetailsInterface::PURCHASE_DATA);
        $memoryPurchaseId = is_array($memoryData) ? ($memoryData['purchaseId'] ?? null) : null;

        $dbInformation = $this->loadDbAdditionalInformation($payment);
        $dbData = $dbInformation[PaymentDetailsInterface::PURCHASE_DATA] ?? null;
        $dbPurchaseId = is_array($dbData) ? ($dbData['purchaseId'] ?? null) : null;
        if (!$dbPurchaseId || $dbPurchaseId === $memoryPurchaseId) {
            return;
        }

        if ($memoryPurchaseId) {
            $memoryQueueId = (int)$this->paymentQueueResource->getIdByPurchaseId($memoryPurchaseId);
            $dbQueueId = (int)$this->paymentQueueResource->getIdByPurchaseId($dbPurchaseId);
            // Unknown ids cannot be ordered, only restore a provably newer purchase
            if (!$memoryQueueId || !$dbQueueId || $memoryQueueId >= $dbQueueId) {
                return;
            }
        }

        $payment->setAdditionalInformation(PaymentDetailsInterface::PURCHASE_DATA, $dbData);
        if (isset($dbInformation[PaymentData::STATE])) {
            $payment->setAdditionalInformation(PaymentData::STATE, $dbInformation[PaymentData::STATE]);
        }
    }

    protected function loadDbAdditionalInformation(Payment $payment): array
    {
        $resource = $payment->getResource();
        $connection = $resource->getConnection();
        $select = $connection->select()
            ->from($resource->getMainTable(), ['additional_information'])
            ->where('payment_id = ?', $payment->getId());
        $raw = $connection->fetchOne($select);
        if (!$raw) {
            return [];
        }

        try {
            $decoded = $this->serializer->unserialize($raw);
        } catch (InvalidArgumentException $e) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
