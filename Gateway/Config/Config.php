<?php
/**
 * @copyright Copyright © 2021 Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\FlagManager;
use Magento\Framework\Url;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Config
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
    const KEY_ACTIVE = 'active';
    const KEY_TEST_MODE = 'test_mode';
    const KEY_CLIENT_SECRET = 'client_secret';
    const KEY_CLIENT_ID = 'client_id';

    const KEY_TOKEN_FLAG = 'avarda_checkout3_api_token';
    const KEY_ONEPAGE_REDIRECT_ACTIVE = 'onepage_redirect_active';
    const KEY_CUSTOM_CSS = 'avarda_checkout3/api/custom_css';
    const KEY_COUNTRY_SELECTOR = 'avarda_checkout3/api/country_selector';
    const KEY_SHOW_B2B_LINK = 'avarda_checkout3/api/show_b2b_link';
    const KEY_SHOW_POSTCODE = 'avarda_checkout3/api/show_postcode';

    const URL_TEST = 'https://avdonl-s-checkout.avarda.org/';
    const URL_PRODUCTION = 'https://avdonl-p-checkout.avarda.org/';
    const TOKEN_PATH = 'api/partner/tokens';

    /** @var EncryptorInterface */
    protected $encryptor;

    /** @var FlagManager */
    protected $flagManager;

    /** @var Url */
    protected $url;

    /** @var ScopeConfigInterface */
    protected $scopeConfig;

    /** @var StoreManagerInterface */
    protected $storeManager;

    protected $storeId = null;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        FlagManager $flagManager,
        Url $url,
        StoreManagerInterface $storeManager,
        $methodCode = 'avarda_checkout3_checkout',
        $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
        $this->encryptor = $encryptor;
        $this->flagManager = $flagManager;
        $this->url = $url;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * Can set which store scope to get configs from
     * @param $storeId
     */
    public function setStoreId($storeId)
    {
        $this->storeId = $storeId;
    }

    /**
     * @return string|int
     */
    public function getStoreId()
    {
        if (!$this->storeId) {
            $this->storeId = $this->storeManager->getStore()->getId();
        }
        return $this->storeId;
    }

    /**
     * Get config value in storeCode scope
     * @param $path
     * @return mixed
     */
    public function getConfigValue($path)
    {
        return $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $this->getStoreId()
        );
    }

    /**
     * Get Payment configuration status
     *
     * @return bool
     */
    public function isActive()
    {
        return (bool) $this->getValue(self::KEY_ACTIVE);
    }

    /**
     * @return bool
     */
    public function getTestMode()
    {
        return (bool) $this->getValue(self::KEY_TEST_MODE);
    }

    /**
     * @return string
     */
    public function getClientSecret()
    {
        return $this->encryptor->decrypt($this->getValue(self::KEY_CLIENT_SECRET, $this->getStoreId()));
    }

    /**
     * @return string
     */
    public function getClientId()
    {
        return $this->getValue(self::KEY_CLIENT_ID, $this->getStoreId());
    }

    /**
     * @return string
     */
    public function getApiUrl()
    {
        if ($this->getTestMode()) {
            return self::URL_TEST;
        }
        return self::URL_PRODUCTION;
    }

    /**
     * @return string
     */
    public function getTokenUrl()
    {
        return $this->getApiUrl() . self::TOKEN_PATH;
    }

    /**
     * @return bool
     */
    public function isOnepageRedirectActive()
    {
        return (bool) $this->getValue(self::KEY_ONEPAGE_REDIRECT_ACTIVE);
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->encryptor->decrypt($this->flagManager->getFlagData(self::KEY_TOKEN_FLAG . $this->getStoreId()));
    }

    /**
     * @param $token string
     */
    public function saveNewToken($token)
    {
        $this->flagManager->saveFlag(self::KEY_TOKEN_FLAG . $this->getStoreId(), $this->encryptor->encrypt($token));
    }

    public function getCheckoutJsUrl()
    {
        if ($this->getTestMode()) {
            return 'https://avdonl0s0checkout0fe.blob.core.windows.net/frontend/static/js/main.js';
        } else {
            return 'https://avdonl0p0checkout0fe.blob.core.windows.net/frontend/static/js/main.js';
        }
    }

    /**
     * @return string
     */
    public function getNotificationUrl()
    {
        return $this->url->getBaseUrl(UrlInterface::URL_TYPE_WEB) . 'rest/V1/avarda3/orderComplete';
    }

    /**
     * @return string
     */
    public function getCustomCss()
    {
        return $this->getConfigValue(self::KEY_CUSTOM_CSS);
    }

    /**
     * @return bool
     */
    public function getCountrySelector()
    {
        return (bool)$this->getConfigValue(self::KEY_COUNTRY_SELECTOR);
    }

    /**
     * @return bool
     */
    public function getShowB2Blink()
    {
        return (bool)$this->getConfigValue(self::KEY_SHOW_B2B_LINK);
    }

    /**
     * @return bool
     */
    public function getShowPostcode()
    {
        return (bool)$this->getConfigValue(self::KEY_SHOW_POSTCODE);
    }
}
