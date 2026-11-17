<?php

namespace Hostinger\Surveys;

use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Utils as Helper;

defined('ABSPATH') || exit;

class Rest
{
    public const PROXY_REST_URI            = 'https://wh-wordpress-proxy-api.hostinger.io';
    public const PROXY_CONFIG_KEY          = 'base_proxy_rest_uri';
    public const SUBMIT_SURVEY             = '/api/v1/installations/%s/survey';
    public const CLIENT_SURVEY_ELIGIBILITY = '/api/v1/installations/%s/survey/eligibility';
    public const CLIENT_SURVEY_IDENTIFIER  = 'customer_satisfaction_score';

    private Client $client;
    private ?Client $proxyClient = null;
    private ?SoftwareIdResolver $softwareIdResolver = null;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getProxyClient(): Client
    {
        if ($this->proxyClient === null) {
            $this->proxyClient = new Client(
                ( new Config() )->getConfigValue(self::PROXY_CONFIG_KEY, self::PROXY_REST_URI),
                $this->client->get_default_headers()
            );
        }

        return $this->proxyClient;
    }

    public function setProxyClient(Client $proxyClient): void
    {
        $this->proxyClient = $proxyClient;
    }

    public function getSoftwareIdResolver(): SoftwareIdResolver
    {
        if ($this->softwareIdResolver === null) {
            $this->softwareIdResolver = new SoftwareIdResolver(new Helper(), new Config(), $this->getProxyClient());
        }

        return $this->softwareIdResolver;
    }

    public function setSoftwareIdResolver(SoftwareIdResolver $softwareIdResolver): void
    {
        $this->softwareIdResolver = $softwareIdResolver;
    }

    public function isClientEligible(): bool
    {
        $softwareId = $this->getSoftwareIdResolver()->getSoftwareId();

        if (empty($softwareId)) {
            return false;
        }

        $response = $this->getProxyClient()->get(
            $this->endpoint(self::CLIENT_SURVEY_ELIGIBILITY, $softwareId),
            [
                'identifier' => self::CLIENT_SURVEY_IDENTIFIER,
            ],
            [],
            10
        );

        $data = $this->getResult($response);

        return is_array($data) && ( $data['eligible'] ?? false ) === true;
    }

    public function submitSurveyData(array $data): bool
    {
        $softwareId = $this->getSoftwareIdResolver()->getSoftwareId();

        if (empty($softwareId)) {
            return false;
        }

        $response = $this->getProxyClient()->post(
            $this->endpoint(self::SUBMIT_SURVEY, $softwareId),
            wp_json_encode($data),
            [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ]
        );

        $decoded = $this->decodeResponse($response);

        if ($decoded['response_code'] < 200 || $decoded['response_code'] >= 300) {
            $this->logError($response, $decoded);

            return false;
        }

        return true;
    }

    /**
     * @param array|\WP_Error $response
     *
     * @return mixed
     */
    public function getResult($response)
    {
        $data = $this->decodeResponse($response);

        if ($data['response_code'] !== 200) {
            $this->logError($response, $data);

            return false;
        }

        return $data['response_data']['data'];
    }

    /**
     * @param array|\WP_Error $response
     *
     * @return array
     */
    public function decodeResponse($response): array
    {
        $response_body = wp_remote_retrieve_body($response);
        $response_code = (int) wp_remote_retrieve_response_code($response);
        $response_data = json_decode($response_body, true);

        if (! is_array($response_data)) {
            $response_data = [ 'data' => null ];
        }

        return [
            'response_code' => $response_code,
            'response_data' => $response_data,
            'response_body' => $response_body,
        ];
    }

    private function endpoint(string $template, string $softwareId): string
    {
        return sprintf($template, rawurlencode($softwareId));
    }

    /**
     * @param array|\WP_Error $response
     */
    private function logError($response, array $decoded): void
    {
        $detail = is_wp_error($response)
            ? $response->get_error_message()
            : sprintf('HTTP %d: %s', $decoded['response_code'], $decoded['response_body']);

        error_log('Hostinger surveys API error: ' . $detail);
    }
}
