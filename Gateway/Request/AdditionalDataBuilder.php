<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Gateway\Request;

use Avarda\Checkout3\Gateway\Config\Config;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * Class OrderReferenceDataBuilder
 */
class AdditionalDataBuilder implements BuilderInterface
{

    /**
     * These data for GatewayClient to build the url correctly
     */
    const ADDITIONAL = 'additional';

    /** @var Config  */
    private Config $config;

    public function __construct(
        Config $config
    ) {
        $this->config = $config;
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
        $productTypes = explode(',', $this->config->getAlternativeProductTypes() ?: "");

        foreach ($order->getItems() as $item) {
            if (in_array($item->getProductType(), $productTypes)) {
                return true;
            }

            foreach ($item->getChildren() as $childItem) {
                if (in_array($childItem->getProductType(), $productTypes)) {
                    return true;
                }
            }
        }

        return false;
    }

}
