<?php

namespace Hostinger\Reach\Api\Handlers;

use Hostinger\Reach\Api\ApiKeyManager;
use Hostinger\Reach\Dto\PluginData;
use Hostinger\Reach\Functions;
use Hostinger\Reach\Integrations\Elementor\ElementorIntegration;
use Hostinger\Reach\Integrations\ImportManager;
use Hostinger\Reach\Integrations\Integration;
use Hostinger\Reach\Integrations\PluginManager;
use Hostinger\Reach\Integrations\Reach\ReachFormIntegration;
use Hostinger\Reach\Providers\IntegrationsProvider;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class IntegrationsApiHandler extends ApiHandler {
    public const INTEGRATIONS_OPTION_NAME = 'hostinger_reach_integrations';

    public PluginManager $plugin_manager;
    public ImportManager $import_manager;
    public ApiKeyManager $api_key_manager;

    public function __construct( Functions $functions, PluginManager $plugin_manager, ImportManager $import_manager, ApiKeyManager $api_key_manager ) {
        parent::__construct( $functions );
        $this->plugin_manager  = $plugin_manager;
        $this->import_manager  = $import_manager;
        $this->api_key_manager = $api_key_manager;
    }

    public static function get_integrations(): array {
        return apply_filters( 'hostinger_reach_integrations', IntegrationsProvider::INTEGRATIONS );
    }

    public function init_hooks(): void {
        if ( ! $this->api_key_manager->is_connected() ) {
            return;
        }
        add_action( 'init', array( $this, 'trigger_active_integrations' ) );
    }

    public function trigger_active_integrations(): void {
        do_action( 'hostinger_reach_integrations_loaded', $this->get_integrations_state() );
    }

    public function is_active( string $integration_name ): bool {
        $data = $this->get_integrations_state();

        return $data[ $integration_name ][ Integration::INTEGRATION_IS_ACTIVE ] ?? false;
    }

    public function is_import_enabled( string $integration_name ): bool {
        $data = $this->get_integrations_state();

        $is_active      = $data[ $integration_name ][ Integration::INTEGRATION_IS_ACTIVE ] ?? false;
        $import_enabled = $data[ $integration_name ][ Integration::INTEGRATION_IMPORT_ENABLED ] ?? false;

        return $is_active && $import_enabled;
    }

    public function get_integrations_handler(): WP_REST_Response {
        return $this->handle_response(
            array(
                'response' => array(
                    'code' => 200,
                ),
                'body'     => wp_json_encode( $this->get_integrations_data() ),
            )
        );
    }

    public function get_status_handler(): WP_REST_Response {
        $integrations_data = $this->get_integrations_state();
        $woocommerce_data  = $integrations_data['woocommerce'] ?? array();

        return $this->handle_response(
            array(
                'response' => array(
                    'code' => 200,
                ),
                'body'     => wp_json_encode(
                    array(
                        'woocommerce' => array(
                            'is_plugin_active'      => $woocommerce_data['is_plugin_active'] ?? false,
                            'is_integration_active' => $woocommerce_data[ Integration::INTEGRATION_IS_ACTIVE ] ?? false,
                            'is_installable'        => $woocommerce_data['is_installable'] ?? false,
                        ),
                    )
                ),
            )
        );
    }

    public function post_integrations_handler( WP_REST_Request $request ): WP_REST_Response {
        $is_active         = $request->get_param( Integration::INTEGRATION_IS_ACTIVE );
        $integration       = $request->get_param( 'integration' );
        $integrations_data = $this->get_integrations_state();
        $integration_data  = $integrations_data[ $integration ] ?? array();
        $has_download_url  = ! empty( $integration_data['download_url'] ?? false );
        $current_is_active = $integration_data[ Integration::INTEGRATION_IS_ACTIVE ] ?? false;

        if ( $current_is_active === $is_active ) {
            return $this->handle_response(
                array(
                    'response' => array(
                        'code' => 200,
                    ),
                )
            );
        }

        if ( $is_active && ! $this->can_activate_integration( $integration, $has_download_url ) ) {
            return $this->handle_wp_error(
                new WP_Error(
                    'insufficient_permissions',
                    __( 'You are not allowed to install or activate plugins', 'hostinger-reach' ),
                    array( 'status' => 403 )
                )
            );
        }

        $should_update = ! $is_active || $this->activate_integration( $integration, $has_download_url );

        if ( $should_update ) {
            $this->save_integration( $integration, array( Integration::INTEGRATION_IS_ACTIVE => $is_active ) );
        }

        return $this->handle_response(
            array(
                'response' => array(
                    'code' => 200,
                ),
            )
        );
    }

    public function post_import_handler( WP_REST_Request $request ): WP_REST_Response {
        $integrations = $request->get_param( 'integrations' );
        $this->import_manager->import( $integrations );

        return $this->handle_response(
            array(
                'response' => array(
                    'code' => 200,
                ),
            )
        );
    }

    public function get_import_handler( WP_REST_Request $request ): WP_REST_Response {
        $integration = $request->get_param( 'integration' );

        if ( ! $this->is_import_enabled( $integration ) ) {
            return $this->handle_wp_error(
                new WP_Error(
                    'import_not_available',
                    __( 'Importing is not available for this integration', 'hostinger-reach' )
                )
            );
        }

        $status = $this->import_manager->get_status( $integration );

        return $this->handle_response(
            array(
                'response' => array(
                    'code' => 200,
                ),
                'body'     => wp_json_encode( $status ),
            )
        );
    }

    public function activate_integration( string $integration_name, bool $can_install ): bool {
        $integration_class = self::get_integrations()[ $integration_name ];
        if ( ! isset( $integration_class ) ) {
            return false;
        }

        if ( $can_install ) {
            $installed = $this->plugin_manager->install( $integration_name );
            if ( is_wp_error( $installed ) ) {
                return false;
            }
        }

        $activated = $this->plugin_manager->activate( $integration_name );
        if ( $activated ) {
            do_action( 'hostinger_reach_integration_activated', $integration_name );
            update_option( Functions::HOSTINGER_REACH_HAS_USER_ACTION, true );
        }

        return $activated;
    }

    public function get_integration_data( string $integration_name ): array {
        $integrations_data = get_option( self::INTEGRATIONS_OPTION_NAME, array() );

        return $integrations_data[ $integration_name ] ?? array();
    }

    public function get_integrations_data(): array {
        $integrations = $this->get_integrations_state();

        foreach ( array_keys( $integrations ) as $integration_name ) {
            $integrations[ $integration_name ]['import_status'] = $this->import_manager->get_status( $integration_name );
        }

        return $integrations;
    }

    public function get_integrations_state(): array {
        $available_integrations       = self::get_integrations();
        $integrations_state           = get_option( self::INTEGRATIONS_OPTION_NAME, array() );
        $available_integrations_state = array_intersect_key( $integrations_state, $available_integrations );
        $integrations                 = array();

        foreach ( $available_integrations as $integration_name => $integration_class ) {
            $plugin = $this->plugin_manager->get_plugin( $integration_name );

            if ( ! $plugin instanceof PluginData ) {
                continue;
            }

            $is_hostinger_reach                = $integration_name === ReachFormIntegration::INTEGRATION_NAME;
            $is_elementor                      = $integration_name === ElementorIntegration::INTEGRATION_NAME;
            $integrations[ $integration_name ] = $plugin->to_array();
            $is_active_by_default              = $integrations[ $integration_name ][ Integration::INTEGRATION_IS_ACTIVE ] ?? false;
            $is_plugin_active                  = $is_hostinger_reach || $this->plugin_manager->is_active( $integration_name );
            $is_integration_active             = $available_integrations_state[ $integration_name ][ Integration::INTEGRATION_IS_ACTIVE ] ?? $is_active_by_default;
            $is_active                         = $is_plugin_active && $is_integration_active;
            $import_enabled                    = $integrations[ $integration_name ][ Integration::INTEGRATION_IMPORT_ENABLED ] ?? false;

            $integrations[ $integration_name ] = array_merge(
                $integrations[ $integration_name ],
                array(
                    'is_plugin_active'                      => $is_plugin_active,
                    Integration::INTEGRATION_IMPORT_ENABLED => $import_enabled,
                    Integration::INTEGRATION_IS_ACTIVE      => $is_hostinger_reach || ( $is_elementor && $is_plugin_active ) || $is_active,
                    'can_deactivate'                        => ! $is_hostinger_reach && ! $is_elementor,
                    'is_go_to_plugin_visible'               => ! $is_hostinger_reach,
                    'is_installable'                        => $this->plugin_manager->is_installed( $integration_name ) || ! empty( $integrations[ $integration_name ]['download_url'] ),
                )
            );

        }

        return $integrations;
    }

    private function can_activate_integration( string $integration_name, bool $can_install ): bool {
        $needs_install = $can_install && ! $this->plugin_manager->is_installed( $integration_name );
        if ( $needs_install && ! current_user_can( 'install_plugins' ) ) {
            return false;
        }

        if ( ! $this->plugin_manager->is_active( $integration_name ) && ! current_user_can( 'activate_plugins' ) ) {
            return false;
        }

        return true;
    }

    private function save_integration( string $integration_name, array $data ): bool {
        $integrations_data                      = $this->get_integrations_state();
        $integrations_data[ $integration_name ] = $data;

        return $this->save_integrations_data( $integrations_data );
    }


    private function save_integrations_data( array $data ): bool {
        return update_option( self::INTEGRATIONS_OPTION_NAME, $data );
    }
}
