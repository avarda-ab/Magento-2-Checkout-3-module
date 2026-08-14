<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Helper\Formatter;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItem;
use Magento\Sales\Model\Order\Payment;

class ReturnItemsDataBuilder implements BuilderInterface
{
    use Formatter;

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject)
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        /** @var Payment $payment */
        $payment = $paymentDO->getPayment();
        /** @var Creditmemo $creditMemo */
        $creditMemo = $payment->getCreditmemo();

        $lines = [];

        foreach ($creditMemo->getAllItems() as $item) {
            $line = $this->buildItemLine($item);
            if ($line !== null) {
                $lines[] = $line;
            }
        }

        $shippingLine = $this->buildShippingLine($creditMemo);
        if ($shippingLine !== null) {
            $lines[] = $shippingLine;
        }

        foreach ($this->buildAdjustmentLines($creditMemo) as $adjustmentLine) {
            $lines[] = $adjustmentLine;
        }

        // Avarda rejects an empty Items array; keep the legacy lump line.
        if (empty($lines)) {
            return [
                'Items' => [
                    $this->line(
                        'Return',
                        'Return from Magento',
                        (float)$creditMemo->getBaseGrandTotal(),
                        (float)$creditMemo->getBaseTaxAmount(),
                        0.0,
                        1
                    ),
                ],
            ];
        }

        $reconciliationLine = $this->buildReconciliationLine($creditMemo, $lines);
        if ($reconciliationLine !== null) {
            $lines[] = $reconciliationLine;
        }

        return ['Items' => $lines];
    }

    /**
     * @param CreditmemoItem $item
     * @return array|null
     */
    protected function buildItemLine(CreditmemoItem $item)
    {
        $qty = (float)$item->getQty();
        if ($qty <= 0) {
            return null;
        }

        $taxAmount = (float)$item->getBaseTaxAmount()
            + (float)$item->getBaseDiscountTaxCompensationAmount();

        $amount = (float)$item->getBaseRowTotal()
            - (float)$item->getBaseDiscountAmount()
            + $taxAmount;

        $orderItem = $item->getOrderItem();
        $taxPercent = $orderItem ? (float)$orderItem->getTaxPercent() : 0.0;

        return $this->line(
            (string)$item->getName(),
            (string)$item->getSku(),
            $amount,
            $taxAmount,
            $taxPercent,
            1
        );
    }

    /**
     * @param Creditmemo $creditMemo
     * @return array|null
     */
    protected function buildShippingLine(Creditmemo $creditMemo)
    {
        $shippingInclTax = (float)$creditMemo->getBaseShippingInclTax();
        $shippingExclTax = (float)$creditMemo->getBaseShippingAmount();

        if ($shippingInclTax <= 0.0 && $shippingExclTax <= 0.0) {
            return null;
        }

        $taxAmount = (float)$creditMemo->getBaseShippingTaxAmount();
        $amount = $shippingInclTax > 0.0 ? $shippingInclTax : $shippingExclTax + $taxAmount;
        $taxPercent = $shippingExclTax > 0.0 ? ($taxAmount / $shippingExclTax) * 100 : 0.0;

        return $this->line('Shipping', 'shipping', $amount, $taxAmount, $taxPercent, 1);
    }

    /**
     * Refund adds to the customer total, fee subtracts; hence the negated fee.
     *
     * @param Creditmemo $creditMemo
     * @return array
     */
    protected function buildAdjustmentLines(Creditmemo $creditMemo)
    {
        $lines = [];

        $adjustmentRefund = (float)$creditMemo->getBaseAdjustmentPositive();
        if (abs($adjustmentRefund) >= 0.005) {
            $lines[] = $this->line(
                'Adjustment refund',
                'adjustment_refund',
                $adjustmentRefund,
                0.0,
                0.0,
                1
            );
        }

        $adjustmentFee = (float)$creditMemo->getBaseAdjustmentNegative();
        if (abs($adjustmentFee) >= 0.005) {
            $lines[] = $this->line(
                'Adjustment fee',
                'adjustment_fee',
                -$adjustmentFee,
                0.0,
                0.0,
                1
            );
        }

        return $lines;
    }

    /**
     * Shipping discount and rounding aren't on any line; this keeps the returned
     * total equal to the credit memo base grand total Magento actually refunds.
     *
     * @param Creditmemo $creditMemo
     * @param array $lines
     * @return array|null
     */
    protected function buildReconciliationLine(Creditmemo $creditMemo, array $lines)
    {
        $sum = 0.0;
        foreach ($lines as $line) {
            $sum += (float)$line['amount'];
        }

        $delta = (float)$creditMemo->getBaseGrandTotal() - $sum;
        if (abs($delta) < 0.005) {
            return null;
        }

        return $this->line('Adjustment', 'adjustment', $delta, 0.0, 0.0, 1);
    }

    /**
     * @param string $description
     * @param string $notes
     * @param float $amount
     * @param float $taxAmount
     * @param float $taxPercent
     * @param int|float $quantity
     * @return array
     */
    protected function line(
        $description,
        $notes,
        $amount,
        $taxAmount,
        $taxPercent,
        $quantity
    ) {
        return [
            'description' => mb_substr($description, 0, 35),
            'notes'       => mb_substr($notes, 0, 35),
            'amount'      => $this->formatPrice($amount),
            'taxCode'     => $this->formatPrice($taxPercent),
            'taxAmount'   => $this->formatPrice($taxAmount),
            'quantity'    => $quantity,
        ];
    }
}
