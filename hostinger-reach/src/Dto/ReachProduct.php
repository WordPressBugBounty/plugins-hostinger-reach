<?php

namespace Hostinger\Reach\Dto;

use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ReachProduct {
    public const TYPE_PHYSICAL = 'physical';
    public const TYPE_DIGITAL  = 'digital';

    private const TITLE_MAX_LENGTH = 255;
    private const URL_MAX_LENGTH   = 2048;

    private WC_Product $product;

    public function __construct( WC_Product $product ) {
        $this->product = $product;
    }

    public function get_product_id(): string {
        return (string) $this->product->get_id();
    }

    public function to_array(): array {
        return array(
            'productId'     => $this->get_product_id(),
            'title'         => mb_substr( $this->product->get_name(), 0, self::TITLE_MAX_LENGTH ),
            'url'           => $this->get_url( $this->product->get_permalink() ),
            'description'   => $this->get_description(),
            'type'          => $this->get_type(),
            'price'         => $this->get_price( $this->product ),
            'stockQuantity' => $this->get_stock_quantity( $this->product ),
            'imageUrl'      => $this->get_image_url( (int) $this->product->get_image_id() ),
            'variants'      => $this->get_variants(),
            'images'        => $this->get_images(),
            'publishedAt'   => $this->get_published_at(),
        );
    }

    private function get_description(): string {
        $description = (string) $this->product->get_description();

        if ( $description !== '' ) {
            return $description;
        }

        return (string) $this->product->get_short_description();
    }

    private function get_type(): string {
        if ( $this->product->is_virtual() || $this->product->is_downloadable() ) {
            return self::TYPE_DIGITAL;
        }

        return self::TYPE_PHYSICAL;
    }

    private function get_price( WC_Product $product ): ?float {
        $price = $product->get_price();

        if ( $price === '' || ! is_numeric( $price ) ) {
            return null;
        }

        return max( 0.0, (float) $price );
    }

    private function get_stock_quantity( WC_Product $product ): ?int {
        if ( ! $product->managing_stock() ) {
            return null;
        }

        $stock_quantity = $product->get_stock_quantity();

        if ( $stock_quantity === null ) {
            return null;
        }

        return max( 0, (int) $stock_quantity );
    }

    private function get_image_url( int $image_id ): ?string {
        if ( ! $image_id ) {
            return null;
        }

        $image_url = wp_get_attachment_url( $image_id );

        if ( ! is_string( $image_url ) ) {
            return null;
        }

        return $this->get_url( $image_url );
    }

    private function get_images(): array {
        $images = array();

        foreach ( $this->product->get_gallery_image_ids() as $image_id ) {
            $image_url = $this->get_image_url( (int) $image_id );

            if ( $image_url !== null ) {
                $images[] = $image_url;
            }
        }

        return $images;
    }

    private function get_variants(): array {
        if ( ! $this->product->is_type( 'variable' ) ) {
            return array();
        }

        $variants = array();

        foreach ( $this->product->get_children() as $variation_id ) {
            $variation = wc_get_product( $variation_id );

            if ( ! $variation instanceof WC_Product ) {
                continue;
            }

            $variants[] = $this->get_variant( $variation );
        }

        return $variants;
    }

    private function get_variant( WC_Product $variation ): array {
        return array(
            'id'            => (string) $variation->get_id(),
            'sku'           => (string) $variation->get_sku(),
            'title'         => (string) $variation->get_name(),
            'price'         => $this->get_price( $variation ),
            'stockQuantity' => $this->get_stock_quantity( $variation ),
            'attributes'    => $variation->get_variation_attributes(),
        );
    }

    private function get_published_at(): ?string {
        $date_created = $this->product->get_date_created();

        if ( ! $date_created ) {
            return null;
        }

        return gmdate( 'Y-m-d\TH:i:s\Z', $date_created->getTimestamp() );
    }

    private function get_url( string $url ): ?string {
        if ( $url === '' || strlen( $url ) > self::URL_MAX_LENGTH ) {
            return null;
        }

        return $url;
    }
}
