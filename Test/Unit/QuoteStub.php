<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

declare(strict_types=1);

namespace Avarda\Checkout3\Test\Unit;

use Magento\Quote\Model\Quote;

/**
 * Declares the magic data methods tests need to mock, because PHPUnit 12
 * removed MockBuilder::addMethods().
 */
class QuoteStub extends Quote
{
    public function getGrandTotal()
    {
        return $this->getData('grand_total');
    }

    public function getBaseGrandTotal()
    {
        return $this->getData('base_grand_total');
    }

    public function setTotalsCollectedFlag($flag)
    {
        return $this->setData('totals_collected_flag', $flag);
    }
}
