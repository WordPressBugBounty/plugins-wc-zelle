<?php

// namespace Automattic\WooCommerce\Blocks\Payments\Integrations;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
//use Automattic\WooCommerce\Blocks\Assets\Api;
final class WC_Zelle_Gateway_Blocks_Support extends AbstractPaymentMethodType {
    private $gateway;

    protected $name = 'zelle';

    //private $asset_api;
    //public function __construct( Api $asset_api ) {
    //	$this->asset_api = $asset_api;
    //}
    public function initialize() {
        // $this->settings = get_option( 'woocommerce_zelle_settings', [] );
        $this->settings = get_option( "woocommerce_{$this->name}_settings", array() );
        // echo '<pre>'; print_r($this->settings); echo '</pre>'; wp_die();
        // you can also initialize your payment gateway here
        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway = $gateways[$this->name];
    }

    public function is_active() {
        // return $this->gateway->enabled === 'yes';
        // return $this->gateway->is_available();
        // return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
        return !empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
    }

    public function get_payment_method_script_handles() {
        /* **************************VERSION 1************************** */
        // $script_path       = 'assets/js/frontend/blocks.js';
        // $script_asset_path = WCZELLE_PLUGIN_DIR . 'assets/js/frontend/blocks.asset.php';
        // $script_asset      = file_exists( $script_asset_path ) ? require( $script_asset_path ) : array( 'dependencies' => array(), 'version'      => '1.2.0' );
        // $script_url        = WCZELLE_PLUGIN_DIR_URL . $script_path;
        // wp_register_script(
        // 	'wc_zelle_gateway_blocks',
        // 	$script_url,
        // 	$script_asset[ 'dependencies' ],
        // 	$script_asset[ 'version' ],
        // 	true
        // );
        /* **************************VERSION 2************************** */
        wp_register_script(
            'wc_zelle_gateway_blocks',
            WCZELLE_PLUGIN_DIR_URL . 'assets/js/frontend/blocks.js',
            array(
                'wc-blocks-registry',
                'wc-settings',
                //'wc-utils',
                'wp-element',
                'wp-html-entities',
            ),
            null,
            // or time() or filemtime( ... ) to skip caching
            true
        );
        /* **************************VERSION 3************************** */
        // wp_register_script_module
        // wp_register_script(
        // 	'wc_zelle_gateway_blocks',
        // 	WCZELLE_PLUGIN_DIR_URL .  'assets/js/frontend/index.js',
        // 	array(),
        // 	null, // or time() or filemtime( ... ) to skip caching
        // 	true
        // );
        if ( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations( 'wc_zelle_gateway_blocks', WCZELLE_PLUGIN_TEXT_DOMAIN, WCZELLE_PLUGIN_DIR . 'languages/' );
        }
        return ['wc_zelle_gateway_blocks'];
    }

    public function get_payment_method_data() {
        // $arr = [
        // 	'title'       => $this->gateway->title,
        // 	//'checkout_description' => $this->get_setting( 'checkout_description' ),
        // 	'description'       => $this->get_setting( 'checkout_description' ),
        // 	'icon'       => $this->gateway->icon,
        // 	//'supports'  => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),
        // 	'supports'	=> $this->get_supported_features(),
        // ];
        $data = $this->settings;
        $data['icon'] = $this->gateway->icon;
        $data['title'] = $this->gateway->title;
        $data['description'] = "Place your order then follow instructions on the order confirmation page to pay";
        // $data['description'] = sanitize_text_field('<a href="https://example.com">a link</a> <strong>bold</strong> <em>italicized</em>');
        // $data['description'] = wp_kses_post('<a href="https://example.com">a link</a> <strong>bold</strong> <em>italicized</em>');
        $data['supports'] = $this->get_supported_features();
        return $data;
    }

}
