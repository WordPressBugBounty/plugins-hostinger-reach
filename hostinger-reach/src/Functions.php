<?php

namespace Hostinger\Reach;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class Functions {
    public const ASSET_PAGES = array(
        'admin.php?page=hostinger-reach',
        'post-new.php',
        'post.php',
        'site-editor.php',
    );

    public function get_frontend_dir(): string {
        return HOSTINGER_REACH_PLUGIN_DIR . 'frontend/dist/';
    }

    public function get_frontend_url(): string {
        return HOSTINGER_REACH_PLUGIN_URL . 'frontend/dist/';
    }

    public function get_blocks_dir(): string {
        return $this->get_frontend_dir() . 'blocks/';
    }

    public function block_file_exists( string $file_name ): bool {
        $file = $this->get_block_file_name( $file_name );

        return file_exists( $file );
    }

    public function get_block_file_name( string $file_name ): string {
        return $this->get_blocks_dir() . $file_name;
    }

    public function get_blocks_url(): string {
        return $this->get_frontend_url() . 'blocks/';
    }

    public function need_to_load_assets(): bool {
        if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
            return false;
        }

        $current_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );

        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return false;
        }

        if ( isset( $current_uri ) && strpos( $current_uri, '/wp-json/' ) !== false ) {
            return false;
        }

        $admin_path = wp_parse_url( admin_url(), PHP_URL_PATH );

        foreach ( self::ASSET_PAGES as $page ) {
            if ( stripos( $current_uri, $admin_path . $page ) !== false ) {
                return true;
            }
        }

        return false;
    }

    public function get_host_info(): string {
        $host     = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
        $site_url = get_site_url();
        $site_url = preg_replace( '#^https?://#', '', $site_url );
        $site_url = preg_replace( '/^www\./', '', $site_url );

        if ( ! empty( $site_url ) && ! empty( $host ) && strpos( $site_url, $host ) === 0 ) {
            if ( $site_url === $host ) {
                return $host;
            } else {
                return substr( $site_url, strlen( $host ) + 1 );
            }
        }

        return $host;
    }

    public function is_hostinger_user(): bool {
        return ! empty( $_SERVER['H_PLATFORM'] );
    }

    public function is_staging(): bool {
        return isset( $_SERVER['H_STAGING'] ) && filter_var( wp_unslash( $_SERVER['H_STAGING'] ), FILTER_VALIDATE_BOOLEAN ) === true;
    }

    public function has_reach_subscription_block( int $id ): bool {
        $content = get_post_field( 'post_content', $id );

        return str_contains( $content, '<!-- wp:hostinger-reach/subscription' );
    }

    public function get_reach_subscription_blocks( int $id ): array {
        $content = get_post_field( 'post_content', $id );
        $blocks  = parse_blocks( $content );

        return $this->get_parsed_blocks( $blocks );
    }

    public function get_parsed_blocks( array $blocks ): array {
        $parsed_blocks = array();
        foreach ( $blocks as $block ) {
            if ( $block['blockName'] === 'hostinger-reach/subscription' ) {
                $parsed_blocks[] = $block['attrs'];
            }

            if ( ! empty( $block['innerBlocks'] ) ) {
                $parsed_blocks = array_merge( $parsed_blocks, $this->get_parsed_blocks( $block['innerBlocks'] ) );
            }
        }

        return $parsed_blocks;
    }
}
