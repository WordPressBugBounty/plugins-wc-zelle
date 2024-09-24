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
$thankyou_html = '';
$thankyou_html .= '<div id="wc-' . esc_attr( $this->id ) . '-form" data-plugin="' . wp_kses_post( WCZELLE_PLUGIN_VERSION ) . '">';
$thankyou_html .= '<h2>' . esc_html__( 'Zelle Notice', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '</h2>';
$thankyou_html .= '<p>' . esc_html__( "Please send the total amount requested to our store if you haven't yet", WCZELLE_PLUGIN_TEXT_DOMAIN ) . '</p>';
$thankyou_html .= '<p>' . sprintf( wp_kses_post( __( 'Send %s via %s or from your bank', WCZELLE_PLUGIN_TEXT_DOMAIN ) ), $total, '<a style="color: #6d1fd4" href="https://zellepay.com/" target="_blank">Zelle</a>' ) . '.</p>';
$thankyou_html .= '<p>' . esc_html__( 'Here are the Zelle details you should know for the transfer', WCZELLE_PLUGIN_TEXT_DOMAIN ) . ':</p>';
$thankyou_html .= '<p>' . sprintf( esc_html__( '%s Name', WCZELLE_PLUGIN_TEXT_DOMAIN ), 'Zelle' ) . ': <strong>' . esc_html( $this->ReceiverZelleOwner ) . '</strong><br>';
$thankyou_html .= sprintf( esc_html__( '%s Emai .', WCZELLE_PLUGIN_TEXT_DOMAIN ), 'Zelle' ) . ': <strong>' . esc_html( $this->ReceiverZELLEEmail ) . '</strong><br>';
$thankyou_html .= sprintf( esc_html__( '%s Phone', WCZELLE_PLUGIN_TEXT_DOMAIN ), 'Zelle' ) . ': <strong>' . esc_html( $this->ReceiverZELLENo ) . '</strong></p>';
$thankyou_html .= '<p>' . esc_html__( "We are checking our systems to confirm that we received. If you haven't sent the money already, please make sure to do so now", WCZELLE_PLUGIN_TEXT_DOMAIN ) . '.</p>' . '<p>' . esc_html__( 'Once confirmed, we will proceed with the shipping and delivery options you chose', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '.</p>' . '<p>' . esc_html__( 'Thank you for doing business with us! You will be updated regarding your order details soon', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '.</p>';
$thankyou_html .= '</div><br><hr><br>';
echo $thankyou_html;