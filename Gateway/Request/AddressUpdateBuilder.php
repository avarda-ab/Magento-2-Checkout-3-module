<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Gateway\Request;

use Avarda\Checkout3\Helper\AvardaCheckBoxTypeValues;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class AddressUpdateBuilder implements BuilderInterface
{
    protected B2cDataBuilder $b2cDataBuilder;

    public function __construct(
        B2cDataBuilder $b2cDataBuilder
    ) {
        $this->b2cDataBuilder = $b2cDataBuilder;
    }

    public function build(array $buildSubject)
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder();

        return [
            "differentDeliveryAddress" => AvardaCheckBoxTypeValues::VALUE_HIDDEN,
            "deliveryAddress" => $this->b2cDataBuilder->getShippingAddress($order),
        ];
    }
}
