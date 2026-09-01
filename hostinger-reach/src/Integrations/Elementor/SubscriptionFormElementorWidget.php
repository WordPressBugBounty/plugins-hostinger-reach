<?php

namespace Hostinger\Reach\Integrations\Elementor;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Hostinger\Reach\Blocks\SubscriptionFormBlock;

class SubscriptionFormElementorWidget extends Widget_Base {

    public const FORM_ID_PREFIX = 'elementor-hostinger-reach-form-';
    public const WIDGET_NAME    = 'hostinger-reach';

    public function get_name(): string {
        return self::WIDGET_NAME;
    }


    public function get_title(): ?string {
        return __( 'Hostinger Reach', 'hostinger-reach' );
    }

    public function get_icon(): string {
        return 'eicon-envelope';
    }

    public function get_keywords(): array {
        return array(
            'form',
            'forms',
            'reach',
            'contact form',
            'hostinger',
            'email',
            'newsletter',
        );
    }

    public function get_categories(): array {
        return array(
            'basic',
        );
    }

    protected function register_controls(): void {

        $this->start_controls_section(
            'form',
            array(
                'label' => esc_html__( 'Form', 'hostinger-reach' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'formId',
            array(
                'label'      => esc_html__( 'Form ID', 'hostinger-reach' ),
                'type'       => Controls_Manager::HIDDEN,
                'input_type' => 'hidden',
                'default'    => self::FORM_ID_PREFIX . random_int( 1, PHP_INT_MAX ),
            )
        );

        $form_builder_description = sprintf(
            /* translators: %s: "Learn more" link. */
            esc_html__( 'Choose a Reach Form Builder template to embed instead. The Reach script loads it automatically. %s', 'hostinger-reach' ),
            '<a href="https://www.hostinger.com/support/hostinger-reach-form-builder-overview-setup-guide/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Learn more', 'hostinger-reach' ) . '</a>'
        );

        $this->add_control(
            'formBuilderSelector',
            array(
                'type' => Controls_Manager::RAW_HTML,
                'raw'  => '<div class="hostinger-reach-elementor-selector"></div>',
            )
        );

        $this->add_control(
            'formBuilderId',
            array(
                'label'   => esc_html__( 'Form Builder Template', 'hostinger-reach' ),
                'type'    => Controls_Manager::HIDDEN,
                'default' => '',
            )
        );

        $builder_mode_conditions = array(
            'relation' => 'or',
            'terms'    => array(
                array(
                    'name'     => 'formBuilderManual',
                    'operator' => '===',
                    'value'    => 'yes',
                ),
                array(
                    'name'     => 'formBuilderId',
                    'operator' => '!==',
                    'value'    => '',
                ),
            ),
        );

        $this->add_control(
            'formBuilderManual',
            array(
                'label'        => esc_html__( 'Enter template ID manually', 'hostinger-reach' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => esc_html__( 'Yes', 'hostinger-reach' ),
                'label_off'    => esc_html__( 'No', 'hostinger-reach' ),
                'return_value' => 'yes',
                'default'      => '',
                'conditions'   => $builder_mode_conditions,
            )
        );

        $this->add_control(
            'formBuilderIdManual',
            array(
                'label'       => esc_html__( 'Form Builder Template ID', 'hostinger-reach' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => '',
                'description' => $form_builder_description,
                'condition'   => array(
                    'formBuilderManual' => 'yes',
                ),
            )
        );

        $no_template_conditions = array(
            'relation' => 'and',
            'terms'    => array(
                array(
                    'name'     => 'formBuilderManual',
                    'operator' => '!==',
                    'value'    => 'yes',
                ),
                array(
                    'name'     => 'formBuilderId',
                    'operator' => '===',
                    'value'    => '',
                ),
            ),
        );

        $this->add_control(
            'showName',
            array(
                'label'        => esc_html__( 'Show Name', 'hostinger-reach' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => esc_html__( 'Yes', 'hostinger-reach' ),
                'label_off'    => esc_html__( 'No', 'hostinger-reach' ),
                'return_value' => 1,
                'default'      => 0,
                'conditions'   => $no_template_conditions,
            )
        );

        $this->add_control(
            'showSurname',
            array(
                'label'        => esc_html__( 'Show Surname', 'hostinger-reach' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => esc_html__( 'Yes', 'hostinger-reach' ),
                'label_off'    => esc_html__( 'No', 'hostinger-reach' ),
                'return_value' => 1,
                'default'      => 0,
                'conditions'   => $no_template_conditions,
            )
        );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        if ( ( $settings['formBuilderManual'] ?? '' ) === 'yes' && ! empty( $settings['formBuilderIdManual'] ) ) {
            $settings['formBuilderId'] = $settings['formBuilderIdManual'];
        }

        SubscriptionFormBlock::render_block_html( $settings, ElementorIntegration::INTEGRATION_NAME );
    }

    protected function content_template(): void {
        ?>
        <# var reachFormBuilderId = 'yes' === settings.formBuilderManual ? settings.formBuilderIdManual : settings.formBuilderId; #>
        <# reachFormBuilderId = reachFormBuilderId && /^[a-zA-Z0-9-]+$/.test( reachFormBuilderId ) ? reachFormBuilderId : ''; #>
        <# if ( reachFormBuilderId ) { #>
            <div data-reach-form="{{ reachFormBuilderId }}"></div>
        <# } else { #>
        <div class="hostinger-reach-block-subscription-form-wrapper">
            <form id="{{{ settings.formId }}}" class="hostinger-reach-block-subscription-form">
                <input type="hidden" name="id" value="{{{ settings.formId }}}">
                <input type="hidden" name="metadata.plugin" value="elementor">

                <div class="hostinger-reach-block-form-field">
                    <label
                        for="{{{ settings.formId }}}-email"><?php esc_html_e( 'Email', 'hostinger-reach' ); ?>
                        <span class="required">*</span></label>
                    <input type="email" id="{{{ settings.formId }}}-email" name="email" required>
                </div>

                <# if ( settings.showName ) { #>
                    <div class="hostinger-reach-block-form-field">
                        <label
                            for="{{{ settings.formId }}}-name"><?php esc_html_e( 'Name', 'hostinger-reach' ); ?></label>
                        <input type="text" id="{{{ settings.formId }}}-name" name="name">
                    </div>
                <# } #>

                <# if ( settings.showSurname ) { #>
                    <div class="hostinger-reach-block-form-field">
                        <label
                            for="{{{ settings.formId }}}-surname"><?php esc_html_e( 'Surname', 'hostinger-reach' ); ?></label>
                        <input type="text" id="{{{ settings.formId }}}-surname" name="surname">
                    </div>
                <# } #>

                <button
                    type="submit"
                    class="hostinger-reach-block-submit wp-block-button__link has-light-color has-color-3-background-color has-text-color has-background has-link-color has-medium-font-size wp-element-button">
                    <?php esc_html_e( 'Subscribe', 'hostinger-reach' ); ?>
                </button>

                <div class="reach-subscription-message" style="display: none;"></div>
            </form>
        </div>
        <# } #>
        <?php
    }
}
