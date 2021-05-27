<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Api;

/**
 * Interface for managing Avarda order complete callback
 * @api
 */
interface PaymentCompleteInterface
{
    /**
     * @throws \Magento\Framework\Exception\PaymentException
     * @param string $purchaseId the external purchaseId
     * @return \Avarda\Checkout3\Api\Data\ItemDetailsListInterface
     */
    public function execute($purchaseId);
}
