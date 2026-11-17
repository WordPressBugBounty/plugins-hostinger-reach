<?php

namespace Hostinger\Reach\Jobs;

use Hostinger\Reach\Dto\ReachProduct;
use WC_Product;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class ProductSyncJob extends AbstractStoreSyncJob {
    public const JOB_NAME  = 'product_sync';
    public const POST_TYPE = 'product';

    public function init(): void {
        parent::init();

        add_action( 'woocommerce_new_product', array( $this, 'handle_product_saved' ), 10, 1 );
        add_action( 'woocommerce_update_product', array( $this, 'handle_product_saved' ), 10, 1 );
        add_action( 'woocommerce_update_product_variation', array( $this, 'handle_variation_saved' ), 10, 1 );
        add_action( 'wp_trash_post', array( $this, 'handle_product_removed' ), 10, 1 );
        add_action( 'before_delete_post', array( $this, 'handle_product_removed' ), 10, 1 );
    }

    public function get_name(): string {
        return self::JOB_NAME;
    }

    public function handle_product_saved( int $product_id ): void {
        $this->handle_entity_saved( $product_id );
    }

    public function handle_variation_saved( int $variation_id ): void {
        $parent_id = (int) wp_get_post_parent_id( $variation_id );

        if ( $parent_id > 0 ) {
            $this->handle_product_saved( $parent_id );
        }
    }

    public function handle_product_removed( mixed $post_id ): void {
        $this->handle_entity_removed( $post_id );
    }

    protected function get_id_arg(): string {
        return 'product_id';
    }

    protected function get_post_type(): string {
        return self::POST_TYPE;
    }

    protected function build_remote_entity( int $id ): ?object {
        $product = wc_get_product( $id );

        if ( ! $product instanceof WC_Product || $product->get_status() !== self::PUBLISHED_STATUS ) {
            return null;
        }

        return new ReachProduct( $product );
    }

    protected function create_remote( object $entity ): WP_Error|array {
        return $this->reach_store_api_handler->create_product( $entity );
    }

    protected function update_remote( object $entity ): WP_Error|array {
        return $this->reach_store_api_handler->update_product( $entity );
    }

    protected function delete_remote( int $id ): WP_Error|array {
        return $this->reach_store_api_handler->delete_product( (string) $id );
    }
}
