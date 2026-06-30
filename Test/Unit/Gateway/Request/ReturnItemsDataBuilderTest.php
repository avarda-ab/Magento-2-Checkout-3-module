<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Test\Unit\Gateway\Request;

use Avarda\Checkout3\Gateway\Request\ReturnItemsDataBuilder;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItem;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\TestCase;

class ReturnItemsDataBuilderTest extends TestCase
{
    /** @var ReturnItemsDataBuilder */
    private $builder;

    protected function setUp(): void
    {
        $this->builder = new ReturnItemsDataBuilder();
    }

    public function testWholeQuantityProducesIntQuantityAndExactSum(): void
    {
        $item = $this->item(2.0, 'Fusion Backpack', '24-MB02', 94.02, 23.98, 0.0, 0.0, 25.5);
        $items = $this->build($this->creditmemo([$item], 118.00));

        $this->assertCount(1, $items);
        $this->assertSame('Fusion Backpack', $items[0]['description']);
        $this->assertSame('24-MB02', $items[0]['notes']);
        $this->assertSame('118.00', $items[0]['amount']);
        $this->assertSame('23.98', $items[0]['taxAmount']);
        $this->assertSame('25.50', $items[0]['taxCode']);
        $this->assertSame(2, $items[0]['quantity']);
        $this->assertSame('118.00', $this->sum($items));
    }

    public function testDecimalQuantityIsPreserved(): void
    {
        // Regression guard: 2.35 must NOT be rounded to 2.
        $item = $this->item(2.35, 'Decimal Qty Test', 'decimal-qty-test', 80.33, 20.48, 0.0, 0.0, 25.5);
        $items = $this->build($this->creditmemo([$item], 100.81));

        $this->assertSame(2.35, $items[0]['quantity']);
        $this->assertIsFloat($items[0]['quantity']);
        $this->assertSame('100.81', $items[0]['amount']);
        $this->assertSame('100.81', $this->sum($items));
    }

    public function testItemDiscountIsNettedFromAmount(): void
    {
        $item = $this->item(1.0, 'Discounted', 'SKU-D', 47.01, 9.49, 5.00, 0.50, 25.5);
        $items = $this->build($this->creditmemo([$item], 52.00));

        $this->assertSame('52.00', $items[0]['amount']);
        $this->assertSame('9.99', $items[0]['taxAmount']);
        $this->assertSame('52.00', $this->sum($items));
    }

    public function testShippingAndAdjustmentLinesWithCorrectSigns(): void
    {
        $item = $this->item(1.0, 'Item', 'SKU', 47.01, 11.99, 0.0, 0.0, 25.5);
        $cm = $this->creditmemo([$item], 68.90, 4.90, 3.91, 0.99, 10.0, 5.0);
        $items = $this->build($cm);

        $byDesc = [];
        foreach ($items as $line) {
            $byDesc[$line['description']] = $line;
        }

        $this->assertSame('59.00', $byDesc['Item']['amount']);
        $this->assertSame('4.90', $byDesc['Shipping']['amount']);
        $this->assertSame('0.99', $byDesc['Shipping']['taxAmount']);
        $this->assertSame('10.00', $byDesc['Adjustment refund']['amount']);
        // Fee subtracts from the refund, so it must be a negative line.
        $this->assertSame('-5.00', $byDesc['Adjustment fee']['amount']);
        $this->assertArrayNotHasKey('Adjustment', $byDesc);
        $this->assertSame('68.90', $this->sum($items));
    }

    public function testReconciliationLineAbsorbsRemainder(): void
    {
        // Grand total below the line sum (e.g. shipping discount on no single
        // line) must be absorbed by a correction line.
        $item = $this->item(1.0, 'Item', 'SKU', 47.01, 11.99, 0.0, 0.0, 25.5);
        $items = $this->build($this->creditmemo([$item], 55.00));

        $last = end($items);
        $this->assertSame('Adjustment', $last['description']);
        $this->assertSame('-4.00', $last['amount']);
        $this->assertSame('55.00', $this->sum($items));
    }

    public function testFallbackToSingleLineWhenNoItems(): void
    {
        $cm = $this->creditmemo([], 50.00);
        $cm->method('getBaseTaxAmount')->willReturn(10.00);
        $items = $this->build($cm);

        $this->assertCount(1, $items);
        $this->assertSame('Return', $items[0]['description']);
        $this->assertSame('Return from Magento', $items[0]['notes']);
        $this->assertSame('50.00', $items[0]['amount']);
        $this->assertSame('10.00', $items[0]['taxAmount']);
        $this->assertSame(1, $items[0]['quantity']);
    }

    private function build(Creditmemo $creditmemo): array
    {
        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCreditmemo'])
            ->getMock();
        $payment->method('getCreditmemo')->willReturn($creditmemo);

        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getPayment')->willReturn($payment);

        $result = $this->builder->build(['payment' => $paymentDO]);
        return $result['Items'];
    }

    private function item(
        float $qty,
        string $name,
        string $sku,
        float $baseRowTotal,
        float $baseTax,
        float $baseDiscount,
        float $baseDtc,
        float $taxPercent
    ): CreditmemoItem {
        $orderItem = $this->getMockBuilder(OrderItem::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getTaxPercent'])
            ->getMock();
        $orderItem->method('getTaxPercent')->willReturn($taxPercent);

        $item = $this->getMockBuilder(CreditmemoItem::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getQty', 'getName', 'getSku', 'getBaseRowTotal', 'getBaseTaxAmount',
                'getBaseDiscountAmount', 'getBaseDiscountTaxCompensationAmount', 'getOrderItem',
            ])
            ->getMock();
        $item->method('getQty')->willReturn($qty);
        $item->method('getName')->willReturn($name);
        $item->method('getSku')->willReturn($sku);
        $item->method('getBaseRowTotal')->willReturn($baseRowTotal);
        $item->method('getBaseTaxAmount')->willReturn($baseTax);
        $item->method('getBaseDiscountAmount')->willReturn($baseDiscount);
        $item->method('getBaseDiscountTaxCompensationAmount')->willReturn($baseDtc);
        $item->method('getOrderItem')->willReturn($orderItem);

        return $item;
    }

    /**
     * @param CreditmemoItem[] $items
     */
    private function creditmemo(
        array $items,
        float $baseGrandTotal,
        float $shippingInclTax = 0.0,
        float $shippingAmount = 0.0,
        float $shippingTax = 0.0,
        float $adjPositive = 0.0,
        float $adjNegative = 0.0
    ): Creditmemo {
        $cm = $this->getMockBuilder(Creditmemo::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getAllItems', 'getBaseShippingInclTax', 'getBaseShippingAmount',
                'getBaseShippingTaxAmount', 'getBaseAdjustmentPositive',
                'getBaseAdjustmentNegative', 'getBaseGrandTotal', 'getBaseTaxAmount',
            ])
            ->getMock();
        $cm->method('getAllItems')->willReturn($items);
        $cm->method('getBaseShippingInclTax')->willReturn($shippingInclTax);
        $cm->method('getBaseShippingAmount')->willReturn($shippingAmount);
        $cm->method('getBaseShippingTaxAmount')->willReturn($shippingTax);
        $cm->method('getBaseAdjustmentPositive')->willReturn($adjPositive);
        $cm->method('getBaseAdjustmentNegative')->willReturn($adjNegative);
        $cm->method('getBaseGrandTotal')->willReturn($baseGrandTotal);

        return $cm;
    }

    private function sum(array $items): string
    {
        $sum = 0.0;
        foreach ($items as $line) {
            $sum += (float)$line['amount'];
        }
        return sprintf('%.2F', $sum);
    }
}
