<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */
namespace Avarda\Checkout3\Gateway\Client;

use Avarda\Checkout3\Gateway\Config\Config;
use Laminas\Http\Request;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

class TransferFactory implements TransferFactoryInterface
{
    const BEARER_AUTHENTICATION_FORMAT = 'Bearer %s';

    protected EncryptorInterface $encryptor;
    protected TransferBuilder $transferBuilder;
    protected Config $config;
    protected string $method;
    protected string $uri;

    public function __construct(
        EncryptorInterface $encryptor,
        TransferBuilder $transferBuilder,
        Config $config,
        $method = Request::METHOD_POST,
        $uri = ''
    ) {
        $this->encryptor = $encryptor;
        $this->transferBuilder = $transferBuilder;
        $this->config = $config;
        $this->method = $method;
        $this->uri = $uri;
    }

    /**
     * Builds gateway transfer object
     *
     * @param array $request
     * @return TransferInterface
     */
    public function create(array $request)
    {
        // @todo this is redundant headers will be set in AvardaClient
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => $this->getAuthorization(),
        ];

        return $this->transferBuilder
            ->setMethod($this->method)
            ->setUri($this->getUri())
            ->setHeaders($headers)
            ->setBody($request)
            ->build();
    }

    /**
     * Generate basic authorization string
     *
     * @return string
     */
    protected function getAuthorization()
    {
        $token = $this->config->getToken();
        return sprintf(
            self::BEARER_AUTHENTICATION_FORMAT,
            $token
        );
    }

    /**
     * Get URI for the request to call
     *
     * @return string
     */
    public function getUri()
    {
        return $this->config->getApiUrl() . $this->uri;
    }
}
