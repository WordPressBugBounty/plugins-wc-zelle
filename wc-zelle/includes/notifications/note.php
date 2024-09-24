<?php if ( ! defined( 'ABSPATH' ) ) { exit; }

// $order = wc_get_order( $order_id );
$amount = $order->get_total();
$currency = $order->get_currency();
// $total = "$amount $currency";
// $total = $order->get_total();
$total = $order->get_formatted_order_total();

$payment_url = $this->wc_zelle_url($amount);
$qr_code_url = $this->wc_zelle_qrcode_url($amount);
$qr_code = $this->wc_zelle_qrcode($amount, 'advanced');

$note = $this->order_note && zelle_fs()->is_plan__premium_only('pro') ? wp_kses_post( str_replace( array( '**order_id**', '**order_total**', '**shop_name**', '**shop_email**', '**shop_url**' ), array( $order_id, $total, get_bloginfo("name"), get_bloginfo("admin_email"), get_site_url() ), $this->order_note )) : esc_html__( 'Your order was received!', WCZELLE_PLUGIN_TEXT_DOMAIN ) .'<br><br>'.
	sprintf( __( 'We are checking our Zelle to confirm that we received the %s you sent so we can start processing your order.', WCZELLE_PLUGIN_TEXT_DOMAIN ), '<strong style="text-transform:uppercase;">' . wp_kses_post( $total ) . '</strong>'  ) .'<br><br>'.
	esc_html__( 'Thank you for doing business with us', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '!<br> ' . esc_html__( 'You will be updated regarding your order details soon', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '<br><br>'.
	esc_html__( 'Kindest Regards', WCZELLE_PLUGIN_TEXT_DOMAIN ) . ',<br>'.
	wp_kses_post(get_bloginfo("name")). '<br>'.
	wp_kses_post(get_bloginfo("admin_email")). '<br>'.
	wp_kses_post(get_site_url()). '<br>';

// some notes to customer (replace true with false to make it private)
$order->add_order_note( $note , true );

?>