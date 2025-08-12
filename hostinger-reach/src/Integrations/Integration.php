<?php

namespace Hostinger\Reach\Integrations;

use Hostinger\Reach\Models\Form;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

abstract class Integration {

    public const HOSTINGER_REACH_SUBMISSIONS_META_KEY = '_hostinger_reach_submissions';
    public const HOSTINGER_REACH_IS_ACTIVE_META_KEY   = '_hostinger_reach_is_active';

    abstract public static function get_name(): string;

    abstract public function get_post_type(): string|null;

    public function load_forms( array $forms, array $args ): array {
        if ( ! isset( $args['type'] ) || $args['type'] === $this->get_name() ) {
            $integration_forms = $this->get_forms( $args );

            return array_merge( $forms, $integration_forms );
        }

        return $forms;
    }

    public function on_form_activation_change( bool $reach_form_was_updated, string $form_id, bool $is_active ): bool {
        if ( $reach_form_was_updated ) {
            return $reach_form_was_updated;
        }

        return (bool) update_post_meta( (int) $form_id, Integration::HOSTINGER_REACH_IS_ACTIVE_META_KEY, $is_active ? 'yes' : 'no' );
    }

    public function update_form_submissions( int $id ): void {
        $submissions = (int) get_post_meta( $id, Integration::HOSTINGER_REACH_SUBMISSIONS_META_KEY, true );
        update_post_meta( $id, Integration::HOSTINGER_REACH_SUBMISSIONS_META_KEY, $submissions + 1 );
    }

    public function get_forms( array $args ): array {
        $posts = get_posts(
            array(
                'post_type' => $this->get_post_type(),
                'status'    => 'publish',
                'per_page'  => - 1,
            )
        );

        $forms = array_map(
            function ( $post ) {
                $form = new Form(
                    array(
                        'form_id'     => $post->ID,
                        'post_id'     => $post->ID,
                        'type'        => $this->get_name(),
                        'is_active'   => $this->is_form_enabled( $post->ID ),
                        'submissions' => (int) get_post_meta( $post->ID, Integration::HOSTINGER_REACH_SUBMISSIONS_META_KEY, true ),
                    )
                );

                return $form->to_array();
            },
            $posts
        );

        return $forms;
    }

    public function is_form_enabled( int $form_id ): bool {
        $is_active_meta = get_post_meta( $form_id, Integration::HOSTINGER_REACH_IS_ACTIVE_META_KEY, true );

        if ( $is_active_meta === '' ) {
            return true;
        }

        return $is_active_meta === 'yes';
    }
}
