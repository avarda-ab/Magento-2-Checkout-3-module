<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Gateway\Client;

use Laminas\Http\Request;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;

class AvardaGatewayClient implements ClientInterface
{
    protected AvardaClientFactory $avardaClient;
    protected ConverterInterface $converter;
    protected Logger $logger;

    public function __construct(
        AvardaClientFactory $avardaClient,
        Logger $logger,
        ConverterInterface $converter
    ) {
        $this->avardaClient = $avardaClient;
        $this->converter    = $converter;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $log = [
            'request' => $transferObject->getBody(),
            'request_uri' => $transferObject->getUri()
        ];
        $client = $this->avardaClient->create();

        $headers = $client->buildHeader($transferObject);
        $uri = $this->getUri($transferObject);
        $body = $this->getBody($transferObject);

        try {
            switch ($transferObject->getMethod()) {
                case Request::METHOD_GET:
                    $result = $client->get($uri, $headers);
                    break;
                case Request::METHOD_POST:
                    $response = $client->post($uri, $body, $headers);
                    $result = $this->converter->convert($response);
                    break;
                case Request::METHOD_PUT:
                    $response = $client->put($uri, $body, $headers);
                    $result = $this->converter->convert($response);
                    break;
                default:
                    throw new \LogicException(
                        sprintf(
                            'Unsupported HTTP method %s',
                            $transferObject->getMethod()
                        )
                    );
            }

            $result = is_array($result) ? $result : json_decode($result ?? '', true);
            $log['response'] = $result;
        } catch (\RuntimeException $e) {
            $this->logger->debug($log);
            throw new \Magento\Payment\Gateway\Http\ClientException(
                __($e->getMessage())
            );
        }

        $this->logger->debug($log);

        return $result;
    }

    /**
     * @param $transferObject TransferInterface
     */
    private function getUri($transferObject)
    {
        $uri = $transferObject->getUri();
        $parts = $transferObject->getBody()['additional'] ?? [];
        foreach ($parts as $part => $value) {
            $uri = str_replace('{' . $part . '}', $value, $uri);
        }

        return $uri;
    }

    /**
     * @param $transferObject TransferInterface
     * @return array
     */
    private function getBody($transferObject)
    {
        $body = $transferObject->getBody();
        unset($body['additional']);
        return $body;
    }
}
