<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( 'no' === $this->enabled ) {
    return;
}
if ( is_checkout() ) {
    wp_register_style(
        'wc_zelle_checkout',
        WCZELLE_PLUGIN_DIR_URL . 'assets/css/checkout.css',
        array(),
        WCZELLE_VERSION
    );
    wp_enqueue_style( 'wc_zelle_checkout' );
    $copy_js = 'tab_copy';
    if ( !wp_script_is( $copy_js, 'registered' ) ) {
        wp_register_script(
            $copy_js,
            WCZELLE_PLUGIN_DIR_URL . 'assets/js/copy.js',
            array('jquery'),
            WCZELLE_VERSION,
            true
        );
    }
    wp_enqueue_script( $copy_js );
    wp_localize_script( $copy_js, 'tab_copy', array(
        'i18n' => array(
            'copied' => esc_html__( 'Copied!', WCZELLE_PLUGIN_TEXT_DOMAIN ),
            'failed' => esc_html__( 'Select it', WCZELLE_PLUGIN_TEXT_DOMAIN ),
        ),
    ) );
    // The QR stack only loads when a QR code will be rendered
    $needs_qrcode = $this->wc_zelle_has_qrcode();
    if ( $needs_qrcode ) {
        $qrcode_styling = 'tab_qr_code_styling';
        if ( !wp_script_is( $qrcode_styling, 'registered' ) ) {
            wp_register_script(
                $qrcode_styling,
                WCZELLE_PLUGIN_DIR_URL . 'assets/js/qr-code-styling.min.js',
                array(),
                '1.6.0',
                false
            );
        }
        wp_enqueue_script( $qrcode_styling );
        $qrcode_generator = 'tab_qr_code_generator';
        if ( !wp_script_is( $qrcode_generator, 'registered' ) ) {
            wp_register_script(
                $qrcode_generator,
                WCZELLE_PLUGIN_DIR_URL . 'assets/js/qr-code-generator.js',
                array($qrcode_styling),
                '1.0.0',
                false
            );
        }
        wp_enqueue_script( $qrcode_generator );
        // Shared too: pairs each QR container id with the localized settings object of the same name
        $qrcode_js = 'tab_qrcode';
        if ( !wp_script_is( $qrcode_js, 'registered' ) ) {
            wp_register_script(
                $qrcode_js,
                WCZELLE_PLUGIN_DIR_URL . 'assets/js/qrcode.js',
                array('jquery', $qrcode_styling, $qrcode_generator),
                '1.0.0',
                true
            );
        }
        wp_enqueue_script( $qrcode_js );
        $payment_url = $this->wc_zelle_url( 1 );
        $wc_zelle_qrcode = array(
            "url" => $payment_url,
        );
        $wc_zelle_qrcode['logo'] = '';
        $wc_zelle_qrcode['width'] = 150;
        $wc_zelle_qrcode['height'] = 150;
        $wc_zelle_qrcode['darkcolor'] = '#000000';
        $wc_zelle_qrcode['lightcolor'] = '#ffffff';
        $wc_zelle_qrcode['backgroundcolor'] = '#ffffff';
        $wc_zelle_qrcode['dotsType'] = 'dots';
        $wc_zelle_qrcode['cornersSquareType'] = 'extra-rounded';
        $wc_zelle_qrcode['cornersDotType'] = 'square';
        wp_localize_script( $qrcode_js, 'wc_zelle_qrcode', $wc_zelle_qrcode );
    }
    // Design 1 (PRO) classic checkout popup. Not on a block checkout, which has its own in blocks.js and would get every click handled twice
    $is_block_checkout = function_exists( 'has_block' ) && has_block( 'woocommerce/checkout' );
    if ( $this->wc_zelle_use_popup() && !is_checkout_pay_page() && !$is_block_checkout ) {
        wp_enqueue_script(
            'wc_zelle_checkout_popup',
            WCZELLE_PLUGIN_DIR_URL . 'assets/js/checkout-popup.js',
            array('jquery', 'jquery-blockui'),
            WCZELLE_VERSION,
            true
        );
        wp_localize_script( 'wc_zelle_checkout_popup', 'wc_zelle_popup', array(
            'gateway'  => $this->id,
            'checkout' => WC_AJAX::get_endpoint( 'checkout' ),
            'i18n'     => array(
                'confirm_required' => esc_html__( 'Please confirm that you have sent the payment.', WCZELLE_PLUGIN_TEXT_DOMAIN ),
            ),
        ) );
    }
    // // jquery-dialog on checkout/thankyou with countdown https://jqueryui.com/demos/dialog/
    // wp_enqueue_script( 'jquery-ui-dialog' );
}
// if ( ! is_cart() || ! is_checkout() || ! isset( $_GET['pay_for_order'] ) ) { }