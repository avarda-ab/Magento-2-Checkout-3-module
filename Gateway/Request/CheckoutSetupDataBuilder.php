<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Gateway\Request;

use Avarda\Checkout3\Gateway\Config\Config;
use Avarda\Checkout3\Helper\AvardaCheckBoxTypeValues;
use Avarda\Checkout3\Model\Data\AddressBuilder;
use Magento\Framework\Locale\Resolver;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Gateway\Data\AddressAdapterInterface;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Quote\Model\Quote\Item;

class CheckoutSetupDataBuilder implements BuilderInterface
{
    protected Resolver $localeResolver;
    protected ConfigInterface $configHelper;
    protected AddressBuilder $addressBuilder;

    public function __construct(
        Resolver $localeResolver,
        ConfigInterface $configHelper,
        AddressBuilder $addressBuilder,
    ) {
        $this->localeResolver = $localeResolver;
        $this->configHelper = $configHelper;
        $this->addressBuilder = $addressBuilder;
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
                'emailNewsletterSubscription' => $this->getNewsletterSubscription(),
                'ShowThankYouPage'          => false
            ]
        ];
    }

    public function getLanguage(): int
    {
        $recognizedLocales = [
            "en" => 0,  // English
            "sv" => 1,  // Swedish
            "fi" => 2,  // Finnish
            "no" => 3,  // Norwegian
            "et" => 4,  // Estonian
            "dk" => 5,  // Danish
            "cz" => 6,  // Czech
            "lt" => 7,  // Latvian
            "sk" => 9,  // Slovak
            "pl" => 10, // Polish
            "de" => 11, // German
            "at" => 12, // Austrian
            "ru" => 13, // Russian
            "es" => 14, // Spanish
            "it" => 15, // Italian
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
            /* @var $item Item */
            if ($item->isDeleted() || $item->getParentItemId()) {
                continue;
            }
            $countItems++;
            if (!$item->getIsVirtual()) {
                $isVirtual = false;
                break;
            }
        }
        $isVirtual = !($countItems == 0) && $isVirtual;

        if ($isVirtual) {
            return AvardaCheckBoxTypeValues::VALUE_HIDDEN;
        } elseif ($this->addressBuilder->isAddressDifferent($order->getBillingAddress(), $order->getShippingAddress())) {
            return AvardaCheckBoxTypeValues::VALUE_CHECKED;
        } else {
            return AvardaCheckBoxTypeValues::VALUE_UNCHECKED;
        }
    }

    protected function getNewsletterSubscription(): string
    {
        if ($this->configHelper->getShowNewsletter()) {
            if ($this->configHelper->getNewsletterSelectedDefault()) {
                return AvardaCheckBoxTypeValues::VALUE_CHECKED;
            } else {
                return AvardaCheckBoxTypeValues::VALUE_UNCHECKED;
            }
        } else {
            return AvardaCheckBoxTypeValues::VALUE_HIDDEN;
        }
    }
}
