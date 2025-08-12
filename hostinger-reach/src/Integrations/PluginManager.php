<?php

namespace Hostinger\Reach\Integrations;

use Plugin_Upgrader;
use Plugin_Upgrader_Skin;
use WP_Error;

if ( ! DEFINED( 'ABSPATH' ) ) {
    die;
}

class PluginManager {

    public static function plugin_data(): array {
        return array(
            ContactForm7Integration::INTEGRATION_NAME => array(
                'folder'       => 'contact-form-7',
                'file'         => 'wp-contact-form-7.php',
                'admin_url'    => 'admin.php?page=wpcf7',
                'edit_url'     => 'admin.php?page=wpcf7&post={form_id}&action=edit',
                'url'          => 'https://wordpress.org/plugins/contact-form-7/',
                'download_url' => 'https://downloads.wordpress.org/plugin/contact-form-7.zip',
                'title'        => __( 'Contact Form 7', 'hostinger-reach' ),
            ),
            WpFormsLiteIntegration::INTEGRATION_NAME  => array(
                'folder'       => 'wpforms-lite',
                'file'         => 'wpforms.php',
                'admin_url'    => 'admin.php?page=wpforms-overview',
                'edit_url'     => 'admin.php?page=wpforms-builder&view=fields&form_id={form_id}',
                'url'          => 'https://wordpress.org/plugins/wpforms-lite/',
                'download_url' => 'https://downloads.wordpress.org/plugin/wpforms-lite.zip',
                'title'        => __( 'WP Forms Lite', 'hostinger-reach' ),
            ),
        );
    }

    public function install( string $plugin_name ): bool|WP_Error {
        if ( $this->is_installed( $plugin_name ) ) {
            return true;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';

        $plugin = $this->get_plugin( $plugin_name );

        $temp_file = download_url( $plugin['download_url'] );

        if ( is_wp_error( $temp_file ) ) {
            return $temp_file;
        }

        $upgrader = new Plugin_Upgrader( new Plugin_Upgrader_Skin() );
        $result   = $upgrader->install( $temp_file );

        wp_delete_file( $temp_file );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return true;
    }

    public function activate( string $plugin_name ): bool {
        if ( ! $this->is_installed( $plugin_name ) ) {
            return false;
        }

        if ( $this->is_active( $plugin_name ) ) {
            return true;
        }

        return is_null( activate_plugin( $this->get_plugin_path( $plugin_name ) ) );
    }

    public function is_installed( string $plugin_name ): bool {
        return file_exists( WP_PLUGIN_DIR . '/' . $this->get_plugin_path( $plugin_name ) );
    }

    public function is_active( string $plugin_name ): bool {
        $plugin_path = $this->get_plugin_path( $plugin_name );

        return is_plugin_active( $plugin_path );
    }

    public function get_plugin_path( string $plugin_name ): string {
        $plugin = $this->get_plugin( $plugin_name );

        if ( ! isset( $plugin['folder'] ) || ! isset( $plugin['file'] ) ) {
            return '';
        }

        return $plugin['folder'] . '/' . $plugin['file'];
    }

    public function get_plugin( string $plugin_name ): array {
        $plugin_data = self::plugin_data();

        return $plugin_data[ $plugin_name ] ?? array();
    }
}
