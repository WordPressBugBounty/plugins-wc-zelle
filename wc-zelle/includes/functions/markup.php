<?php if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Markup helpers shared by the checkout box, thank you page and popup.
 * Buttons reuse WooCommerce's Place order classes so themes style them; the Zelle details are compact text rows
 *
 * @see wc_wp_theme_get_element_class_name()
 * @see woocommerce/templates/checkout/payment.php
 */

if ( ! function_exists( 'wc_zelle_button_class' ) ) {
	/**
	 * The classes WooCommerce puts on the Place order button.
	 *
	 * @param bool   $alt   True for a primary button, false for a secondary one.
	 * @param string $extra Extra classes to append.
	 * @return string
	 */
	function wc_zelle_button_class( $alt = true, $extra = '' ) {
		$classes = array( 'button' );

		if ( $alt ) {
			$classes[] = 'alt';
		}

		// Empty unless the active theme is a block theme with button element styles
		$element = function_exists( 'wc_wp_theme_get_element_class_name' ) ? wc_wp_theme_get_element_class_name( 'button' ) : '';
		if ( $element ) {
			$classes[] = $element;
		}

		if ( $extra ) {
			$classes[] = $extra;
		}

		return implode( ' ', $classes );
	}
}

if ( ! function_exists( 'wc_zelle_copy_row' ) ) {
	/**
	 * One <dt>/<dd> row of the Zelle details: label, value and a Copy button handled by assets/js/copy.js.
	 * Wrap rows in wc_zelle_copy_rows_open()/_close()
	 *
	 * @param string $label      Row label, already escaped.
	 * @param string $value      Value to show and copy.
	 * @param string $attributes Optional extra attributes for the value, already escaped.
	 * @return string
	 */
	function wc_zelle_copy_row( $label, $value = '', $attributes = '' ) {
		$html  = '<div class="theafricanboss-row">';
		$html .= '<dt>' . $label . '</dt>';
		$html .= '<dd><span class="theafricanboss-value"' . ( '' !== $attributes ? ' ' . $attributes : '' ) . '>' . esc_html( $value ) . '</span>';
		$html .= '<button type="button" class="theafricanboss-copy" aria-label="' . esc_attr( sprintf( __( 'Copy %s', WCZELLE_PLUGIN_TEXT_DOMAIN ), wp_strip_all_tags( $label ) ) ) . '">' . esc_html__( 'Copy', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '</button>';
		$html .= '</dd></div>';

		return $html;
	}

	/** Opens the list wc_zelle_copy_row() rows go in. */
	function wc_zelle_copy_rows_open() {
		return '<dl class="wcz-details">';
	}

	/** Closes it. */
	function wc_zelle_copy_rows_close() {
		return '</dl>';
	}
}

if ( ! function_exists( 'wc_zelle_status_icon' ) ) {
	/** The red stop sign beside an unpaid order's status on the thank you page. */
	function wc_zelle_status_icon() {
		return '<svg class="wcz-status-icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="#d63638" d="M7.86 2h8.28L22 7.86v8.28L16.14 22H7.86L2 16.14V7.86z"/><rect fill="#fff" x="6" y="10.5" width="12" height="3" rx="1"/></svg>';
	}
}
