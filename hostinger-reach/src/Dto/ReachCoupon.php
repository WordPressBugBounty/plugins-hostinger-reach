<?php

namespace Hostinger\Reach\Dto;

use DateTimeInterface;
use WC_Coupon;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class ReachCoupon {
    public const TYPE_PERCENTAGE    = 'percentage';
    public const TYPE_FIXED         = 'fixed';
    public const TYPE_FREE_SHIPPING = 'free_shipping';
    public const TARGET_TOTAL       = 'total';
    public const TARGET_ITEM        = 'item';

    private const TITLE_MAX_LENGTH = 100;
    private const TYPE_MAX_LENGTH  = 20;

    private const DISCOUNT_TYPES = array(
        'percent'       => array( self::TYPE_PERCENTAGE, null ),
        'fixed_cart'    => array( self::TYPE_FIXED, self::TARGET_TOTAL ),
        'fixed_product' => array( self::TYPE_FIXED, self::TARGET_ITEM ),
    );

    private WC_Coupon $coupon;

    public function __construct( WC_Coupon $coupon ) {
        $this->coupon = $coupon;
    }

    public function get_coupon_id(): string {
        return (string) $this->coupon->get_id();
    }

    public function to_array(): array {
        return array(
            'couponId'        => $this->get_coupon_id(),
            'amount'          => $this->get_amount(),
            'type'            => $this->get_type(),
            'target'          => $this->get_target(),
            'title'           => mb_substr( (string) $this->coupon->get_code(), 0, self::TITLE_MAX_LENGTH ),
            'description'     => (string) $this->coupon->get_description(),
            'enabled'         => $this->coupon->get_status() === 'publish',
            'startsAt'        => null,
            'endsAt'          => $this->format_date( $this->coupon->get_date_expires() ),
            'sourceCreatedAt' => $this->format_date( $this->coupon->get_date_created() ),
            'sourceUpdatedAt' => $this->format_date( $this->coupon->get_date_modified() ),
        );
    }

    private function get_amount(): float {
        $amount = $this->coupon->get_amount();

        if ( ! is_numeric( $amount ) ) {
            return 0.0;
        }

        return max( 0.0, (float) $amount );
    }

    private function get_type(): string {
        if ( $this->is_free_shipping_only() ) {
            return self::TYPE_FREE_SHIPPING;
        }

        $discount_type = (string) $this->coupon->get_discount_type();

        if ( isset( self::DISCOUNT_TYPES[ $discount_type ] ) ) {
            return self::DISCOUNT_TYPES[ $discount_type ][0];
        }

        return mb_substr( strtolower( $discount_type ), 0, self::TYPE_MAX_LENGTH );
    }

    private function get_target(): string {
        if ( $this->is_free_shipping_only() ) {
            return self::TARGET_TOTAL;
        }

        $target = self::DISCOUNT_TYPES[ (string) $this->coupon->get_discount_type() ][1] ?? null;

        if ( $target !== null ) {
            return $target;
        }

        if ( ! empty( $this->coupon->get_product_ids() ) || ! empty( $this->coupon->get_product_categories() ) ) {
            return self::TARGET_ITEM;
        }

        return self::TARGET_TOTAL;
    }

    private function is_free_shipping_only(): bool {
        return $this->coupon->get_free_shipping() && $this->get_amount() === 0.0;
    }

    private function format_date( ?DateTimeInterface $date ): ?string {
        if ( ! $date ) {
            return null;
        }

        return gmdate( 'Y-m-d\TH:i:s\Z', $date->getTimestamp() );
    }
}
