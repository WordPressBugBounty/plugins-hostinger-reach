<?php

namespace Hostinger\Reach\Jobs;

use Hostinger\Reach\Dto\ReachCoupon;
use WC_Coupon;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class CouponSyncJob extends AbstractStoreSyncJob {
    public const JOB_NAME     = 'coupon_sync';
    public const POST_TYPE    = 'shop_coupon';
    public const TRASH_STATUS = 'trash';

    public function init(): void {
        parent::init();

        add_action( 'woocommerce_new_coupon', array( $this, 'handle_coupon_saved' ), 10, 1 );
        add_action( 'woocommerce_update_coupon', array( $this, 'handle_coupon_saved' ), 10, 1 );
        add_action( 'wp_trash_post', array( $this, 'handle_coupon_removed' ), 10, 1 );
        add_action( 'before_delete_post', array( $this, 'handle_coupon_removed' ), 10, 1 );
    }

    public function get_name(): string {
        return self::JOB_NAME;
    }

    public function handle_coupon_saved( int $coupon_id ): void {
        $this->handle_entity_saved( $coupon_id );
    }

    public function handle_coupon_removed( mixed $post_id ): void {
        $this->handle_entity_removed( $post_id );
    }

    protected function get_id_arg(): string {
        return 'coupon_id';
    }

    protected function get_post_type(): string {
        return self::POST_TYPE;
    }

    protected function build_remote_entity( int $id ): ?object {
        if ( get_post_type( $id ) !== self::POST_TYPE || get_post_status( $id ) === self::TRASH_STATUS ) {
            return null;
        }

        $coupon = $this->load_coupon( $id );

        if ( ! $coupon instanceof WC_Coupon ) {
            return null;
        }

        return new ReachCoupon( $coupon );
    }

    protected function load_coupon( int $id ): ?WC_Coupon {
        if ( ! class_exists( 'WC_Coupon' ) ) {
            return null;
        }

        $coupon = new WC_Coupon( $id );

        if ( $coupon->get_id() !== $id ) {
            return null;
        }

        return $coupon;
    }

    protected function create_remote( object $entity ): WP_Error|array {
        return $this->reach_store_api_handler->create_coupon( $entity );
    }

    protected function update_remote( object $entity ): WP_Error|array {
        return $this->reach_store_api_handler->update_coupon( $entity );
    }

    protected function delete_remote( int $id ): WP_Error|array {
        return $this->reach_store_api_handler->delete_coupon( (string) $id );
    }
}
