<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Model\Ui;

use Avarda\Checkout3\Gateway\Config\Config;
use Magento\Checkout\Model\ConfigProviderInterface;

/**
 * Class ConfigProvider
 */
class ConfigProviderBase implements ConfigProviderInterface
{
    const CODE = 'avarda_checkout3_checkout';

    /** @var Config */
    protected $config;

    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        $active = $this->config->isActive();

        $config = [
            'payment' => [
                self::CODE => [
                    'isActive' => $active,
                ],
            ],
        ];

        return $config;
    }
}
