<?php

namespace Hostinger\Reach;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

use Hostinger\Reach\Providers\AssetsProvider;
use Hostinger\Reach\Providers\BlocksProvider;
use Hostinger\Reach\Providers\ContainerProvider;
use Hostinger\Reach\Providers\DatabaseProvider;
use Hostinger\Reach\Providers\IntegrationsProvider;
use Hostinger\Reach\Providers\MenusProvider;
use Hostinger\Reach\Providers\ProviderInterface;
use Hostinger\Reach\Providers\RedirectsProvider;
use Hostinger\Reach\Providers\RoutesProvider;
use Hostinger\Reach\Providers\WpdbProvider;

class Boot {
    private Container $container;
    private array $providers       = array(
        WpdbProvider::class,
        ContainerProvider::class,
        DatabaseProvider::class,
        AssetsProvider::class,
        MenusProvider::class,
        RoutesProvider::class,
        BlocksProvider::class,
        IntegrationsProvider::class,
        RedirectsProvider::class,
    );
    private static ?Boot $instance = null;

    private function __construct() {
        $this->container = new Container();
    }

    public static function get_instance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function plugins_loaded(): void {
        $this->register_providers();
    }

    private function register_providers(): void {
        foreach ( $this->providers as $provider_class ) {
            $provider = new $provider_class();
            if ( $provider instanceof ProviderInterface ) {
                $provider->register( $this->container );
            }
        }
    }
}
