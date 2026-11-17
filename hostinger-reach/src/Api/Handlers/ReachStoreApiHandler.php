<?php

namespace Hostinger\Reach\Api\Handlers;

use Hostinger\Reach\Dto\ReachCoupon;
use Hostinger\Reach\Dto\ReachProduct;
use Hostinger\Reach\Functions;
use WP_Error;
use WP_Http;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ReachStoreApiHandler extends ApiHandler {
    public const STORE_OPTION_NAME = 'hostinger_reach_store';
    public const PLATFORM_TYPE     = 'wordpress';
    public const RESOURCE_PRODUCTS = 'products';
    public const RESOURCE_COUPONS  = 'coupons';

    private const REQUEST_TIMEOUT = 30;

    private ReachApiHandler $reach_api_handler;

    public function __construct( Functions $functions, ReachApiHandler $reach_api_handler ) {
        parent::__construct( $functions );
        $this->reach_api_handler = $reach_api_handler;
        $this->api_base_name     = $reach_api_handler->get_api_basename();
    }

    public function get_default_headers(): array {
        return $this->reach_api_handler->get_default_headers();
    }

    public function create_product( ReachProduct $product ): WP_Error|array {
        return $this->create_entity( self::RESOURCE_PRODUCTS, $product->to_array() );
    }

    public function update_product( ReachProduct $product ): WP_Error|array {
        return $this->update_entity( self::RESOURCE_PRODUCTS, $product->get_product_id(), $product->to_array() );
    }

    public function delete_product( string $product_id ): WP_Error|array {
        return $this->delete_entity( self::RESOURCE_PRODUCTS, $product_id );
    }

    public function create_coupon( ReachCoupon $coupon ): WP_Error|array {
        return $this->create_entity( self::RESOURCE_COUPONS, $coupon->to_array() );
    }

    public function update_coupon( ReachCoupon $coupon ): WP_Error|array {
        return $this->update_entity( self::RESOURCE_COUPONS, $coupon->get_coupon_id(), $coupon->to_array() );
    }

    public function delete_coupon( string $coupon_id ): WP_Error|array {
        return $this->delete_entity( self::RESOURCE_COUPONS, $coupon_id );
    }

    public function get_response_code( WP_Error|array $response ): int {
        if ( is_wp_error( $response ) ) {
            return 0;
        }

        return (int) wp_remote_retrieve_response_code( $response );
    }

    public function is_successful( WP_Error|array $response ): bool {
        $code = $this->get_response_code( $response );

        return $code >= WP_Http::OK && $code < WP_Http::MULTIPLE_CHOICES;
    }

    public function get_store_path(): string {
        $store = $this->get_cached_store();

        if ( empty( $store ) ) {
            $store = $this->resolve_store();
        }

        if ( empty( $store ) ) {
            return '';
        }

        return 'profiles/' . rawurlencode( $store['profile_uuid'] ) . '/stores/' . rawurlencode( $store['store_uuid'] ) . '/';
    }

    public function clear_store(): void {
        delete_option( self::STORE_OPTION_NAME );
    }

    private function create_entity( string $resource_type, array $data ): WP_Error|array {
        $endpoint = $this->get_store_endpoint( $resource_type );

        if ( is_wp_error( $endpoint ) ) {
            return $endpoint;
        }

        $response = $this->post( $endpoint, $data, array(), self::REQUEST_TIMEOUT );

        if ( $this->get_response_code( $response ) === WP_Http::NOT_FOUND ) {
            $this->clear_store();
        }

        return $response;
    }

    private function update_entity( string $resource_type, string $id, array $data ): WP_Error|array {
        $endpoint = $this->get_store_endpoint( $resource_type . '/' . rawurlencode( $id ) );

        if ( is_wp_error( $endpoint ) ) {
            return $endpoint;
        }

        return $this->put( $endpoint, $data, array(), self::REQUEST_TIMEOUT );
    }

    private function delete_entity( string $resource_type, string $id ): WP_Error|array {
        $endpoint = $this->get_store_endpoint( $resource_type . '/' . rawurlencode( $id ) );

        if ( is_wp_error( $endpoint ) ) {
            return $endpoint;
        }

        return $this->delete( $endpoint, array(), array(), self::REQUEST_TIMEOUT );
    }

    private function get_store_endpoint( string $endpoint ): WP_Error|string {
        if ( ! $this->reach_api_handler->is_connected() ) {
            return new WP_Error( 'not_connected', $this->reach_api_handler->get_not_connected_error_message() );
        }

        $store_path = $this->get_store_path();

        if ( $store_path === '' ) {
            return new WP_Error( 'store_not_found', 'Reach store could not be resolved' );
        }

        return $store_path . $endpoint;
    }

    private function get_cached_store(): array {
        $store = get_option( self::STORE_OPTION_NAME, array() );

        if ( ! is_array( $store ) || empty( $store['profile_uuid'] ) || empty( $store['store_uuid'] ) ) {
            return array();
        }

        if ( ( $store['token_hash'] ?? '' ) !== $this->get_token_hash() || ( $store['domain'] ?? '' ) !== $this->get_domain() ) {
            return array();
        }

        return $store;
    }

    private function resolve_store(): array {
        $connection = $this->find_website_connection();

        if ( empty( $connection ) ) {
            return array();
        }

        $store_uuid = $connection['store_uuid'];

        if ( $store_uuid === '' ) {
            $store_uuid = $this->connect_store( $connection['profile_uuid'] );
        }

        if ( $store_uuid === '' ) {
            return array();
        }

        return $this->save_store( $connection['profile_uuid'], $store_uuid );
    }

    private function connect_store( string $profile_uuid ): string {
        $body = array(
            'type'                 => self::PLATFORM_TYPE,
            'connectionIdentifier' => $this->get_domain(),
        );

        $currency = function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : '';

        if ( preg_match( '/^[A-Z]{3}$/', $currency ) ) {
            $body['currency'] = $currency;
        }

        $response = $this->post( 'profiles/' . rawurlencode( $profile_uuid ) . '/stores', $body, array(), self::REQUEST_TIMEOUT );

        if ( $this->get_response_code( $response ) === WP_Http::CONFLICT ) {
            $connection = $this->find_website_connection();

            return $connection['store_uuid'] ?? '';
        }

        if ( ! $this->is_successful( $response ) ) {
            return '';
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        return (string) ( $data['data']['uuid'] ?? '' );
    }

    private function find_website_connection(): array {
        $response = $this->get( 'profiles', array(), array(), self::REQUEST_TIMEOUT );

        if ( ! $this->is_successful( $response ) ) {
            return array();
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        foreach ( (array) ( $data['data'] ?? array() ) as $order ) {
            foreach ( (array) ( $order['profiles'] ?? array() ) as $profile ) {
                $connection = $this->find_profile_connection( (array) $profile );

                if ( ! empty( $connection ) ) {
                    return $connection;
                }
            }
        }

        return array();
    }

    private function find_profile_connection( array $profile ): array {
        $domain = $this->get_domain();

        foreach ( (array) ( $profile['websiteConnections'] ?? array() ) as $connection ) {
            if ( ( $connection['platformType'] ?? '' ) !== self::PLATFORM_TYPE || strtolower( (string) ( $connection['domain'] ?? '' ) ) !== $domain ) {
                continue;
            }

            return array(
                'profile_uuid' => (string) ( $profile['uuid'] ?? '' ),
                'store_uuid'   => (string) ( $connection['store']['uuid'] ?? '' ),
            );
        }

        return array();
    }

    private function save_store( string $profile_uuid, string $store_uuid ): array {
        $store = array(
            'profile_uuid' => $profile_uuid,
            'store_uuid'   => $store_uuid,
            'domain'       => $this->get_domain(),
            'token_hash'   => $this->get_token_hash(),
        );

        update_option( self::STORE_OPTION_NAME, $store, false );

        return $store;
    }

    private function get_domain(): string {
        return strtolower( $this->reach_api_handler->get_site_domain() );
    }

    private function get_token_hash(): string {
        return md5( $this->reach_api_handler->api_key_manager->get_token() );
    }
}
