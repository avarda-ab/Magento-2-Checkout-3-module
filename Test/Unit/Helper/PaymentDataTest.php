<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

declare(strict_types=1);

namespace Avarda\Checkout3\Test\Unit\Helper;

use Avarda\Checkout3\Helper\PaymentData;
use Avarda\Checkout3\Helper\PaymentMethod;
use Avarda\Checkout3\Test\Unit\QuoteStub;
use Magento\Quote\Model\Quote;
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
