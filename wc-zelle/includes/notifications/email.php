<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !$order ) {
    return;
}
$email_html = '';
// $order = wc_get_order( $order_id );
$amount = $order->get_total();
$currency = $order->get_currency();
// $total = "$amount $currency";
// $total = $order->get_total();
$total = $order->get_formatted_order_total();
// $email_html .= '<p>' . sprintf( wp_kses_post( __( 'Send the requested total via %s or from your bank', WCZELLE_PLUGIN_TEXT_DOMAIN ) ), '<a style="color: #6d1fd4" href="https://zellepay.com/" target="_blank">Zelle</a>' ) . '.</p>';
$email_html .= '<p>' . sprintf( wp_kses_post( __( 'Send the requested total from your bank via Zelle', WCZELLE_PLUGIN_TEXT_DOMAIN ) ) ) . '.</p>';
$email_html .= '<p>' . esc_html__( 'Here are the Zelle details you should know for the transfer', WCZELLE_PLUGIN_TEXT_DOMAIN ) . ':</p>';
$email_html .= '<p>' . sprintf( esc_html__( '%s Name', WCZELLE_PLUGIN_TEXT_DOMAIN ), 'Zelle' ) . ': <strong>' . esc_html( $this->ReceiverZelleOwner ) . '</strong><br>';
$email_html .= sprintf( esc_html__( '%s Tag', WCZELLE_PLUGIN_TEXT_DOMAIN ), 'Zelle' ) . ': <strong>' . esc_html( $this->ReceiverZelleTag ) . '</strong><br>';
$email_html .= sprintf( esc_html__( '%s Email', WCZELLE_PLUGIN_TEXT_DOMAIN ), 'Zelle' ) . ': <strong>' . esc_html( $this->ReceiverZELLEEmail ) . '</strong><br>';
$email_html .= sprintf( esc_html__( '%s Phone', WCZELLE_PLUGIN_TEXT_DOMAIN ), 'Zelle' ) . ': <strong>' . esc_html( $this->ReceiverZELLENo ) . '</strong></p>';
if ( !empty( $plain_text ) ) {
    $email_text = preg_replace( array('/<br\\s*\\/?>/i', '/<\\/p>/i'), array("\n", "\n\n"), $email_html );
    $email_text = html_entity_decode( wp_strip_all_tags( $email_text ), ENT_QUOTES, 'UTF-8' );
    echo "\n" . trim( preg_replace( "/\n{3,}/", "\n\n", $email_text ) ) . "\n\n";
    return;
}
echo $email_html;