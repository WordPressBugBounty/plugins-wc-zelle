<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
$checkout_html = '';
global $woocommerce;
//$WC_Cart = new WC_Cart();
$amount = $woocommerce->cart->total;
//$total = $WC_Cart->get_total();
$total = $woocommerce->cart->get_total();
//$total = $woocommerce->cart->total;
// The order-pay page has no cart, so its total comes from the order being paid
$order_pay = ( is_checkout_pay_page() ? wc_get_order( absint( get_query_var( 'order-pay' ) ) ) : null );
if ( $order_pay ) {
    $amount = $order_pay->get_total();
    $total = $order_pay->get_formatted_order_total();
}
$checkout_html .= '<fieldset id="wc-' . esc_attr( $this->id ) . '-form" data-plugin="' . wp_kses_post( WCZELLE_PLUGIN_VERSION ) . '">';
do_action( 'woocommerce_form_start', $this->id );
// upgrade display_zelle
$this->wc_zelle_upgrade_display_zelle();
// 1, 2 or 3, after the values that no longer exist have been mapped and after design 1
// has fallen back to design 2 for anything not on the PRO plan
$design = $this->wc_zelle_display();
// Design 1 (PRO) shows the details below in a popup on Place order. Not on order-pay, whose order already exists, so it shows them inline
$use_popup = '1' === $design && !is_checkout_pay_page();
if ( empty( $this->ReceiverZELLEEmail ) && empty( $this->ReceiverZELLENo ) && empty( $this->ReceiverZelleTag ) ) {
    $checkout_html .= '<p>' . wp_kses_post( __( 'Please finish setting up this payment method or contact the admin to do so.', WCZELLE_PLUGIN_TEXT_DOMAIN ) ) . '</p>';
    do_action( 'woocommerce_form_end', $this->id );
    $checkout_html .= '<input name="' . $this->id . 'do_not_checkout" type="hidden" value="true"><div class="clear"></div></fieldset>';
    echo $checkout_html;
    return;
}
// $checkout_html .= '<p>' . wp_kses_post( sprintf( __( 'Send %s via %s or from your bank', WCZELLE_PLUGIN_TEXT_DOMAIN ), $total, '<a style="color: #6d1fd4" href="https://zellepay.com/" target="_blank">Zelle</a>'  ) ) . '.</p>';
$checkout_html .= '<p>' . wp_kses_post( sprintf( __( 'Send %s from your bank via Zelle', WCZELLE_PLUGIN_TEXT_DOMAIN ), $total ) ) . '.</p>';
$checkout_html .= '<p>' . esc_html__( 'Here are the Zelle details you should know for the transfer', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '</p>';
$checkout_html .= '<p>' . sprintf( esc_html__( '%s Name', WCZELLE_PLUGIN_TEXT_DOMAIN ), 'Zelle' ) . ': <strong>' . esc_html( $this->ReceiverZelleOwner ) . '</strong><br>';
$checkout_html .= sprintf( esc_html__( '%s Tag', WCZELLE_PLUGIN_TEXT_DOMAIN ), 'Zelle' ) . ': <strong>' . esc_html( $this->ReceiverZelleTag ) . '</strong><br>';
$checkout_html .= sprintf( esc_html__( '%s Email', WCZELLE_PLUGIN_TEXT_DOMAIN ), 'Zelle' ) . ': <strong>' . esc_html( $this->ReceiverZELLEEmail ) . '</strong><br>';
$checkout_html .= sprintf( esc_html__( '%s Phone', WCZELLE_PLUGIN_TEXT_DOMAIN ), 'Zelle' ) . ': <strong>' . esc_html( $this->ReceiverZELLENo ) . '</strong></p>';
$checkout_html .= '<p>' . wp_kses_post( __( 'Please <strong>use your Order Number (available once you place order)</strong> as the payment reference', WCZELLE_PLUGIN_TEXT_DOMAIN ) ) . '.</p>';
$checkout_html .= '<p><strong>' . esc_html__( 'After paying, please come back here and place the order below', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '</strong> ' . esc_html__( 'so we can start processing your order', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '.</p>';
// Support
$call = '';
$email = '';
if ( !empty( $this->ReceiverZELLENo ) ) {
    $call = esc_html__( 'please call', WCZELLE_PLUGIN_TEXT_DOMAIN ) . ' <a href="tel:' . esc_attr( $this->ReceiverZELLENo ) . '" target="_blank">' . esc_html( $this->ReceiverZELLENo ) . '</a>.';
}
if ( !empty( $this->ReceiverZELLEEmail ) ) {
    $email = ' ' . esc_html__( 'You can also email', WCZELLE_PLUGIN_TEXT_DOMAIN ) . ' <a href="mailto:' . esc_attr( $this->ReceiverZELLEEmail ) . '" target="_blank">' . esc_html( $this->ReceiverZELLEEmail ) . '</a>';
}
// toggleSupport
if ( 'yes' === $this->toggleSupport && !(empty( $this->ReceiverZELLEEmail ) && empty( $this->ReceiverZELLENo )) ) {
    $checkout_html .= '<p>' . esc_html__( 'If you are having an issue', WCZELLE_PLUGIN_TEXT_DOMAIN ) . ', ' . wp_kses_post( ( $call ? $call : '' ) ) . wp_kses_post( ( $email ? $email : '' ) ) . '</p>';
}
// toggleTutorial
if ( 'yes' === $this->toggleTutorial ) {
    $checkout_html .= '<p><a href="https://theafricanboss.com/zelledemo" style="text-decoration: underline" target="_blank">' . esc_html__( 'See this 1min video demo explaining how this works', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '.</a></p>';
}
// // toggleCredits
// if ( 'yes' === $this->toggleCredits ) {
// 	$checkout_html .= '<p><a href="https://theafricanboss.com/zelle" style="text-decoration: underline;" target="_blank">' . sprintf(  esc_html__( 'Powered by %s', WCZELLE_PLUGIN_TEXT_DOMAIN ), 'The African Boss' ) . '</a></p>';
// }
do_action( 'woocommerce_form_end', $this->id );
$checkout_html .= '<div class="clear"></div></fieldset>';
if ( !$use_popup ) {
    echo $checkout_html;
    //return $checkout_html;
    return;
}
$popup_html = '<div class="wcz-popup-teaser" data-plugin="' . wp_kses_post( WCZELLE_PLUGIN_VERSION ) . '">';
// True whether the popup appears or the customer goes straight to the order
// confirmation page, which carries the same details
$popup_html .= '<p>' . esc_html__( 'Place order to get payment details and instructions to proceed', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '.</p>';
// Without JavaScript there is no popup, so the notice stays on the page in a <noscript> (no flash)
$popup_html .= '<noscript>';
if ( !empty( $this->checkout_description ) ) {
    $popup_html .= '<p>' . wp_kses_post( wpautop( wptexturize( __( $this->checkout_description, WCZELLE_PLUGIN_TEXT_DOMAIN ) ) ) ) . '</p>';
}
$popup_html .= '<p>' . esc_html__( 'Your order will not be processed until funds have cleared in our Zelle/Bank account', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '.</p>';
$popup_html .= '</noscript>';
// // Only true once checkout-popup.js is running, which is what unhides it. With no
// // JavaScript there is no window to confirm in, and the order is placed on the spot
// $popup_html .= '<p class="wcz-popup-note" hidden>' . esc_html__( 'Your order is only confirmed once you confirm it in that window', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '.</p>';
$popup_html .= '</div>';
$popup_html .= '<div id="wc-' . esc_attr( $this->id ) . '-popup" class="wcz-popup" role="dialog" aria-modal="true" aria-labelledby="wc-' . esc_attr( $this->id ) . '-popup-title" aria-hidden="true" hidden>';
$popup_html .= '<div class="wcz-popup-overlay" data-wcz-close="1"></div>';
$popup_html .= '<div class="wcz-popup-dialog">';
$popup_html .= '<div class="wcz-popup-header">';
$popup_html .= '<h3 class="wcz-popup-title" id="wc-' . esc_attr( $this->id ) . '-popup-title"><img width="30" height="30" alt="' . esc_attr( $this->method_title ) . '" src="' . esc_url( WCZELLE_PLUGIN_DIR_URL . 'assets/images/zelle_35.png' ) . '" />' . esc_html( $this->title ) . '</h3>';
$popup_html .= '<button type="button" class="wcz-popup-close" data-wcz-close="1" aria-label="' . esc_attr__( 'Close', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '">&times;</button>';
$popup_html .= '</div>';
$popup_html .= '<div class="wcz-popup-body">' . $checkout_html . '</div>';
$popup_html .= '<div class="wcz-popup-footer">';
// The customer confirms the payment was sent. Same markup as WooCommerce's terms checkbox so themes style it
$popup_html .= '<p class="form-row validate-required wcz-popup-confirmed">
	<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
		<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" id="wc-' . esc_attr( $this->id ) . '-confirmed" />
		<span>' . esc_html__( 'I confirm that I have sent the payment', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '</span>
	</label>
</p>';
// Deliberately not .woocommerce-error: checkout.js removes every node carrying that
// class whenever it renders a checkout error, and this one has to outlive that
$popup_html .= '<p class="wcz-popup-error" role="alert" hidden></p>';
$popup_html .= '<button type="button" class="' . esc_attr( wc_zelle_button_class( true, 'wcz-popup-confirm' ) ) . '">' . sprintf( esc_html__( 'I have sent %s, confirm my order', WCZELLE_PLUGIN_TEXT_DOMAIN ), wp_kses_post( $total ) ) . '</button>';
$popup_html .= '</div>';
$popup_html .= '</div></div>';
echo $popup_html;