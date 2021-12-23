<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Gateway\Request;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\ScopeInterface;

class B2cDataBuilder implements BuilderInterface
{
    /** The first name value must be less than or equal to 40 characters. */
    const FIRST_NAME = 'firstName';

    /** The last name value must be less than or equal to 40 characters. */
    const LAST_NAME = 'lastName';

    /** The street address line 1. Maximum 40 characters. */
    const STREET_1 = 'address1';

    /** The street address line 2. Maximum 40 characters. */
    const STREET_2 = 'address2';

    /** The Zip/Postal code. Maximum 6 characters. */
    const ZIP = 'zip';

    /** The locality/city. 30 character maximum. */
    const CITY = 'city';

    /** country */
    const COUNTRY = 'country';

    /** @var Session */
    protected $customerSession;

    /** @var ScopeConfigInterface */
    protected $config;

    public function __construct(
        Session $customerSession,
        ScopeConfigInterface $config
    ) {
        $this->customerSession = $customerSession;
        $this->config = $config;
    }

    public function build(array $buildSubject)
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder();

        return [
            "b2C" => [
                "customerToken" => $this->getCustomerToken(),
                "invoicingAddress" => $this->getBillingAddress($order),
                "deliveryAddress" => $this->getShippingAddress($order),
                "userInputs" => [
                    "phone" => $order->getBillingAddress()->getTelephone(),
                    "email" => $order->getBillingAddress()->getEmail()
                ]
            ]
        ];
    }

    /**
     * @param OrderAdapterInterface $order
     * @return array
     */
    protected function getBillingAddress(OrderAdapterInterface $order)
    {
        $address = $order->getBillingAddress();
        if ($address === null) {
            return [];
        }

        return [
            self::FIRST_NAME => $address->getFirstname(),
            self::LAST_NAME  => $address->getLastname(),
            self::STREET_1   => $address->getStreetLine1(),
            self::STREET_2   => $address->getStreetLine2(),
            self::ZIP        => $address->getPostcode(),
            self::CITY       => $address->getCity(),
            self::COUNTRY    => $address->getCountryId() ?: $this->getDefaultCountry($order),
        ];
    }

    /**
     * @param OrderAdapterInterface $order
     * @return array
     */
    protected function getShippingAddress(OrderAdapterInterface $order)
    {
        $address = $order->getShippingAddress();
        if ($address === null) {
            // If it's virtual order it doesn't have shipping address
            return $this->getBillingAddress($order);
        }

        return [
            self::FIRST_NAME => $address->getFirstname(),
            self::LAST_NAME  => $address->getLastname(),
            self::STREET_1   => $address->getStreetLine1(),
            self::STREET_2   => $address->getStreetLine2(),
            self::ZIP        => $address->getPostcode(),
            self::CITY       => $address->getCity(),
            self::COUNTRY    => $address->getCountryId() ?: $this->getDefaultCountry($order),
        ];
    }

    protected function getCustomerToken()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return '';
        }

        $customerToken = $this->customerSession
            ->getCustomerData()
            ->getCustomAttribute('avarda_customer_token');

        if ($customerToken === null || $customerToken->getValue() === null) {
            return '';
        }

        return $customerToken->getValue();
    }

    /**
     * @param $order OrderAdapterInterface
     * @return string
     */
    protected function getDefaultCountry($order)
    {
        return $this->config->getValue(
            'general/country/default',
            ScopeInterface::SCOPE_STORE,
            $order->getStoreId()
        );
    }
}
