<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Gateway\Request;

use Avarda\Checkout3\Gateway\Config\Config;
use Avarda\Checkout3\Model\AlternativeApi;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\Quote\QuoteAdapter;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class AdditionalDataBuilder implements BuilderInterface
{
    /**
     * These data for GatewayClient to build the url correctly
     */
    public const ADDITIONAL = 'additional';

    protected AlternativeApi $alternativeApi;
    protected Config $config;

    public function __construct(
        AlternativeApi $alternativeApi,
        Config $config
    ) {
        $this->alternativeApi = $alternativeApi;
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $purchaseData = $payment->getAdditionalInformation('purchase_data') ?: [];

        return [
            self::ADDITIONAL => [
                'purchaseid' => $purchaseData['purchaseId'] ?? '',
                'storeId' => $paymentDO->getOrder()->getStoreId(),
                'useAltApi' => $this->getUseAlternative($paymentDO->getOrder(), $purchaseData)
            ]
        ];
    }

    public function getUseAlternative(OrderAdapterInterface $order, array $purchaseData = []): bool
    {
        // The shared config keeps the store of the previous request, which in cron can be another order's
        $this->config->setStoreId($order->getStoreId());

        // A cart follows the current rules: if they no longer match its purchase, Avarda rejects the call
        // and the purchase is renewed
        if ($order instanceof QuoteAdapter) {
            return $this->alternativeApi->isUsedForOrder($order);
        }

        // Avarda only accepts the credentials the purchase was created with, whatever the rules are now
        if (isset($purchaseData['useAltApi'])) {
            return (bool)$purchaseData['useAltApi'];
        }

        return $this->alternativeApi->isUsedForUnstampedOrder($order);
    }
}
