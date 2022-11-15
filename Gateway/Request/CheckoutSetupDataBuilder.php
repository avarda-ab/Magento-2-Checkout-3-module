<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Gateway\Request;

use Avarda\Checkout3\Gateway\Config\Config;
use Avarda\Checkout3\Helper\AvardaCheckBoxTypeValues;
use Magento\Framework\Locale\Resolver;
use Magento\Payment\Gateway\Data\AddressAdapterInterface;
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
                'emailNewsletterSubscription' => $this->getNewsletterSubscription(),
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
        $isVirtual = !($countItems == 0) && $isVirtual;

        if ($isVirtual) {
            return AvardaCheckBoxTypeValues::VALUE_HIDDEN;
        } elseif ($this->isAddressDifferent($order->getBillingAddress(), $order->getShippingAddress())) {
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

    /**
     * @param $address1 AddressAdapterInterface
     * @param $address2 AddressAdapterInterface
     * @return bool
     */
    protected function isAddressDifferent($address1, $address2): bool
    {
        if (
            $address1->getFirstname() != $address2->getFirstname() ||
            $address1->getLastname() != $address2->getLastname() ||
            $address1->getStreetLine1() != $address2->getStreetLine1() ||
            $address1->getStreetLine2() != $address2->getStreetLine2() ||
            $address1->getCity() != $address2->getCity() ||
            $address1->getPostcode() != $address2->getPostcode() ||
            $address1->getCountryId() != $address2->getCountryId()
        ) {
            return true;
        } else {
            return false;
        }
    }
}
