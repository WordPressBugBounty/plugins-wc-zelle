<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
// namespace Automattic\WooCommerce\Blocks\Payments\Integrations;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
// use Automattic\WooCommerce\Blocks\Assets\Api;
final class WC_Zelle_Gateway_Blocks_Support extends AbstractPaymentMethodType {
    private $gateway;

    public $name = 'zelle';

    protected $settings;

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
        $this->gateway = ( isset( $gateways[$this->name] ) ? $gateways[$this->name] : null );
    }

    public function is_active() {
        if ( empty( $this->gateway ) ) {
            return false;
        }
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
            WCZELLE_VERSION,
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

    /**
     * Data for the block checkout's Zelle payment method, printed publicly in the page as wcSettings zelle_data.
     * Only the keys assets/js/frontend/blocks.js reads, never the stored gateway settings
     *
     * @return array
     */
    public function get_payment_method_data() {
        $use_popup = method_exists( $this->gateway, 'wc_zelle_use_popup' ) && $this->gateway->wc_zelle_use_popup();
        if ( $use_popup ) {
            $description = esc_html__( 'Place order to get payment details and instructions to proceed', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '.';
        } else {
            $description = "Place your order then follow instructions on the order confirmation page to pay";
        }
        return array(
            'title'          => $this->gateway->title,
            'description'    => $description,
            'icon'           => $this->gateway->icon,
            'supports'       => $this->get_supported_features(),
            'popup'          => $use_popup,
            'popup_endpoint' => WC_AJAX::get_endpoint( 'wc_zelle_blocks_popup' ),
            'popup_i18n'     => array(
                'confirm_required' => esc_html__( 'Please confirm that you have sent the payment.', WCZELLE_PLUGIN_TEXT_DOMAIN ),
                'cancelled'        => esc_html__( 'Confirm the payment in the window to place your order.', WCZELLE_PLUGIN_TEXT_DOMAIN ),
            ),
        );
    }

}
