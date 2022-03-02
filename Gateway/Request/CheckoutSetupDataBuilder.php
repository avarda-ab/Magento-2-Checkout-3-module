<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Gateway\Request;

use Avarda\Checkout3\Gateway\Config\Config;
use Magento\Framework\Locale\Resolver;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class CheckoutSetupDataBuilder implements BuilderInterface
{
    /** @var Resolver */
    protected $localeResolver;

    /** @var Config */
    protected $configHelper;

    public function __construct(
        Resolver $localeResolver,
        Config $configHelper
    ) {
        $this->localeResolver = $localeResolver;
        $this->configHelper = $configHelper;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder();

        return [
            'checkoutSetup' => [
                'language'                  => $this->getLanguage(),
                'mode'                      => 'B2C',
                'completedNotificationUrl'  => $this->configHelper->getNotificationUrl(),
                'differentDeliveryAddress'  => $this->showDeliveryAddress($order),
                'enableB2BLink'             => $this->configHelper->getShowB2Blink(),
                'enableCountrySelector'     => $this->configHelper->getCountrySelector(),
            ]
        ];
    }

    public function getLanguage(): int
    {
        $recognizedLocales = [
            "en" => 0,
            "sv" => 1,
            "fi" => 2,
            "no" => 3,
            "et" => 4,
            "dk" => 5,
            "cz" => 6,
            "lt" => 7,
            "sk" => 9,
            "pl" => 10,
        ];

        $localeCode = $this->localeResolver->getLocale();
        $parts = explode('_', $localeCode);
        $firstPart = reset($parts);
        if (in_array($firstPart, array_keys($recognizedLocales))) {
            return $recognizedLocales[$firstPart];
        }

        // Use english as default
        return 0;
    }

    protected function showDeliveryAddress(OrderAdapterInterface $order): string
    {
        $isVirtual = true;
        $countItems = 0;
        foreach ($order->getItems() as $item) {
            /* @var $item \Magento\Quote\Model\Quote\Item */
            if ($item->isDeleted() || $item->getParentItemId()) {
                continue;
            }
            $countItems++;
            if (!$item->getIsVirtual()) {
                $isVirtual = false;
                break;
            }
        }
        $isVirtual = $countItems == 0 ? false : $isVirtual;

        return $isVirtual ? 'Hidden' : 'Unchecked';
    }
}
