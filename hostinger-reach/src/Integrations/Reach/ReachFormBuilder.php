<?php

namespace Hostinger\Reach\Integrations\Reach;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReachFormBuilder {

    public const SCRIPT_HANDLE = 'hostinger-reach-embed';

    public function init(): void {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_embed_script' ) );
    }

    public function enqueue_embed_script(): void {
        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            HOSTINGER_REACH_EMBED_SCRIPT_URL,
            array(),
            null,
            array(
                'in_footer' => true,
                'strategy'  => 'defer',
            )
        );
    }
}
