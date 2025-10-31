<?php

namespace Avarda\Checkout3\Model\Config\Backend;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Csp\Api\PolicyCollectorInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\StoreManagerInterface;

class CspUrlCheck extends Field
{
    protected PolicyCollectorInterface $cspWhitelistCollector;
    protected StoreManagerInterface $storeManager;

    public function __construct(
        Context $context,
        PolicyCollectorInterface $cspWhitelistCollector,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->cspWhitelistCollector = $cspWhitelistCollector;
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        $html = parent::_getElementHtml($element);
        $value = $element->getValue();

        if (!$value) {
            return $html;
        }

        // Extract all URLs from the textarea content
        $urls = $this->extractUrls($value);
        if (empty($urls)) {
            return $html;
        }

        // Check CSP compliance for each URL
        $compliantUrls = [];
        $nonCompliantUrls = [];
        foreach ($urls as $url) {
            if ($this->isUrlCspCompliant($url, 'img-src')) {
                $compliantUrls[] = $url;
            } else {
                $nonCompliantUrls[] = $url;
            }
        }

        // Display results
        $html .= '<div style="margin-top: 10px;">';

        if (!empty($nonCompliantUrls)) {
            $html .= '<div style="padding: 10px; background-color: #fff5f5; border: 1px solid #e22626; border-radius: 4px;">';
            $html .= '<span style="color: #e22626; font-weight: bold;">⚠ Non-Compliant URLs (' . count($nonCompliantUrls) . '):</span><br>';
            $html .= '<small>These URLs are not whitelisted in CSP policy</small>';
            $html .= '<ul style="margin: 5px 0; padding-left: 20px;">';
            foreach ($nonCompliantUrls as $url) {
                $html .= '<li style="color: #e22626; font-size: 12px; word-break: break-all;">' . htmlspecialchars($url) . '</li>';
            }
            $html .= '</ul>';
        } elseif (!empty($compliantUrls)) {
            $html .= '<div style="padding: 10px; background-color: #f0f9ff; border: 1px solid #3d9970; border-radius: 4px; margin-bottom: 10px;">';
            $html .= '<span style="color: #3d9970; font-weight: bold;">✓ URLs are CSP Compliant</span><br>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Extract all URLs from text content
     *
     * @param $text
     * @return array
     */
    protected function extractUrls($text)
    {
        $urls = [];

        preg_match_all(
            '#\b(?:https?://)[^\s<>"\']+#i',
            $text,
            $matches1
        );
        if (!empty($matches1[0])) {
            $urls = array_merge($urls, $matches1[0]);
        }

        // Filter and clean URLs
        $cleanUrls = [];
        foreach ($urls as $url) {
            $url = trim($url);
            // Validate URL format
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $cleanUrls[] = $url;
            }
        }

        // Remove duplicates and return
        return array_unique($cleanUrls);
    }

    /**
     * @param $url
     * @param $policyId
     * @return bool
     */
    protected function isUrlCspCompliant($url, $policyId)
    {
        if (empty($url)) {
            return true;
        }

        $parsedUrl = parse_url($url);
        if (!$parsedUrl || !isset($parsedUrl['host'])) {
            return false;
        }

        $domain = ($parsedUrl['scheme'] ?? 'https') . '://' . $parsedUrl['host'];
        // Check if URL matches Magento base URL
        if ($this->matchesMagentoBaseUrl($parsedUrl['host'])) {
            return true;
        }

        $policies = $this->cspWhitelistCollector->collect([]);
        foreach ($policies as $policy) {
            if ($policy->getId() === $policyId) {
                $value = $policy->getValue();
                if ($this->matchesCspValue($domain, $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param $url
     * @param $cspValue
     * @return false|int
     */
    protected function matchesCspValue($url, $cspValue)
    {
        // Handle wildcards and patterns
        $pattern = str_replace(['*', '.'], ['.*', '\.'], $cspValue);
        return preg_match('#^' . $pattern . '#', $url);
    }

    /**
     * @param $host
     * @return bool
     */
    protected function matchesMagentoBaseUrl($host)
    {
        try {
            $baseUrl = $this->storeManager->getStore()->getBaseUrl();
            $parsedBaseUrl = parse_url($baseUrl);
            if (!$parsedBaseUrl || !isset($parsedBaseUrl['host'])) {
                return false;
            }
            return $host == $parsedBaseUrl['host'];
        } catch (\Exception $e) {
            return false;
        }
    }
}
