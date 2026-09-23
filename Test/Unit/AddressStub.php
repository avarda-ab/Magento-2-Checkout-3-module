<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

declare(strict_types=1);

namespace Avarda\Checkout3\Test\Unit;

use Magento\Quote\Model\Quote\Address;

/**
 * Declares the magic data methods tests need to mock, because PHPUnit 12
 * removed MockBuilder::addMethods().
 */
class AddressStub extends Address
{
    public function setCollectShippingRates($flag)
    {
        return $this->setData('collect_shipping_rates', $flag);
    }
}
