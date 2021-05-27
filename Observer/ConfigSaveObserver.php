<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\FlagManager;

class ConfigSaveObserver implements ObserverInterface
{
    /** @var FlagManager */
    protected $flagManager;

    public function __construct(
        FlagManager $flagManager
    ) {
        $this->flagManager = $flagManager;
    }

    public function execute(Observer $observer)
    {
        $paths = $observer->getEvent()->getData('changed_paths');
        $changed = ['avarda_checkout3/api/test_mode', 'avarda_checkout3/api/client_secret', 'avarda_checkout3/api/client_id'];
        $hasChanged = false;
        foreach ($changed as $item) {
            if (in_array($item, $paths)) {
                $hasChanged = true;
                break;
            }
        }
        // If api api keys or api url is changed remove current api token data
        if ($hasChanged) {
            $this->flagManager->deleteFlag('avarda_checkout3_api_token');
            $this->flagManager->deleteFlag('avarda_checkout3_token_valid');
        }
    }
}
