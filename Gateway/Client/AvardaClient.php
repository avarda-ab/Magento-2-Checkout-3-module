<?php
/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_Checkout3
 */

namespace Avarda\Checkout3\Gateway\Client;

use Avarda\Checkout3\Gateway\Config\Config;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Laminas\Http\Request;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\FlagManager;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use Psr\Http\Message\ResponseInterface;

class AvardaClient
{
    protected Config $config;
    protected FlagManager $flagManager;
    protected Logger $logger;
    protected ?ResponseInterface $lastResponse = null;

    public function __construct(
        Config $config,
        FlagManager $flagManager,
        Logger $logger = null
    ) {
        $this->config = $config;
        $this->flagManager = $flagManager;
        $this->logger = $logger;
    }

    /**
     * @param $url string
     * @param $payload array
     * @param $headers array
     * @param $payloadType string
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function post($url, $payload, $headers, $payloadType = 'json'): ResponseInterface
    {
        $client = new Client();

        $response = $client->request(
            Request::METHOD_POST,
            $url,
            [
                $payloadType => $payload,
                'headers' => $headers,
                'http_errors' => false
            ]
        );

        $this->handleErrors($response);

        return $response;
    }

    /**
     * @param $url string
     * @param $payload array
     * @param $headers array
     * @param $payloadType string
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function put($url, $payload, $headers, $payloadType = 'json'): ResponseInterface
    {
        $client = new Client();

        $response = $client->request(
            Request::METHOD_PUT,
            $url,
            [
                $payloadType => $payload,
                'headers' => $headers,
                'http_errors' => false
            ]
        );

        $this->handleErrors($response);

        return $response;
    }

    /**
     * @param $url
     * @param $headers
     * @param array $additionalParameters
     * @return string
     * @throws GuzzleException
     */
    public function get($url, $headers, array $additionalParameters = []): string
    {
        $client = new Client();

        $response = $client->request(
            Request::METHOD_GET,
            $url,
            [
                'headers' => $headers,
                'query' => $additionalParameters
            ]
        );

        $this->handleErrors($response);

        return $response->getBody()->getContents();
    }

    /**
     * @param ResponseInterface $response
     * @return bool|null
     * @throws \RuntimeException
     */
    public function handleErrors(ResponseInterface $response): ?bool
    {
        $this->lastResponse = $response;
        switch ($response->getStatusCode()) {
            case 200:
            case 201:
            case 202:
            case 203:
            case 204:
            case 400:
            case 401:
            case 422:
                return true;
            case 403:
            case 404:
            case 405:
                $this->logger->debug([$response->getStatusCode(), (string)$response->getBody()]);
                throw new \RuntimeException('Error in request');
            case 500:
                $this->logger->debug([$response->getStatusCode(), (string)$response->getBody()]);
                throw new \RuntimeException('Avarda server error');
            default:
                throw new \RuntimeException('Unhandled response status code: ' . $response->getStatusCode());
        }
    }

    /**
     * @param TransferInterface $transferObject
     * @param bool $json
     * @param bool $token
     * @return array
     * @throws ClientException
     * @throws GuzzleException
     */
    public function buildHeader($transferObject, $json = true, $token = true): array
    {
        $header = [
            'Date' => date('r')
        ];

        if ($json) {
            $header['Accept'] = 'application/json';
            $header['Content-Type'] = 'application/json';
        }

        if ($token) {
            $header['Authorization'] = sprintf('Bearer %s', $this->getToken($transferObject));
        }

        return $header;
    }

    /**
     * @return ResponseInterface
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     * @param $transferObject TransferInterface
     * @return string
     * @throws ClientException|GuzzleException|NoSuchEntityException
     */
    private function getToken($transferObject)
    {
        // Set store id to get correctly scoped api keys
        if (isset($transferObject->getBody()['additional']['storeId'])) {
            $this->config->setStoreId($transferObject->getBody()['additional']['storeId']);
        }

        $useAltApi = false;
        $useAltApiFlagPart = '';
        if (isset($transferObject->getBody()['additional']['useAltApi'])
            && $transferObject->getBody()['additional']['useAltApi']
        ) {
            $useAltApi = true;
            $useAltApiFlagPart = 'alt_';
        }

        $tokenValidFlag = 'avarda_checkout3_token_valid_' . $useAltApiFlagPart . $this->config->getStoreId();
        $tokenValid = $this->flagManager->getFlagData($tokenValidFlag);
        if (!$tokenValid || $tokenValid < time()) {
            $authUrl = $this->config->getTokenUrl();

            if ($useAltApi) {
                $authParam = [
                    'clientId'     => $this->config->getAlternativeClientId(),
                    'clientSecret' => $this->config->getAlternativeClientSecret()
                ];
            } else {
                $authParam = [
                    'clientId'     => $this->config->getClientId(),
                    'clientSecret' => $this->config->getClientSecret()
                ];
            }

            $headers = [
                'content-type' => 'application/json-patch+json'
            ];
            $response = $this->post($authUrl, $authParam, $headers);
            $responseArray = json_decode((string)$response->getBody(), true);
            if (!is_array($responseArray)) {
                throw new ClientException(__(
                    'Authentication with avarda responded with invalid response (%1: %2)',
                    $response->getStatusCode(),
                    $response->getReasonPhrase()
                ));
            }

            if (isset($responseArray['error_description'])) {
                throw new ClientException(__('Authentication error, check avarda credentials'));
            }

            $this->flagManager->saveFlag($tokenValidFlag, strtotime($responseArray['tokenExpirationUtc']));
            $this->config->saveNewToken($responseArray['token'], $useAltApi);
        }

        return $this->config->getToken($useAltApi);
    }
}
