<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

declare(strict_types=1);

namespace Avarda\Checkout3\Test\Unit\Plugin\Checkout;

use Avarda\Checkout3\Plugin\Checkout\ShippingAddressDataPlugin;
use Avarda\Checkout3\Test\Unit\AddressExtensionStub;
use Avarda\Checkout3\Test\Unit\AddressStub;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ShippingAddressDataPluginTest extends TestCase
{
    protected const STORE_STREET = 'Valtakatu 34';
    protected const STORE_POSTCODE = '53100';

    protected ShippingAddressDataPlugin $plugin;

    protected CartRepositoryInterface|MockObject $cartRepositoryMock;

    protected ShippingInformationManagement|MockObject $subjectMock;

    protected function setUp(): void
    {
        $this->cartRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->subjectMock = $this->createMock(ShippingInformationManagement::class);
        $this->plugin = new ShippingAddressDataPlugin($this->cartRepositoryMock);
    }

    public function testPickupAddressIsReplacedWithBillingWhenLeavingPickup(): void
    {
        $payloadExtension = $this->createExtension('071');
        $payload = $this->createPayloadAddress(self::STORE_STREET, self::STORE_POSTCODE, $payloadExtension);
        $billing = $this->createBillingAddress('Testikatu 1');
        $this->createQuote('instore_pickup', '071', $billing);

        $payload->expects($this->once())->method('setStreet')->with(['Testikatu 1']);
        $payload->expects($this->once())->method('setCity')->with('Imatra');
        $payload->expects($this->once())->method('setCountryId')->with('FI');
        $payload->expects($this->once())->method('setCustomerAddressId')->with(null);
        $payloadExtension->expects($this->once())->method('setPickupLocationCode')->with(null);

        $this->plugin->beforeSaveAddressInformation(
            $this->subjectMock,
            1,
            $this->createInformation($payload, 'matkahuolto', '80', $billing),
        );
    }

    public function testBlankBillingKeepsPostcodeAndClearsStreet(): void
    {
        $payload = $this->createPayloadAddress(self::STORE_STREET, self::STORE_POSTCODE, $this->createExtension('071'));
        $this->createQuote('instore_pickup', '071', $this->createBillingAddress(''));

        $payload->expects($this->once())->method('setStreet')->with(['']);
        $payload->expects($this->never())->method('setPostcode');
        $payload->expects($this->never())->method('setCity');

        $this->plugin->beforeSaveAddressInformation(
            $this->subjectMock,
            1,
            $this->createInformation($payload, 'matkahuolto', '80', $this->createBillingAddress('')),
        );
    }

    public function testNewAddressChosenWhileLeavingPickupIsKept(): void
    {
        $payload = $this->createPayloadAddress('Uusikatu 5', '55100', $this->createExtension(null));
        $this->createQuote('instore_pickup', '071', $this->createBillingAddress('Testikatu 1'));

        $payload->expects($this->never())->method('setStreet');

        $this->plugin->beforeSaveAddressInformation(
            $this->subjectMock,
            1,
            $this->createInformation($payload, 'matkahuolto', '80', $this->createBillingAddress('Testikatu 1')),
        );
    }

    public function testAddressIsNotTouchedWhenChoosingPickup(): void
    {
        $payload = $this->createPayloadAddress(self::STORE_STREET, self::STORE_POSTCODE, $this->createExtension('071'));
        $this->createQuote('instore_pickup', '071', $this->createBillingAddress('Testikatu 1'));

        $payload->expects($this->never())->method('setStreet');

        $this->plugin->beforeSaveAddressInformation(
            $this->subjectMock,
            1,
            $this->createInformation($payload, 'instore', 'pickup', $this->createBillingAddress('Testikatu 1')),
        );
    }

    public function testAddressIsNotTouchedWhenQuoteWasNeverOnPickup(): void
    {
        $payload = $this->createPayloadAddress('Uusikatu 5', '55100', $this->createExtension(null));
        $this->createQuote('matkahuolto_80', null, $this->createBillingAddress('Testikatu 1'));

        $payload->expects($this->never())->method('setStreet');

        $this->plugin->beforeSaveAddressInformation(
            $this->subjectMock,
            1,
            $this->createInformation($payload, 'matkahuolto', '80', $this->createBillingAddress('Testikatu 1')),
        );
    }

    protected function createExtension(?string $pickupLocationCode): AddressExtensionStub|MockObject
    {
        $extension = $this->getMockBuilder(AddressExtensionStub::class)
            ->onlyMethods(['getPickupLocationCode', 'setPickupLocationCode'])
            ->getMock();
        $extension->method('getPickupLocationCode')->willReturn($pickupLocationCode);

        return $extension;
    }

    protected function createPayloadAddress(
        string $street,
        string $postcode,
        AddressExtensionStub $extension,
    ): AddressInterface|MockObject {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getStreet')->willReturn([$street]);
        $address->method('getPostcode')->willReturn($postcode);
        $address->method('getExtensionAttributes')->willReturn($extension);

        return $address;
    }

    protected function createBillingAddress(string $street): AddressInterface|MockObject
    {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getStreet')->willReturn([$street]);
        $address->method('getFirstname')->willReturn('Testi');
        $address->method('getLastname')->willReturn('Asiakas');
        $address->method('getCity')->willReturn('Imatra');
        $address->method('getPostcode')->willReturn('55100');
        $address->method('getCountryId')->willReturn('FI');

        return $address;
    }

    protected function createQuote(
        string $shippingMethod,
        ?string $pickupLocationCode,
        AddressInterface $billing,
    ): Quote|MockObject {
        $quoteAddress = $this->getMockBuilder(AddressStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getExtensionAttributes', 'getStreet', 'getPostcode'])
            ->getMock();
        $quoteAddress->setData('shipping_method', $shippingMethod);
        $quoteAddress->method('getExtensionAttributes')->willReturn($this->createExtension($pickupLocationCode));
        $quoteAddress->method('getStreet')->willReturn([self::STORE_STREET]);
        $quoteAddress->method('getPostcode')->willReturn(self::STORE_POSTCODE);

        $quote = $this->createMock(Quote::class);
        $quote->method('getShippingAddress')->willReturn($quoteAddress);
        $quote->method('getBillingAddress')->willReturn($billing);
        $this->cartRepositoryMock->method('getActive')->willReturn($quote);

        return $quote;
    }

    protected function createInformation(
        AddressInterface $payload,
        string $carrierCode,
        string $methodCode,
        AddressInterface $billing,
    ): ShippingInformationInterface|MockObject {
        $information = $this->createMock(ShippingInformationInterface::class);
        $information->method('getShippingAddress')->willReturn($payload);
        $information->method('getBillingAddress')->willReturn($billing);
        $information->method('getShippingCarrierCode')->willReturn($carrierCode);
        $information->method('getShippingMethodCode')->willReturn($methodCode);

        return $information;
    }
}
