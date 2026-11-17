<?php

namespace Hostinger\Amplitude;

use Hostinger\WpHelper\Requests\Client;

class ExperimentsManager
{
    public const EXPERIMENTS_ENDPOINT = '/api/v1/experiments/variants';

    private Client $proxyClient;

    public function __construct(Client $proxyClient)
    {
        $this->proxyClient = $proxyClient;
    }

    /**
     * @return array<string, array{key: string, payload?: mixed}> Variants keyed by flag key.
     *
     * @throws \RuntimeException
     */
    public function getExperiments(): array
    {
        $response = $this->proxyClient->get(self::EXPERIMENTS_ENDPOINT);

        if (is_wp_error($response)) {
            throw new \RuntimeException($response->get_error_message());
        }

        $responseCode = (int) wp_remote_retrieve_response_code($response);
        if ($responseCode < 200 || $responseCode >= 300) {
            throw new \RuntimeException(
                'Unexpected response code ' . $responseCode . ' from ' . self::EXPERIMENTS_ENDPOINT
            );
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        if (! is_array($decoded) || ! is_array($decoded['data'] ?? null)) {
            throw new \RuntimeException('Invalid response from ' . self::EXPERIMENTS_ENDPOINT);
        }

        return $this->mapVariants($decoded['data']);
    }

    public function mapVariants(array $variants): array
    {
        $experiments = [];

        foreach ($variants as $variant) {
            if (! is_array($variant) || empty($variant['flagKey']) || ! isset($variant['key'])) {
                continue;
            }

            $experiment = [ 'key' => (string) $variant['key'] ];

            if (isset($variant['payload'])) {
                $experiment['payload'] = $variant['payload'];
            }

            $experiments[ (string) $variant['flagKey'] ] = $experiment;
        }

        return $experiments;
    }
}
