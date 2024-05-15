<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Block;

use Avarda\Checkout3\Gateway\Config\Config;
use Avarda\Checkout3\Helper\JwtToken;
use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;
use Magento\Checkout\Model\CompositeConfigProvider;
use Magento\Checkout\Model\Session;
use Magento\Directory\Helper\Data;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\Resolver as LocaleResolver;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;

class Checkout extends Template
{
    /** @var Config */
    protected $config;

    /** @var CompositeConfigProvider */
    protected $configProvider;

    /** @var Session */
    protected $checkoutSession;

    /** @var Data */
    protected $directoryHelper;

    /** @var QuoteIdMaskFactory */
    protected $quoteIdMaskFactory;

    /** @var RequestInterface */
    protected $request;

    /** @var Repository */
    protected $assetRepo;

    /** @var CartInterface */
    protected $quote;

    /** @var LocaleResolver */
    protected $localeResolver;

    /** @var array|LayoutProcessorInterface[] */
    protected $layoutProcessors;

    /** @var JwtToken */
    protected $jwtTokenHelper;

    /** @var SerializerInterface|mixed */
    private $serializer;

    public function __construct(
        Context $context,
        Config $config,
        CompositeConfigProvider $configProvider,
        Session $checkoutSession,
        Data $directoryHelper,
        ProductMetadataInterface $productMetadata,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        RequestInterface $request,
        LocaleResolver $localeResolver,
        JwtToken $jwtTokenHelper,
        array $layoutProcessors = [],
        array $data = [],
        SerializerInterface $serializerInterface = null
    ) {
        parent::__construct($context, $data);

        $this->config = $config;
        $this->configProvider = $configProvider;
        $this->checkoutSession = $checkoutSession;
        $this->directoryHelper = $directoryHelper;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->assetRepo = $context->getAssetRepository();
        $this->request = $request;
        $this->localeResolver = $localeResolver;
        $this->layoutProcessors = $layoutProcessors;
        $this->jwtTokenHelper = $jwtTokenHelper;
        $this->serializer = $serializerInterface ?: \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Framework\Serialize\Serializer\JsonHexTag::class);

        if ($productMetadata->getEdition() === 'Enterprise') {
            $this->jsLayout = array_merge_recursive([
                'components' => [
                    'gift-card' => [
                        'component' => 'Magento_GiftCardAccount/js/view/payment/gift-card-account',
                        'children' => [
                            'errors' => [
                                'sortOrder' => 0,
                                'component' => 'Magento_GiftCardAccount/js/view/payment/gift-card-messages',
                                'displayArea' => 'messages'
                            ]
                        ]
                    ]
                ]
            ], $this->jsLayout);
        }
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getBaseMediaUrl()
    {
        return $this->_storeManager->getStore()
            ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
    }

    /**
     * @return int|null
     */
    public function getMaskedQuoteId()
    {
        return $this->quoteIdMaskFactory->create()->load(
            $this->getQuoteId(),
            'quote_id'
        )->getMaskedId();
    }

    /**
     * @return int|null
     */
    public function getCustomerId()
    {
        return (int) $this->getQuote()->getCustomerId();
    }

    /**
     * @return int|null
     */
    public function getQuoteId()
    {
        return $this->getQuote()->getId();
    }

    /**
     * @return bool
     */
    public function hasItems()
    {
        return $this->getQuote()->hasItems();
    }

    /**
     * @return CartInterface
     */
    protected function getQuote()
    {
        if (!isset($this->quote)) {
            $this->quote = $this->checkoutSession->getQuote();
        }

        return $this->quote;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->localeResolver->getLocale();
    }

    /**
     * @return string
     */
    public function getCountryId()
    {
        return $this->directoryHelper->getDefaultCountry();
    }

    /**
     * Get AvardaCheckOutClient script path for Require.js.
     *
     * @return string
     */
    public function getCheckOutClientScriptPath()
    {
        return $this->config->getCheckoutJsUrl();
    }

    /**
     * @return bool
     */
    public function getShowPostcode()
    {
        return $this->config->getShowPostcode();
    }

