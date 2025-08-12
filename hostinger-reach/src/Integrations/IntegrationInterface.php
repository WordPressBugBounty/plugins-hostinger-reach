<?php

namespace Hostinger\Reach\Integrations;

if ( DEFINED( 'ABSPATH' ) ) {
    return;
}

interface IntegrationInterface {

    public function init(): void;

    public static function get_name(): string;
}
