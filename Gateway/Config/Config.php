<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Gateway\Config;

use Avarda\Checkout3\Model\Ui\ConfigProviderBase;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
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
    const KEY_ADDRESS_CHANGE = 'avarda_checkout3/api/address_change';
    const KEY_OFFER_LOGIN = 'avarda_checkout3/api/offer_login';
    const KEY_SHOW_NEWSLETTER = 'avarda_checkout3/api/show_newsletter';
    const KEY_NEWSLETTER_DEFAULT = 'avarda_checkout3/api/newsletter_default';
    const KEY_SELECT_SHIPPING_METHOD = 'avarda_checkout3/api/select_shipping_method';

    const KEY_ALTERNATIVE_CLIENT_ID = 'payment/avarda_checkout3_checkout/alternative_client_id';
    const KEY_ALTERNATIVE_CLIENT_SECRET = 'payment/avarda_checkout3_checkout/alternative_client_secret';
    const KEY_ALTERNATIVE_PRODUCT_TYPES = 'payment/avarda_checkout3_checkout/alternative_product_types';

    const URL_TEST = 'https://stage.checkout-api.avarda.com/';
    const URL_PRODUCTION = 'https://checkout-api.avarda.com/';
    const TOKEN_PATH = 'api/partner/tokens';

    protected EncryptorInterface $encryptor;
    protected FlagManager $flagManager;
    protected Url $url;
    protected ScopeConfigInterface $scopeConfig;
    protected StoreManagerInterface $storeManager;
    protected $storeId = null;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        FlagManager $flagManager,
        Url $url,
        StoreManagerInterface $storeManager,
        $methodCode = ConfigProviderBase::CODE,
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
     * @return int
     * @throws NoSuchEntityException
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
     *
     * @param $path
     * @return mixed
     * @throws NoSuchEntityException
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
     * @throws NoSuchEntityException
     */
    public function getClientSecret()
    {
        return $this->encryptor->decrypt($this->getValue(self::KEY_CLIENT_SECRET, $this->getStoreId()));
    }

    /**
     * @return string
     * @throws NoSuchEntityException
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

    public function getAlternativeProductTypes()
    {
        return $this->getConfigValue(self::KEY_ALTERNATIVE_PRODUCT_TYPES);
    }

    public function getAlternativeClientId()
    {
        return $this->getConfigValue(self::KEY_ALTERNATIVE_CLIENT_ID);
    }

    public function getAlternativeClientSecret()
    {
        return $this->encryptor->decrypt($this->getConfigValue(self::KEY_ALTERNATIVE_CLIENT_SECRET));
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
     * @throws NoSuchEntityException
     */
    public function getToken($alternative = false)
    {
        return $this->encryptor->decrypt($this->flagManager->getFlagData(self::KEY_TOKEN_FLAG  . ($alternative ? 'alt' : '') . $this->getStoreId()));
    }

    /**
     * @param $token string
     * @throws NoSuchEntityException
     */
    public function saveNewToken($token, $alternative = false)
    {
        $this->flagManager->saveFlag(self::KEY_TOKEN_FLAG . ($alternative ? 'alt' : '') . $this->getStoreId(), $this->encryptor->encrypt($token));
    }

    public function getCheckoutJsUrl()
    {
        if ($this->getTestMode()) {
            return 'https://stage.checkout-cdn.avarda.com/cdn/static/js/main.js';
        } else {
            return 'https://checkout-cdn.avarda.com/cdn/static/js/main.js';
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
     * @throws NoSuchEntityException
     */
    public function getCustomCss()
    {
        return $this->getConfigValue(self::KEY_CUSTOM_CSS);
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    public function getCountrySelector()
    {
        return (bool)$this->getConfigValue(self::KEY_COUNTRY_SELECTOR);
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    public function getShowB2Blink()
    {
        return (bool)$this->getConfigValue(self::KEY_SHOW_B2B_LINK);
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    public function getShowPostcode()
    {
        return (bool)$this->getConfigValue(self::KEY_SHOW_POSTCODE);
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    public function getAdressChangeCallback()
    {
        return (bool)$this->getConfigValue(self::KEY_ADDRESS_CHANGE);
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    public function getOfferLogin()
    {
        return (bool)$this->getConfigValue(self::KEY_OFFER_LOGIN);
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    public function getShowNewsletter(): bool
    {
        return (bool)$this->getConfigValue(self::KEY_SHOW_NEWSLETTER);
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    public function getNewsletterSelectedDefault(): bool
    {
        return (bool)$this->getConfigValue(self::KEY_NEWSLETTER_DEFAULT);
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    public function getSelectShippingMethod(): bool
    {
        return (bool)$this->getConfigValue(self::KEY_SELECT_SHIPPING_METHOD);
    }
}
