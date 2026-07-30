<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

declare(strict_types=1);

namespace Avarda\Checkout3\Test\Unit\Gateway\Validator;

use Avarda\Checkout3\Gateway\Validator\CaptureAmountValidator;
use Avarda\Checkout3\Helper\PaymentMethod;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CaptureAmountValidatorTest extends TestCase
{
    protected CaptureAmountValidator $validator;

    protected ResultInterfaceFactory|MockObject $resultFactoryMock;

    protected ResultInterface|MockObject $resultMock;

    protected function setUp(): void
    {
        $this->resultFactoryMock = $this->createMock(ResultInterfaceFactory::class);
        $this->resultMock = $this->createMock(ResultInterface::class);
        $this->validator = new CaptureAmountValidator($this->resultFactoryMock);
    }

    /**
     * @dataProvider validationProvider
     */
    public function testValidate(string $methodCode, float $grandTotal, bool $expectedIsValid): void
    {
        $order = $this->createMock(OrderAdapterInterface::class);
        $order->method('getGrandTotalAmount')->willReturn($grandTotal);
        $order->method('getOrderIncrementId')->willReturn('100000001');

        $methodInstance = $this->createMock(MethodInterface::class);
        $methodInstance->method('getCode')->willReturn($methodCode);

        $payment = $this->createMock(InfoInterface::class);
        $payment->method('getMethodInstance')->willReturn($methodInstance);

        $paymentDataObject = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDataObject->method('getOrder')->willReturn($order);
        $paymentDataObject->method('getPayment')->willReturn($payment);

        $this->resultFactoryMock
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(
                static fn (array $arguments): bool => ($arguments['isValid'] ?? null) === $expectedIsValid
            ))
            ->willReturn($this->resultMock);

        $this->assertSame(
            $this->resultMock,
            $this->validator->validate(['payment' => $paymentDataObject])
        );
    }

    public static function validationProvider(): array
    {
        $zeroAmountMethod = PaymentMethod::$codes[PaymentMethod::ZERO_AMOUNT];
        $cardMethod = PaymentMethod::$codes[PaymentMethod::CARD];

        return [
            'zero-amount method with non-zero total is rejected' => [$zeroAmountMethod, 49.90, false],
            'zero-amount method with genuinely free order is allowed' => [$zeroAmountMethod, 0.0, true],
            'zero-amount method just below threshold is allowed' => [$zeroAmountMethod, 0.00005, true],
            'real payment method with non-zero total is allowed' => [$cardMethod, 49.90, true],
        ];
    }
}
