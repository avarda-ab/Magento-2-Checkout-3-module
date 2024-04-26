<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Gateway\Request;

use Avarda\Checkout3\Gateway\Config\Config;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\Quote\QuoteAdapter;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Quote\Api\Data\CartInterfaceFactory;

/**
 * Class OrderReferenceDataBuilder
 */
class AdditionalDataBuilder implements BuilderInterface
{
    /**
     * These data for GatewayClient to build the url correctly
     */
    const ADDITIONAL = 'additional';

    private Config $config;
    private CartInterfaceFactory $cartInterfaceFactory;

    public function __construct(
        Config $config,
        CartInterfaceFactory $cartInterfaceFactory
    ) {
        $this->config = $config;
        $this->cartInterfaceFactory = $cartInterfaceFactory;
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
        $productTypes = array_filter(explode(',', $this->config->getAlternativeProductTypes() ?: ""));
        if (!$productTypes) {
            return false;
        }

        $items = $order->getItems();
        if (!$items && $order instanceof QuoteAdapter) {
            // If cart is not active cart items will not load,
            // but cartRepository->get calls are cached without items loaded so that doesn't help either and
            // $order is an adapter, so it doesn't have getItemsCollection method
            // thus we load the quote with model so that we get the items loaded properly
            $quote = $this->cartInterfaceFactory->create()->loadByIdWithoutStore($order->getId());
            $items = $quote->getItemsCollection()->getItems();
        }

        foreach ($items as $item) {
            if (in_array($item->getProductType(), $productTypes)) {
                return true;
            }

            if (is_array($item->getChildren())) {
                foreach ($item->getChildren() as $childItem) {
                    if (in_array($childItem->getProductType(), $productTypes)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

}
