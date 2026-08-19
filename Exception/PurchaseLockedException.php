<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

declare(strict_types=1);

namespace Avarda\Checkout3\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * Another process holds the lock of a purchase involved in the operation.
 */
class PurchaseLockedException extends LocalizedException
{
}
