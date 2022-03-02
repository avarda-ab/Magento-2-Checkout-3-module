<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Gateway\Helper;

use Avarda\Checkout3\Gateway\Data\ItemAdapterInterface;

/**
 * This class encapsulates implicit interfaces (array structures) used in
 * payments implementation. This type of class was introduced for backward
 * compatibility with legacy implementation according to Magento 2 core.
 *
 * @api
 * @since 100.0.2
 */
class ItemSubjectReader
{
    /**
     * Reads item from subject
     *
     * @param array $subject Subject passed from items builder
     *
     * @return ItemAdapterInterface
     */
    public static function readItem(array $subject)
    {
        if (!isset($subject['item'])
            || !$subject['item'] instanceof ItemAdapterInterface
        ) {
            throw new \InvalidArgumentException(
                'Item data object should be provided'
            );
        }

        return $subject['item'];
    }

    /**
     * Reads quantity from subject
     *
     * @param array $subject Subject passed from items builder
     *
     * @return float
     */
    public static function readQty(array $subject)
    {
        if (!isset($subject['qty']) || !is_numeric($subject['qty'])) {
            throw new \InvalidArgumentException(
                'Quantity should be provided'
            );
        }

        return $subject['qty'];
    }

    /**
     * Reads amount from subject
     *
     * @param array $subject Subject passed from items builder
     *
     * @return float
     */
    public static function readAmount(array $subject)
    {
        if (!isset($subject['amount']) || !is_numeric($subject['amount'])) {
            throw new \InvalidArgumentException(
                'Amount should be provided'
            );
        }

        return $subject['amount'];
    }

    /**
     * Reads tax amount from subject
     *
     * @param array $subject Subject passed from items builder
     *
     * @return float
     */
    public static function readTaxAmount(array $subject)
    {
        if (!isset($subject['tax_amount']) || !is_numeric($subject['tax_amount'])) {
            throw new \InvalidArgumentException(
                'Tax amount should be provided'
            );
        }

        return $subject['tax_amount'];
    }
}
