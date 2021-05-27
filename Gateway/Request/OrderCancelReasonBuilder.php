<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

/**
 * Class OrderReferenceDataBuilder
 */
class OrderCancelReasonBuilder implements BuilderInterface
{
    /**
     * Shipping description
     */
    const REASON = 'reason';

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject)
    {
        return [self::REASON => 'Order canceled in Magento'];
    }
}