    /**
     * @return bool
     */
    public function getAdressChangeCallback()
    {
        return $this->config->getAdressChangeCallback();
    }

    /**
     * @return bool
     */
    public function getOfferLogin()
    {
        return $this->config->getOfferLogin();
    }

    /**
     * @return array
     */
    public function getCheckoutConfig()
    {
        return $this->configProvider->getConfig();
    }

    /**
     * @return string
     */
    public function getPurchaseId()
    {
        return $this->_request->getParam('purchase');
    }

    public function getJwtToken($purcheseId)
    {
        return $this->jwtTokenHelper->getNewJwtToken($purcheseId);
    }

    public function getCheckoutUrl()
    {
        if ($this->config->isOnepageRedirectActive()) {
            return $this->getUrl('avarda3/checkout');
        } else {
            return $this->getUrl('avarda3/checkout', ['_query' => ['fromCheckout' => 1]]);
        }
    }

    /**
     * @return string
     */
    public function getSaveOrderUrl()
    {
        return $this->getUrl('avarda3/checkout/saveOrder/', ['_secure' => true]) . 'purchase/';
    }

    /**
     * @return string
     */
    public function getCallbackUrl()
    {
        return $this->getUrl('avarda3/checkout/process/', ['_secure' => true]) . 'purchase/';
    }

    /**
     * @return string
     */
    public function getCompleteCallbackUrl()
    {
        return $this->getBaseUrl() . 'rest/V1/avarda3/orderComplete';
    }

    /**
     * @return string
     */
    public function getProductPlaceholderUrl()
    {
        return $this->getViewFileUrl('Magento_Catalog::images/product/placeholder/thumbnail.jpg');
    }

    /**
     * @return boolean
     */
    public function getArrivedFromCheckout()
    {
        return !$this->config->isOnepageRedirectActive() && $this->request->getParam('fromCheckout');
    }

    public function getJsLayout()
    {
        foreach ($this->layoutProcessors as $processor) {
            $this->jsLayout = $processor->process($this->jsLayout);
        }

        return $this->serializer->serialize($this->jsLayout);
    }

    /**
     * Get base url for block.
     *
     * @return string
     * @codeCoverageIgnore
     * @throws NoSuchEntityException
     */
    public function getBaseUrl()
    {
        return $this->_storeManager->getStore()->getBaseUrl();
    }

    /**
     * Retrieve serialized checkout config.
     *
     * @return bool|string
     * @since 100.2.0
     */
    public function getSerializedCheckoutConfig()
    {
        return  $this->serializer->serialize($this->getCheckoutConfig());
    }

    /**
     * Takes from config rows and parses them to json to be used in init
     * buttons.primary.fontSize='22'
     * buttons.primary.base.backgroundColor='#fff'
     *
     * @return string
     */
    public function getStyles()
    {
        $customCss = $this->config->getCustomCss();
        $styles = [];
        if ($customCss && count(explode("\n", $customCss)) > 0) {
            foreach (explode("\n", $customCss) as $row) {
                if (!trim($row) && !str_contains($row, '=')) {
                    continue;
                }
                [$path, $value] = explode('=', $row);
                $value = trim($value, " \t\n\r\0\x0B;'" . '"');

                if (!$value || !$path) {
                    continue;
                }

                $pathParts = explode('.', $path);
                $prevKey = false;
                foreach ($pathParts as $part) {
                    if ($prevKey === false) {
                        if (!isset($styles[$part])) {
                            $styles[$part] = [];
                        }
                        $prevKey = &$styles[$part];
                    } else {
                        if (!isset($prevKey[$part])) {
                            $prevKey[$part] = [];
                        }
                        $prevKey = &$prevKey[$part];
                    }
                }
                $prevKey = is_numeric($value) ? floatval($value) : $value;

                if (json_decode($value) !== null) {
                    $value = json_decode($value, true);
                    $prevKey = $value;
                }

                unset($prevKey);
            }
        }
        $stylesJson = json_encode($styles);
        if (!$stylesJson) {
            $stylesJson = '[]';
        }

        return $stylesJson;
    }
}
