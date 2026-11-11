<?php

namespace Hostinger\Reach\Integrations;

use Hostinger\Reach\Repositories\FormRepository;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

abstract class IntegrationWithForms extends Integration {

    public FormRepository $form_repository;

    public function __construct( FormRepository $form_repository ) {
        $this->form_repository = $form_repository;
    }

    abstract public function get_form_ids( WP_Post $post ): array;

    protected function maybe_unset_forms( WP_Post $post ): void {
        $previous_forms    = $this->form_repository->all(
            array(
                'post_id' => $post->ID,
                'type'    => $this->get_name(),
            )
        );
        $previous_form_ids = array_column( $previous_forms, 'form_id' );
        $current_form_ids  = $this->get_form_ids( $post );

        foreach ( $previous_form_ids as $form_id ) {
            if ( ! in_array( $form_id, $current_form_ids, true ) ) {
                $this->remove_form( $form_id );
            }
        }
    }

    protected function can_update_form( array $form, int $post_id ): bool {
        $type = $form['type'] ?? '';
        if ( $type !== '' && $type !== $this->get_name() ) {
            return false;
        }

        $owner_post_id = (int) ( $form['post']['ID'] ?? 0 );
        if ( $owner_post_id ) {
            return $owner_post_id === $post_id;
        }

        return user_can( (int) get_post_field( 'post_author', $post_id ), 'manage_options' );
    }

    protected function remove_form( string $form_id ): void {
        $this->form_repository->delete( $form_id );
    }

    protected function unset_all_forms( WP_Post $post ): void {
        $this->form_repository->delete_all_from_post( $post->ID, $this->get_name() );
    }

    public function get_forms(): array {
        $args['type'] = $this->get_name();
        return $this->form_repository->all( $args );
    }
}
