<?php

namespace Hostinger\Reach\Blocks;


if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class SubscriptionFormBlock extends Block {
    public string $name = 'subscription';

    public function data(): array {
        return array(
            'endpoint'     => '/wp-json/hostinger-reach/v1/contact',
            'nonce'        => wp_create_nonce( 'wp_rest' ),
            'translations' => array(
                'thanks' => __( 'Thanks for subscribing.', 'hostinger-reach' ),
                'error'  => __( 'Something went wrong. Please try again.', 'hostinger-reach' ),
            ),
        );
    }

    public function autoloader(): void {
        if ( ! is_admin() || empty( $_GET['hostinger_reach_add_block'] ) ) {
            return;
        }

        if ( $this->functions->block_file_exists( "$this->name-autoloader.js" ) === false ) {
            return;
        }

        $handler = parent::get_block_name() . '-autoloader';

        wp_enqueue_script(
            $handler,
            $this->functions->get_blocks_url() . "$this->name-autoloader.js",
            array( parent::get_block_name() . '-editor' ),
            filemtime( $this->functions->get_block_file_name( "$this->name-autoloader.js" ) ),
            array( 'in_footer' => true )
        );
    }

    public function render( array $attributes ): bool|string {
        $form_id      = $attributes['formId'] ?? '';
        $show_name    = $attributes['showName'] ?? false;
        $show_surname = $attributes['showSurname'] ?? false;
        $contact_list = $attributes['contactList'] ?? '';

        ob_start();
        ?>
        <div class="hostinger-reach-block-subscription-form-wrapper">
            <form id="<?php echo esc_attr( $form_id ); ?>" class="hostinger-reach-block-subscription-form">
                <input type="hidden" name="group" value="<?php echo esc_attr( $contact_list ); ?>">
                <input type="hidden" name="id" value="<?php echo esc_attr( $form_id ); ?>">

                <div class="hostinger-reach-block-form-field">
                    <label
                        for="<?php echo esc_attr( $form_id ); ?>-email"><?php esc_html_e( 'Email', 'hostinger-reach' ); ?>
                        <span class="required">*</span></label>
                    <input type="email" id="<?php echo esc_attr( $form_id ); ?>-email" name="email" required>
                </div>

                <?php if ( $show_name ) : ?>
                    <div class="hostinger-reach-block-form-field">
                        <label
                            for="<?php echo esc_attr( $form_id ); ?>-name"><?php esc_html_e( 'Name', 'hostinger-reach' ); ?></label>
                        <input type="text" id="<?php echo esc_attr( $form_id ); ?>-name" name="name">
                    </div>
                <?php endif; ?>

                <?php if ( $show_surname ) : ?>
                    <div class="hostinger-reach-block-form-field">
                        <label
                            for="<?php echo esc_attr( $form_id ); ?>-surname"><?php esc_html_e( 'Surname', 'hostinger-reach' ); ?></label>
                        <input type="text" id="<?php echo esc_attr( $form_id ); ?>-surname" name="surname">
                    </div>
                <?php endif; ?>

                <button
                    type="submit"
                    class="hostinger-reach-block-submit wp-block-button__link has-dark-color has-color-1-background-color has-text-color has-background has-link-color has-medium-font-size wp-element-button">
                        <?php esc_html_e( 'Subscribe', 'hostinger-reach' ); ?>
                </button>

                <div class="reach-subscription-message" style="display: none;"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
