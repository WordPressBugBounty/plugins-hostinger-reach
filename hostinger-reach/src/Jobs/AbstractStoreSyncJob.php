<?php

namespace Hostinger\Reach\Jobs;

use Hostinger\Reach\Api\Handlers\IntegrationsApiHandler;
use Hostinger\Reach\Api\Handlers\ReachApiHandler;
use Hostinger\Reach\Api\Handlers\ReachStoreApiHandler;
use Hostinger\Reach\Integrations\WooCommerce\WooCommerceIntegration;
use Throwable;
use WP_Error;
use WP_Http;
use WP_Post;

defined( 'ABSPATH' ) || exit;

abstract class AbstractStoreSyncJob extends AbstractJob {
    public const SYNCED_META_KEY  = '_hostinger_reach_synced_at';
    public const PUBLISHED_STATUS = 'publish';

    protected ReachStoreApiHandler $reach_store_api_handler;
    protected IntegrationsApiHandler $integrations_api_handler;

    public function __construct( ActionScheduler $action_scheduler, ReachApiHandler $reach_api_handler, ReachStoreApiHandler $reach_store_api_handler, IntegrationsApiHandler $integrations_api_handler ) {
        parent::__construct( $action_scheduler, $reach_api_handler );
        $this->reach_store_api_handler  = $reach_store_api_handler;
        $this->integrations_api_handler = $integrations_api_handler;
    }

    public function can_schedule( array $args = array() ): bool {
        return parent::can_schedule( $args ) && $this->is_enabled();
    }

    public function schedule( array $args = array() ): void {
        if ( ! $this->can_schedule( $args ) ) {
            return;
        }

        $this->action_scheduler->schedule_immediate( $this->get_process_item_hook(), array( $args ) );
    }

    public function is_enabled(): bool {
        return function_exists( 'WC' ) && $this->integrations_api_handler->is_active( WooCommerceIntegration::INTEGRATION_NAME );
    }

    protected function is_running( ?array $args = array() ): bool {
        return $this->action_scheduler->has_pending_action( $this->get_process_item_hook(), array( $args ) );
    }

    protected function process_items( array $args ): void {
        $id = (int) ( $args[ $this->get_id_arg() ] ?? 0 );

        if ( $id <= 0 ) {
            return;
        }

        try {
            $this->sync( $id );
        } catch ( Throwable $throwable ) {
            $this->log_error( $id, $throwable->getMessage() );
        }
    }

    protected function handle_entity_saved( int $id ): void {
        if ( ! $this->is_published( $id ) && ! $this->is_synced( $id ) ) {
            return;
        }

        $this->schedule_safely( $id );
    }

    protected function handle_entity_removed( mixed $post_id ): void {
        $post_id = (int) ( $post_id instanceof WP_Post ? $post_id->ID : $post_id );

        if ( $post_id <= 0 || get_post_type( $post_id ) !== $this->get_post_type() || ! $this->is_synced( $post_id ) ) {
            return;
        }

        $this->schedule_safely( $post_id );
    }

    abstract protected function get_id_arg(): string;

    abstract protected function get_post_type(): string;

    /**
     * Returns null when the entity no longer exists or must be removed from Reach.
     */
    abstract protected function build_remote_entity( int $id ): ?object;

    abstract protected function create_remote( object $entity ): WP_Error|array;

    abstract protected function update_remote( object $entity ): WP_Error|array;

    abstract protected function delete_remote( int $id ): WP_Error|array;

    private function sync( int $id ): void {
        $entity = $this->build_remote_entity( $id );

        if ( $entity === null ) {
            $this->delete( $id );

            return;
        }

        $this->upsert( $id, $entity );
    }

    private function upsert( int $id, object $entity ): void {
        $response = $this->write( $entity, $this->is_synced( $id ) );

        if ( ! $this->reach_store_api_handler->is_successful( $response ) ) {
            $this->log_error( $id, $this->get_error_message( $response ) );

            return;
        }

        update_post_meta( $id, self::SYNCED_META_KEY, time() );
    }

    private function write( object $entity, bool $is_synced ): WP_Error|array {
        if ( $is_synced ) {
            $response = $this->update_remote( $entity );

            if ( $this->reach_store_api_handler->get_response_code( $response ) !== WP_Http::NOT_FOUND ) {
                return $response;
            }
        }

        $response = $this->create_remote( $entity );

        if ( $this->reach_store_api_handler->get_response_code( $response ) === WP_Http::CONFLICT ) {
            return $this->update_remote( $entity );
        }

        return $response;
    }

    private function delete( int $id ): void {
        $response = $this->delete_remote( $id );
        $code     = $this->reach_store_api_handler->get_response_code( $response );

        if ( ! $this->reach_store_api_handler->is_successful( $response ) && $code !== WP_Http::NOT_FOUND ) {
            $this->log_error( $id, $this->get_error_message( $response ) );

            return;
        }

        delete_post_meta( $id, self::SYNCED_META_KEY );
    }

    private function schedule_safely( int $id ): void {
        try {
            $this->schedule( array( $this->get_id_arg() => $id ) );
        } catch ( Throwable $throwable ) {
            $this->log_error( $id, $throwable->getMessage() );
        }
    }

    private function is_published( int $id ): bool {
        return get_post_status( $id ) === self::PUBLISHED_STATUS;
    }

    private function is_synced( int $id ): bool {
        return (bool) get_post_meta( $id, self::SYNCED_META_KEY, true );
    }

    private function get_error_message( WP_Error|array $response ): string {
        if ( is_wp_error( $response ) ) {
            return $response->get_error_message();
        }

        return 'HTTP ' . $this->reach_store_api_handler->get_response_code( $response ) . ' ' . wp_remote_retrieve_body( $response );
    }

    private function log_error( int $id, string $message ): void {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return;
        }

        error_log( 'Hostinger Reach: Failed to sync ' . $this->get_post_type() . ' ' . $id . ' - ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging only.
    }
}
