<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\FlagManager;
use Magento\Store\Model\StoreManagerInterface;

class ConfigSaveObserver implements ObserverInterface
{
    /** @var FlagManager */
    protected $flagManager;

    /** @var StoreManagerInterface */
    protected $storeManager;

    public function __construct(
        FlagManager $flagManager,
        StoreManagerInterface $storeManager
    ) {
        $this->flagManager = $flagManager;
        $this->storeManager = $storeManager;
    }

    public function execute(Observer $observer)
    {
        $paths = $observer->getEvent()->getData('changed_paths');
        $changed = ['payment/avarda_checkout3_checkout/test_mode', 'payment/avarda_checkout3_checkout/client_secret', 'payment/avarda_checkout3_checkout/client_id'];
        $hasChanged = false;
        foreach ($changed as $item) {
            if (in_array($item, $paths)) {
                $hasChanged = true;
                break;
            }
        }
        // If api api keys or api url is changed remove current api token data
        if ($hasChanged) {
            foreach ($this->storeManager->getStores() as $store) {
                $this->flagManager->deleteFlag('avarda_checkout3_api_token' . $store->getId());
                $this->flagManager->deleteFlag('avarda_checkout3_token_valid_' . $store->getId());
            }
        }
    }
}
