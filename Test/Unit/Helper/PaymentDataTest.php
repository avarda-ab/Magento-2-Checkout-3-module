<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

declare(strict_types=1);

namespace Avarda\Checkout3\Test\Unit\Helper;

use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PaymentMethod;
use Avarda\Checkout3\Test\Unit\AddressStub;
use Avarda\Checkout3\Test\Unit\QuoteStub;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PaymentDataTest extends TestCase
{
    protected PaymentData $paymentData;

    protected function setUp(): void
    {
        $this->paymentData = new PaymentData();
    }

    protected function createQuote(
        array $additionalInformation,
        float $grandTotal,
        float $baseGrandTotal,
        string $method = ''
    ): Quote|MockObject {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturn($additionalInformation);
        $payment->method('getMethod')->willReturn($method);

        $quote = $this->getMockBuilder(QuoteStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment', 'getGrandTotal', 'getBaseGrandTotal'])
            ->getMock();
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('getGrandTotal')->willReturn($grandTotal);
        $quote->method('getBaseGrandTotal')->willReturn($baseGrandTotal);

        return $quote;
    }

    public function testGetSyncedTotalReturnsStoredValue(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturn([PaymentData::SYNCED_TOTAL => '23.95']);

        $this->assertSame(23.95, $this->paymentData->getSyncedTotal($payment));
    }

    /**
     * @dataProvider missingValueProvider
     */
    #[DataProvider('missingValueProvider')]
    public function testGetSyncedTotalReturnsNullWhenValueMissing(array $additionalInformation): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturn($additionalInformation);

        $this->assertNull($this->paymentData->getSyncedTotal($payment));
    }

    public static function missingValueProvider(): array
    {
        return [
            'no key' => [[PaymentData::STATE => 'Completed']],
            'null value' => [[PaymentData::SYNCED_TOTAL => null]],
        ];
    }

    public function testGetPurchaseTotalReturnsStoredValue(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturn([PaymentData::PURCHASE_TOTAL => '23.97']);

        $this->assertSame(23.97, $this->paymentData->getPurchaseTotal($payment));
    }

    /**
     * @dataProvider missingPurchaseTotalProvider
     */
    #[DataProvider('missingPurchaseTotalProvider')]
    public function testGetPurchaseTotalReturnsNullWhenValueMissing(array $additionalInformation): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturn($additionalInformation);

        $this->assertNull($this->paymentData->getPurchaseTotal($payment));
    }

    public static function missingPurchaseTotalProvider(): array
    {
        return [
            'no key' => [[PaymentData::SYNCED_TOTAL => 23.97]],
            'null value' => [[PaymentData::PURCHASE_TOTAL => null]],
        ];
    }

    public function testForgetPurchaseTotalStripsOnlyProbeKeys(): void
    {
        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $payment->setAdditionalInformation([
            PaymentData::STATE => 'Completed',
            PaymentData::SYNCED_TOTAL => 23.97,
            PaymentData::PURCHASE_TOTAL => 23.97,
            PaymentData::PURCHASE_CURRENCY => null,
        ]);

        $this->paymentData->forgetPurchaseTotal($payment);

        $this->assertSame(
            [PaymentData::STATE => 'Completed', PaymentData::SYNCED_TOTAL => 23.97],
            $payment->getAdditionalInformation()
        );
    }

    public function testForgetPurchaseTotalLeavesPaymentWithoutProbeKeysUntouched(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturn([PaymentData::STATE => 'Completed']);
        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with([PaymentData::STATE => 'Completed']);

        $this->paymentData->forgetPurchaseTotal($payment);
    }

    /**
     * @dataProvider sameAmountProvider
     */
    #[DataProvider('sameAmountProvider')]
    public function testIsSameAmount(float $purchaseTotal, float $grandTotal, bool $expected): void
    {
        $this->assertSame($expected, $this->paymentData->isSameAmount($purchaseTotal, $grandTotal));
    }

    public static function sameAmountProvider(): array
    {
        return [
            'equal' => [23.97, 23.97, true],
            'sub-cent difference rounds to the same cents' => [23.97, 23.9650, true],
            'float noise' => [0.1 + 0.2, 0.3, true],
            'one cent apart' => [23.97, 23.98, false],
            'whole euro apart' => [23.97, 22.97, false],
            'zero against non-zero' => [0.0, 0.01, false],
        ];
    }

    /**
     * @dataProvider storedRateProvider
     */
    #[DataProvider('storedRateProvider')]
    public function testHasStoredRate(?string $shippingMethod, array $rateCodes, bool $expected): void
    {
        $address = $this->createAddress($shippingMethod, $rateCodes);

        $this->assertSame($expected, $this->paymentData->hasStoredRate($address));
    }

    public static function storedRateProvider(): array
    {
        return [
            'rate stored for the selected method' => ['markupposti_1', ['flatrate_flatrate', 'markupposti_1'], true],
            'rates stored only for other methods' => ['markupposti_1', ['flatrate_flatrate'], false],
            'no rates stored' => ['markupposti_1', [], false],
            'no method selected' => [null, ['flatrate_flatrate'], false],
            'empty method selected' => ['', ['flatrate_flatrate'], false],
        ];
    }

    /**
     * @param string[] $rateCodes
     */
    protected function createAddress(?string $shippingMethod, array $rateCodes): Address|MockObject
    {
        $address = $this->getMockBuilder(AddressStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getShippingMethod', 'getAllShippingRates'])
            ->getMock();
        $address->method('getShippingMethod')->willReturn($shippingMethod);
        $address->method('getAllShippingRates')->willReturn(array_map(
            fn (string $code) => new DataObject(['code' => $code]),
            $rateCodes
        ));

        return $address;
    }

    /**
     * @dataProvider mismatchesProvider
     */
    #[DataProvider('mismatchesProvider')]
    public function testSyncedTotalMismatches(
        array $additionalInformation,
        float $grandTotal,
        float $baseGrandTotal,
        string $method,
        bool $expected
    ): void {
        $quote = $this->createQuote($additionalInformation, $grandTotal, $baseGrandTotal, $method);

        $this->assertSame($expected, $this->paymentData->syncedTotalMismatches($quote));
    }

    public static function mismatchesProvider(): array
    {
        $zeroAmountMethod = PaymentMethod::$codes[PaymentMethod::ZERO_AMOUNT];
        $cardMethod = PaymentMethod::$codes[PaymentMethod::CARD];

        return [
            'synced total matches' => [
                [PaymentData::SYNCED_TOTAL => 23.95], 23.95, 23.95, $cardMethod, false,
            ],
            'synced total matches within float epsilon' => [
                [PaymentData::SYNCED_TOTAL => 3.5527136788005E-15], 0.0, 0.0, $zeroAmountMethod, false,
            ],
            'synced total differs' => [
                [PaymentData::SYNCED_TOTAL => 23.95], 33.95, 33.95, $cardMethod, true,
            ],
            'zero-amount synced but cart grew' => [
                [PaymentData::SYNCED_TOTAL => 0.0], 19.96, 19.96, $zeroAmountMethod, true,
            ],
            'legacy quote, zero-amount method with non-zero cart' => [
                [], 19.96, 19.96, $zeroAmountMethod, true,
            ],
            'legacy quote, zero-amount method with zero cart' => [
                [], 0.0, 0.0, $zeroAmountMethod, false,
            ],
            'legacy quote, real payment method' => [
                [], 19.96, 19.96, $cardMethod, false,
            ],
        ];
    }
}
