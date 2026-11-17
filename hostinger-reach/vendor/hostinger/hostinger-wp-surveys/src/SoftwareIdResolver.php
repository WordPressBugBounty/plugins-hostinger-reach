<?php

namespace Hostinger\Surveys;

use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Utils as Helper;

defined('ABSPATH') || exit;

class SoftwareIdResolver
{
    public const SOFTWARE_ID_OPTION      = 'hostinger_sfid';
    public const INSTALLATIONS_ENDPOINT  = '/api/v1/installations';
    public const LOOKUP_FAILED_TRANSIENT = 'hts_sfid_lookup_failed';
    public const LOOKUP_FAILED_TTL       = 3600;

    private const PREVIEW_HOST_SUFFIX = '.hostingersite.com';

    private Helper $helper;
    private Config $configHandler;
    private Client $client;

    public function __construct(Helper $helper, Config $configHandler, Client $client)
    {
        $this->helper        = $helper;
        $this->configHandler = $configHandler;
        $this->client        = $client;
    }

    public function getSoftwareId(): ?string
    {
        if (defined('HOSTINGER_SOFTWARE_ID_OVERRIDE') && ! empty(HOSTINGER_SOFTWARE_ID_OVERRIDE)) {
            return (string) HOSTINGER_SOFTWARE_ID_OVERRIDE;
        }

        $softwareId = get_option(self::SOFTWARE_ID_OPTION);

        if (! empty($softwareId)) {
            return (string) $softwareId;
        }

        $configSoftwareId = $this->configHandler->getConfigValue('software_id', '');

        if (! empty($configSoftwareId)) {
            update_option(self::SOFTWARE_ID_OPTION, $configSoftwareId, true);

            return (string) $configSoftwareId;
        }

        return $this->lookupSoftwareId();
    }

    private function lookupSoftwareId(): ?string
    {
        if (get_transient(self::LOOKUP_FAILED_TRANSIENT)) {
            return null;
        }

        $domain = $this->getCurrentDomain();

        if ($domain === '') {
            return null;
        }

        $response = $this->client->get(self::INSTALLATIONS_ENDPOINT, [ 'domain' => $domain ], [], 10);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            set_transient(self::LOOKUP_FAILED_TRANSIENT, true, self::LOOKUP_FAILED_TTL);

            return null;
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($decoded['data'][0]['id'])) {
            set_transient(self::LOOKUP_FAILED_TRANSIENT, true, self::LOOKUP_FAILED_TTL);

            return null;
        }

        $softwareId = (string) $decoded['data'][0]['id'];

        update_option(self::SOFTWARE_ID_OPTION, $softwareId, true);

        return $softwareId;
    }

    private function getCurrentDomain(): string
    {
        if (defined('HOSTINGER_DOMAIN_OVERRIDE') && ! empty(HOSTINGER_DOMAIN_OVERRIDE)) {
            return (string) HOSTINGER_DOMAIN_OVERRIDE;
        }

        $host = (string) preg_replace('/^www\./', '', (string) $this->helper->getHostInfo());

        if ($host !== '' && substr($host, -strlen(self::PREVIEW_HOST_SUFFIX)) === self::PREVIEW_HOST_SUFFIX) {
            $siteUrl  = (string) preg_replace('#^https?://#', '', (string) get_option('siteurl', ''));
            $realHost = (string) preg_replace('/^www\./', '', rtrim($siteUrl, '/'));

            if ($realHost !== '') {
                return $realHost;
            }
        }

        return $host;
    }
}
