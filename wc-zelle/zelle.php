<?php

/*
Plugin Name: Checkout with Zelle on Woocommerce
Plugin URI: https://theafricanboss.com/zelle
Description: The top bank to bank payments method now on WordPress. Receive Zelle payments on your website with WooCommerce + Zelle
Author: The African Boss
Author URI: https://theafricanboss.com
Version: 4.0
Requires PHP: 5.0
Requires at least: 5.0
Tested up to: 6.5.4
WC requires at least: 6.0.0
WC tested up to: 9.0.1
Text Domain: wc-zelle
Domain Path: languages
Created: 2021
Copyright 2024 theafricanboss.com All rights reserved
*/
// Reach out to The African Boss for website and mobile app development services at theafricanboss@gmail.com
// or at www.TheAfricanBoss.com or download our app at www.TheAfricanBoss.com/app
// If you are using this version, please send us some feedback
// via email at theafricanboss@gmail.com on your thoughts and what you would like improved
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
include_once ABSPATH . 'wp-admin/includes/plugin.php';
$plugin_data = get_plugin_data( __FILE__ );
define( 'WCZELLE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCZELLE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WCZELLE_PLUGIN_DIR_URL', plugins_url( '/', __FILE__ ) );
define( 'WCZELLE_PLUGIN_SLUG', explode( "/", WCZELLE_PLUGIN_BASENAME )[0] );
define( 'WCZELLE_PLUGIN_VERSION', WCZELLE_PLUGIN_SLUG . '-' . $plugin_data['Version'] );
define( 'WCZELLE_PLUGIN_TEXT_DOMAIN', $plugin_data['TextDomain'] );
define( 'WCZELLE_UPGRADE_URL', 'https://theafricanboss.com/freemius/wc-zelle' );
if ( function_exists( 'zelle_fs' ) ) {
    zelle_fs()->set_basename( false, __FILE__ );
} else {
    // DO NOT REMOVE THIS IF, IT IS ESSENTIAL FOR THE `function_exists` CALL ABOVE TO PROPERLY WORK.
    if ( !function_exists( 'zelle_fs' ) ) {
        // Create a helper function for easy SDK access.
        function zelle_fs() {
            global $zelle_fs;
            if ( !isset( $zelle_fs ) ) {
                // Activate multisite network integration.
                if ( !defined( 'WP_FS__PRODUCT_9162_MULTISITE' ) ) {
                    define( 'WP_FS__PRODUCT_9162_MULTISITE', true );
                }
                // Include Freemius SDK.
                require_once dirname( __FILE__ ) . '/freemius/start.php';
                $zelle_fs = fs_dynamic_init( array(
                    'id'             => '9162',
                    'slug'           => 'wc-zelle',
                    'premium_slug'   => 'wc-zelle-pro',
                    'type'           => 'plugin',
                    'public_key'     => 'pk_a67d9b0ca5b20d4305d08cef08a05',
                    'is_premium'     => false,
                    'premium_suffix' => 'PRO',
                    'has_addons'     => false,
                    'has_paid_plans' => true,
                    'trial'          => array(
                        'days'               => 3,
                        'is_require_payment' => true,
                    ),
                    'menu'           => array(
                        'slug'           => 'wc-settings',
                        'override_exact' => true,
                        'first-path'     => 'admin.php?page=wc-settings&tab=checkout&section=zelle',
                        'support'        => false,
                        'parent'         => array(
                            'slug' => 'wc-settings',
                        ),
                    ),
                    'is_live'        => true,
                ) );
            }
            return $zelle_fs;
        }

        // Init Freemius.
        zelle_fs();
        // Signal that SDK was initiated.
        do_action( 'zelle_fs_loaded' );
        function zelle_fs_settings_url() {
            return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=zelle' );
        }

        zelle_fs()->add_filter( 'connect_url', 'zelle_fs_settings_url' );
        zelle_fs()->add_filter( 'after_skip_url', 'zelle_fs_settings_url' );
        zelle_fs()->add_filter( 'after_connect_url', 'zelle_fs_settings_url' );
        zelle_fs()->add_filter( 'after_pending_connect_url', 'zelle_fs_settings_url' );
    }
    if ( !is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
        // deactivate_plugins( WCZELLE_PLUGIN_BASENAME );
        require_once WCZELLE_PLUGIN_DIR . 'includes/notifications/woocommerce.php';
    }
    // translations
    function wczelle_load_textdomain() {
        load_plugin_textdomain( WCZELLE_PLUGIN_TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    add_action( 'plugins_loaded', 'wczelle_load_textdomain' );
    // if ( current_user_can( 'manage_options' ) ) { // needs WPINC . '/pluggable.php'
    if ( is_admin() ) {
        add_action( 'plugin_action_links_' . WCZELLE_PLUGIN_BASENAME, function ( $links ) {
            global $zelle_fs;
            $settings_link = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=zelle' ) . '">Settings</a>';
            $upgrade_url = zelle_fs()->get_upgrade_url();
            $promo_links = '<a href="' . $upgrade_url . '" target="_blank" style="color: blue; font-weight: bold;">Go Pro</a>';
            array_unshift( $links, $settings_link );
            $links[] = $promo_links;
            return $links;
        } );
        add_action( 'admin_enqueue_scripts', function () {
            $currentScreen = get_current_screen();
            // var_dump($currentScreen);
            if ( strpos( $currentScreen->id, 'wc_zelle' ) !== false || strpos( $currentScreen->id, 'wc-zelle' ) !== false ) {
                wp_register_style( 'wc_zelle_bootstrap', WCZELLE_PLUGIN_DIR_URL . 'assets/css/bootstrap.min.css' );
                wp_enqueue_style( 'wc_zelle_bootstrap' );
            } else {
                return;
            }
        } );
        require_once WCZELLE_PLUGIN_DIR . 'includes/admin/dashboard.php';
    }
    add_filter( 'woocommerce_payment_gateways', 'zelle_add_gateway_class' );
    //This action hook registers our PHP class as a WooCommerce payment gateway
    function zelle_add_gateway_class(  $gateways  ) {
        $gateways[] = 'WC_Zelle_Gateway';
        return $gateways;
    }

    add_action( 'before_woocommerce_init', function () {
        if ( class_exists( Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    } );
    add_action( 'plugins_loaded', 'zelle_init_gateway_class' );
    // init_gateway inside plugins_loaded action hook
    function zelle_init_gateway_class() {
        // // include_once ABSPATH . 'wp-includes/pluggable.php';
        include_once ABSPATH . WPINC . '/pluggable.php';
        if ( class_exists( 'WC_Payment_Gateway' ) ) {
            require_once WCZELLE_PLUGIN_DIR . 'includes/class-wc_zelle_gateway.php';
            require_once WCZELLE_PLUGIN_DIR . 'includes/class-wc_zelle_update_order.php';
        } else {
            require_once WCZELLE_PLUGIN_DIR . 'includes/notifications/woocommerce.php';
        }
    }

    // add_action( 'woocommerce_blocks_loaded', 'zelle_woocommerce_block_support' );
    // function zelle_woocommerce_block_support() {
    //     if ( class_exists ( 'WC_Payment_Gateway' ) && class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
    // 		require_once WCZELLE_PLUGIN_DIR . 'includes/class-wc_zelle_gateway_blocks.php';
    // 		add_action(
    // 			'woocommerce_blocks_payment_method_type_registration',
    // 			function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
    // 				$payment_method_registry->register( new WC_Zelle_Gateway_Blocks_Support() );
    // 			}
    // 		);
    // 	}
    // }
}