<?php

namespace Hostinger\Reach\Integrations;

use Hostinger\Reach\Functions;
use Hostinger\Reach\Repositories\ContactListRepository;
use Hostinger\Reach\Repositories\FormRepository;
use WP_Post;

if ( ! DEFINED( 'ABSPATH' ) ) {
    exit;
}

class ReachFormIntegration extends Integration implements IntegrationInterface {

    public const INTEGRATION_NAME = 'hostinger-reach';

    protected FormRepository $form_repository;
    protected ContactListRepository $contact_list_repository;
    protected Functions $functions;

    public function __construct( FormRepository $form_repository, ContactListRepository $contact_list_repository, Functions $functions ) {
        $this->form_repository         = $form_repository;
        $this->contact_list_repository = $contact_list_repository;
        $this->functions               = $functions;
    }

    public function init(): void {
        add_action( 'hostinger_reach_forms', array( $this, 'load_forms' ), 10, 2 );
        add_action( 'transition_post_status', array( $this, 'handle_transition_post_status' ), 10, 3 );
        add_action( 'hostinger_reach_contact_submitted', array( $this, 'handle_submission' ) );
    }

    public function get_forms( array $args ): array {
        if ( ! isset( $args['type'] ) ) {
            $args['type'] = self::INTEGRATION_NAME;
        }

        return $this->form_repository->all( $args );
    }

    public function handle_transition_post_status( string $new_status, string $old_status, WP_Post $post ): void {
        if ( $new_status === 'publish' ) {
            $this->set_forms( $post );
            $this->maybe_unset_forms( $post );
        } elseif ( $old_status === 'publish' ) {
            $this->unset_all_forms( $post );
        }
    }

    public function handle_submission( array $data ): void {
        $this->form_repository->submit( $data );
    }

    public static function get_name(): string {
        return self::INTEGRATION_NAME;
    }

    public static function get_data(): array {
        return array(
            'is_active'        => true,
            'is_plugin_active' => true,
            'title'            => __( 'Hostinger Reach', 'hostinger-reach' ),
            'admin_url'        => 'admin.php?page=hostinger-reach',
            'edit_url'         => 'post.php?post={post_id}&action=edit',
            'url'              => 'https://wordpress.org/plugins/hostinger-reach',
        );
    }

    public function get_post_type(): string|null {
        return null;
    }

    private function set_contact_list( string $name ): array {
        if ( ! $this->contact_list_repository->exists( $name ) ) {
            $this->contact_list_repository->insert( array( 'name' => $name ) );
        }

        return $this->contact_list_repository->get( $name );
    }

    private function set_forms( WP_Post $post ): void {
        $blocks = $this->functions->get_reach_subscription_blocks( $post->ID );
        foreach ( $blocks as $block ) {
            if ( empty( $block['formId'] ) ) {
                continue;
            }
            $contact_list = $this->set_contact_list( $block['contactList'] ?? HOSTINGER_REACH_DEFAULT_CONTACT_LIST );
            $form         = array(
                'form_id'         => $block['formId'],
                'contact_list_id' => $contact_list['id'],
                'type'            => self::INTEGRATION_NAME,
            );

            if ( $this->form_repository->exists( $block['formId'] ) ) {
                $this->form_repository->update( $form );
            } else {
                $this->form_repository->insert( array_merge( $form, array( 'post_id' => $post->ID ) ) );
            }
        }
    }

    private function maybe_unset_forms( WP_Post $post ): void {
        $previous_forms    = $this->form_repository->all( array( 'post_id' => $post->ID ) );
        $previous_form_ids = array_column( $previous_forms, 'form_id' );
        $blocks            = $this->functions->get_reach_subscription_blocks( $post->ID );
        $current_form_ids  = array();

        foreach ( $blocks as $block ) {
            if ( empty( $block['formId'] ) ) {
                continue;
            }
            $current_form_ids[] = $block['formId'];
        }

        foreach ( $previous_form_ids as $form_id ) {
            if ( ! in_array( $form_id, $current_form_ids, true ) ) {
                $this->form_repository->delete( $form_id );
            }
        }
    }

    private function unset_all_forms( WP_Post $post ): void {
        $forms = $this->form_repository->all( array( 'post_id' => $post->ID ) );
        foreach ( $forms as $form ) {
            $this->form_repository->delete( $form['form_id'] );
        }
    }
}
