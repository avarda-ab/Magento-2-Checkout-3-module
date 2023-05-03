<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Api;

use Avarda\Checkout3\Api\Data\ItemDetailsListInterface;
use Magento\Framework\Exception\PaymentException;

/**
 * Interface for managing Avarda order complete callback
 *
 * @api
 */
interface PaymentCompleteInterface
{
    /**
     * @param string $purchaseId the external purchaseId
     * @return ItemDetailsListInterface
     * @throws PaymentException
     */
    public function execute($purchaseId);
}
