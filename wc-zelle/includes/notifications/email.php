<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
// $order = wc_get_order( $order_id );
$amount = $order->get_total();
$currency = $order->get_currency();
// $total = "$amount $currency";
// $total = $order->get_total();
$total = $order->get_formatted_order_total();
echo '<p>' . sprintf( wp_kses_post( __( 'Send the requested total via %s or from your bank', WCZELLE_PLUGIN_TEXT_DOMAIN ) ), '<a style="color: #6d1fd4" href="https://zellepay.com/" target="_blank">Zelle</a>' ) . '.</p>';
echo '<p>' . esc_html__( 'Here are the Zelle details you should know for the transfer', WCZELLE_PLUGIN_TEXT_DOMAIN ) . ':</p>';
echo '<p>' . sprintf( esc_html__( '%s Name', WCZELLE_PLUGIN_TEXT_DOMAIN ), 'Zelle' ) . ': <strong>', esc_html( $this->ReceiverZelleOwner ), '</strong><br>';
echo sprintf( esc_html__( '%s Email', WCZELLE_PLUGIN_TEXT_DOMAIN ), 'Zelle' ) . ': <strong>', esc_html( $this->ReceiverZELLEEmail ), '</strong><br>';
echo sprintf( esc_html__( '%s Phone', WCZELLE_PLUGIN_TEXT_DOMAIN ), 'Zelle' ) . ': <strong>', esc_html( $this->ReceiverZELLENo ), '</strong></p>';