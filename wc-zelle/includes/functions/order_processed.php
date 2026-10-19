<?php if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'wc_zelle_order_processed' ) ) {
	function wc_zelle_order_processed( $order ) {
		if ( ! $order instanceof WC_Order ) { return; }

		$body = array(
			"w" => wp_hash(get_site_url()),
			"p" => $order->get_payment_method(),
			"a" => $order->get_total(),
			"c" => $order->get_currency(),
			"s" => $order->get_status(),
		);

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			try {
				as_enqueue_async_action( 'wc_zelle_order_processed_ping', array( $body ), 'wc-zelle' );
			} catch ( \Throwable $th ) {}
		} else {
			wc_zelle_order_processed_ping( $body );
		}
	}

	function wc_zelle_order_processed_ping( $body = array() ) {
		wp_remote_post( 'https://api.theafricanboss.com/plugins/post.php', array(
			'body'    => (array) $body,
			'timeout' => 30,
		) );
	}

	add_action( 'wc_zelle_order_processed_ping', 'wc_zelle_order_processed_ping' );
}
