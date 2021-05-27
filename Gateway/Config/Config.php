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
    const KEY_ORDER_STATUS = 'order_status';
    const KEY_CUSTOM_CSS = 'avarda_checkout3/api/custom_css';

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

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        FlagManager $flagManager,
        Url $url,
        $methodCode = 'avarda_checkout3_checkout',
        $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
        $this->encryptor = $encryptor;
        $this->flagManager = $flagManager;
        $this->url = $url;
        $this->scopeConfig = $scopeConfig;
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
        return $this->encryptor->decrypt($this->getValue(self::KEY_CLIENT_SECRET));
    }

    /**
     * @return string
     */
    public function getClientId()
    {
        return $this->getValue(self::KEY_CLIENT_ID);
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
     * @return int
     */
    public function getOrderStatus()
    {
        return $this->getValue(self::KEY_ORDER_STATUS);
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
        return $this->encryptor->decrypt($this->flagManager->getFlagData(self::KEY_TOKEN_FLAG));
    }

    /**
     * @param $token string
     */
    public function saveNewToken($token)
    {
        $this->flagManager->saveFlag(self::KEY_TOKEN_FLAG, $this->encryptor->encrypt($token));
    }

    public function getCheckoutJsUrl()
    {
        if ($this->getTestMode()) {
            return 'https://avdonl0s0checkout0fe.blob.core.windows.net/frontend/static/js/main.js';
        } else {
            return 'https://avdonl0p0checkout0fe.blob.core.windows.net/frontend/static/js/main.js';
        }
    }

    public function getDefaultPayment()
    {
        return $this->getValue(self::KEY_ORDER_STATUS);
    }

    public function getNotificationUrl()
    {
        return $this->url->getBaseUrl(UrlInterface::URL_TYPE_WEB) . '/V1/avarda3/orderComplete';
    }

    public function getCustomCss()
    {
        return $this->scopeConfig->getValue(self::KEY_CUSTOM_CSS);
    }
}
