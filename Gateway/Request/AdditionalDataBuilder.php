<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Gateway\Request;

use Avarda\Checkout3\Model\AlternativeApi;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class AdditionalDataBuilder implements BuilderInterface
{
    /**
     * These data for GatewayClient to build the url correctly
     */
    public const ADDITIONAL = 'additional';

    protected AlternativeApi $alternativeApi;

    public function __construct(
        AlternativeApi $alternativeApi
    ) {
        $this->alternativeApi = $alternativeApi;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject)
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $purchaseData = $payment->getAdditionalInformation('purchase_data');

        return [
            self::ADDITIONAL => [
                'purchaseid' => $purchaseData['purchaseId'] ?? '',
                'storeId' => $paymentDO->getOrder()->getStoreId(),
                'useAltApi' => $this->getUseAlternative($paymentDO->getOrder())
            ]
        ];
    }

    public function getUseAlternative(OrderAdapterInterface $order)
    {
        return $this->alternativeApi->isUsedForOrder($order);
    }
}
