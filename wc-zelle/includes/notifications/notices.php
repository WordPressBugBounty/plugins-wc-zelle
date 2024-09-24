<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !class_exists( 'WooCommerce', false ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="error"><p><strong>Checkout with Zelle on Woocommerce requires WooCommerce to be installed and active.</strong> <a href="' . esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ) . '">Download and Activate WooCommerce here</a></p></div>';
        echo '<div class="notice notice-success"><p><strong>Checkout with Zelle on Woocommerce Plugin has been deactivated.</strong></p></div>';
    } );
}
global $zelle_fs;
if ( !(class_exists( 'WCSMS_WooCommerce_Notification', false ) || is_plugin_active( 'wc-sms/wc-sms.php' ) || is_plugin_active( 'wc-sms-pro/wc-sms.php' )) ) {
    add_action( 'admin_notices', function () {
        echo '<div id="message" class="notice wcsms-notice dismissible">
					<p>You are currently not using <strong>SMS for WooCommerce</strong> with WC Zelle. You can use it to send the Zelle notice, order notifications and more. You can also use it for other payment methods</p>
					<p>
						<a class="button-primary" href="' . esc_url( admin_url( 'plugin-install.php?s=theafricanboss&tab=search&type=author' ) ) . '">Add SMS for Woocommerce for a better Zelle checkout experience</a>
						&nbsp;
						<a href="https://theafricanboss.com/sms" target="_blank">Check it out</a>
					</p>
					<span title="Stop showing this message" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></span>
				</div>';
    } );
}