<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Observer;

use Avarda\Checkout3\Helper\PaymentMethod;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
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

    /** @var WriterInterface */
    protected $configWriter;

    /** @var ScopeConfigInterface */
    protected $config;

    public function __construct(
        FlagManager $flagManager,
        StoreManagerInterface $storeManager,
        WriterInterface $configWriter,
        ScopeConfigInterface $config
    ) {
        $this->flagManager = $flagManager;
        $this->storeManager = $storeManager;
        $this->configWriter = $configWriter;
        $this->config = $config;
    }

    public function execute(Observer $observer)
    {
        $paths = $observer->getEvent()->getData('changed_paths');
        $changed = [
            'payment/avarda_checkout3_checkout/test_mode',
            'payment/avarda_checkout3_checkout/client_secret',
            'payment/avarda_checkout3_checkout/client_id',
            'payment/avarda_checkout3_checkout/alternative_client_id',
            'payment/avarda_checkout3_checkout/alternative_client_secret',
        ];
        $hasChanged = false;
        foreach ($changed as $item) {
            if (in_array($item, $paths)) {
                $hasChanged = true;
                break;
            }
        }
        // If api keys or api url is changed remove current api token data
        if ($hasChanged) {
            foreach ($this->storeManager->getStores() as $store) {
                $this->flagManager->deleteFlag('avarda_checkout3_api_token' . $store->getId());
                $this->flagManager->deleteFlag('avarda_checkout3_token_valid_' . $store->getId());
                $this->flagManager->deleteFlag('avarda_checkout3_api_token_alt' . $store->getId());
                $this->flagManager->deleteFlag('avarda_checkout3_token_valid_alt_' . $store->getId());
            }
        }
        if (in_array('payment/avarda_checkout3_checkout/order_status', $paths)) {
            $value = $this->config->getValue('payment/avarda_checkout3_checkout/order_status');
            foreach (PaymentMethod::$codes as $code) {
                $this->configWriter->save("payment/{$code}/order_status", $value);
            }
        }
    }
}
