<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Model\Data;

use Avarda\Checkout3\Api\Data\PaymentDetailsInterface;
use Magento\Framework\Model\AbstractExtensibleModel;

/**
 * @codeCoverageIgnoreStart
 */
class PaymentDetails extends AbstractExtensibleModel implements PaymentDetailsInterface
{
    /**
     * {@inheritdoc}
     */
    public function getPurchaseData()
    {
        return $this->getData(self::PURCHASE_DATA);
    }

    /**
     * {@inheritdoc}
     */
    public function setPurchaseData($purchaseData)
    {
        return $this->setData(self::PURCHASE_DATA, $purchaseData);
    }
}
