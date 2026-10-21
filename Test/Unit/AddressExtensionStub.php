<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

declare(strict_types=1);

namespace Avarda\Checkout3\Test\Unit;

use Magento\Framework\Api\AbstractSimpleObject;

/**
 * Declares the pickup extension attribute methods, because the generated extension class only has them
 * when the In-Store Pickup modules are installed and PHPUnit 12 removed MockBuilder::addMethods().
 */
class AddressExtensionStub extends AbstractSimpleObject
{
    public function getPickupLocationCode()
    {
        return $this->_get('pickup_location_code');
    }

    public function setPickupLocationCode($pickupLocationCode)
    {
        return $this->setData('pickup_location_code', $pickupLocationCode);
    }
}
