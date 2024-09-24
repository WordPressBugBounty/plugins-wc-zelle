<?php if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'WooCommerce', false ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="error"><p><strong>Checkout with Zelle on Woocommerce requires WooCommerce to be installed and active.</strong> <a href="' . esc_url(admin_url('plugin-install.php?s=woocommerce&tab=search&type=term')) . '">Download and Activate WooCommerce here</a></p></div>';
		echo '<div class="notice notice-success"><p><strong>Checkout with Zelle on Woocommerce Plugin has been deactivated.</strong></p></div>';
	});
}

?>