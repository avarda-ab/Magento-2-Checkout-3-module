<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Gateway\Request\Item;

use Avarda\Checkout3\Gateway\Helper\ItemSubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Helper\Formatter;

/**
 * Class TaxDataBuilder
 */
class TaxDataBuilder implements BuilderInterface
{
    use Formatter;

    /**
     * String
     */
    const TAX_CODE = 'TaxCode';

    /**
     * Decimal
     */
    const TAX_AMOUNT = 'TaxAmount';

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject)
    {
        $item = ItemSubjectReader::readItem($buildSubject);
        $taxAmount = ItemSubjectReader::readTaxAmount($buildSubject);

        return [
            self::TAX_CODE => $item->getTaxPercent(),
            self::TAX_AMOUNT => $this->formatPrice($taxAmount),
        ];
    }
}
