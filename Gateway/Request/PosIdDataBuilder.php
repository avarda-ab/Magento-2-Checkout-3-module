<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;

class PosIdDataBuilder implements BuilderInterface
{
    public function build(array $buildSubject)
    {
        return [
            "posId" => 0,
        ];
    }
}
