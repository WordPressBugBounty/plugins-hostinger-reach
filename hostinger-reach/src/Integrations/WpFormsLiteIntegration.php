<?php

namespace Hostinger\Reach\Integrations;

use Hostinger\Reach\Api\Handlers\IntegrationsApiHandler;
use Hostinger\Reach\Api\Handlers\ReachApiHandler;

if ( ! DEFINED( 'ABSPATH' ) ) {
    exit;
}

class WpFormsLiteIntegration extends Integration implements IntegrationInterface {

    public const INTEGRATION_NAME = 'wpforms-lite';
    protected ReachApiHandler $reach_api_handler;
    protected IntegrationsApiHandler $integrations_api_handler;

    public function __construct( ReachApiHandler $reach_api_handler, IntegrationsApiHandler $integrations_api_handler ) {
        $this->reach_api_handler        = $reach_api_handler;
        $this->integrations_api_handler = $integrations_api_handler;
    }

    public function init(): void {
        if ( $this->integrations_api_handler->is_active( self::INTEGRATION_NAME ) ) {
            add_action( 'wpforms_process_complete', array( $this, 'handle_submission' ), 10, 3 );
            add_filter( 'hostinger_reach_forms', array( $this, 'load_forms' ), 10, 2 );
            add_filter( 'hostinger_reach_after_form_state_is_set', array( $this, 'on_form_activation_change' ), 10, 3 );
        }
    }

    public function handle_submission( array $fields, array $entry, array $form_data ): void {
        if ( ! $this->is_form_enabled( $form_data['id'] ) ) {
            return;
        }

        $email = $this->find_field( $fields, 'email' );
        if ( $email ) {
            $response = $this->reach_api_handler->post_contact(
                array(
                    // translators: %s - form id.
                    'group' => $form_data['settings']['form_title'] ?? sprintf( __( 'WP Forms Lite %s', 'hostinger-reach' ), $form_data['id'] ),
                    'email' => $email,
                )
            );

            if ( $response->get_status() < 300 ) {
                $this->update_form_submissions( $form_data['id'] );
            }
        }
    }

    public function find_field( array $fields, string $type ): string {
        foreach ( $fields as $field ) {
            if ( isset( $field['type'] ) && $field['type'] === $type ) {
                if ( isset( $field['value'] ) ) {
                    return $field['value'];
                }
            }
        }

        return '';
    }


    public static function get_name(): string {
        return self::INTEGRATION_NAME;
    }


    public function get_post_type(): string {
        return 'wpforms';
    }
}
