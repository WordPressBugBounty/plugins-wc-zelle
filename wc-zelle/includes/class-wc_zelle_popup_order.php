<?php if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'WC_Zelle_Popup_Order' ) ):

/**
 * The pending order behind the checkout popup (PRO, checkout design 1).
 *
 * A Zelle payment is made in the customer's own banking app, and it needs the order
 * number as its memo, so the popup cannot open with "available once you place order"
 * in it. The order is therefore created before the popup opens.
 *
 * WooCommerce creates it, not this class. The popup posts the checkout form to
 * WooCommerce's own checkout endpoint with wc_zelle_popup=1, so every validation,
 * hook and field mapping runs exactly as it would for a normal order. We only step in
 * once the order exists, to answer with its details instead of taking the payment.
 *
 * The order is saved as pending payment, never checkout-draft: the customer may pay with its number straight away,
 * and WooCommerce deletes checkout drafts that are a day old.
 *
 * When the customer confirms, the checkout is not submitted again: wc_zelle_confirm_order()
 * moves this very order to on-hold, or leaves it alone when it is already paid, and sends
 * the customer to its order received page. The block checkout still submits its Store API
 * checkout for an unpaid order, which resumes this same order.
 *
 * Closing the popup keeps it and reopening lands on the same one, through the session's
 * order_awaiting_payment key, the mechanism WooCommerce uses to retry a failed payment.
 * If the customer pays with another method instead, or WooCommerce opens a fresh order
 * for the popup, it is only released from the session with an order note, in case it was
 * already paid. Abandoned ones follow WooCommerce's own unpaid order rules
 *
 * @see WC_Checkout::create_order()
 */
class WC_Zelle_Popup_Order {

	public $id = 'zelle';

	/** Session key holding the order behind this session's popup. */
	const SESSION_KEY = 'wc_zelle_popup_order';

	/** Session key holding the order the popup last confirmed, so a repeated confirm lands on it again. */
	const CONFIRMED_KEY = 'wc_zelle_confirmed_order';

	/** WooCommerce's own session key for the block checkout's order, read by its Store API. Never rename it. @see DraftOrderTrait */
	const BLOCKS_POPUP_KEY = 'store_api_draft_order';

	/** wc_zelle_skip_popup() answers, by cart hash. */
	protected $skip_popup = array();

	/** Read before WooCommerce logs the new account in, when this request is a checkout. @see wc_zelle_checkout_process() */
	protected $creates_account = null;

	public function register() {
		// The block checkout's Place order, before the Store API looks up its order: it resumes the one the popup showed
		add_action( 'woocommerce_store_api_checkout_update_customer_from_request', array( $this, 'wc_zelle_store_api_resume_popup_order' ), 10, 2 );

		// Before wc_clear_cart_after_payment() (20), which would empty the cart and leave the customer on an empty cart page
		add_action( 'template_redirect', array( $this, 'wc_zelle_checkout_redirect' ), 15 );

		// The block checkout placed the popup's order, so the session no longer holds it
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'wc_zelle_store_api_order_processed' ) );

		// Before WC_Checkout::create_order(), so a different payment method never
		// inherits or races the order held open for the Zelle popup
		add_action( 'woocommerce_checkout_process', array( $this, 'wc_zelle_checkout_process' ) );

		// The last hook before WC_Checkout::create_order(), where the cart totals are
		// final, so the order the popup showed is updated rather than replaced
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'wc_zelle_refresh_popup_cart_hash' ), 999 );

		// Fires immediately before the order's first save, so the popup's order is written as pending payment
		add_action( 'woocommerce_checkout_create_order', array( $this, 'wc_zelle_checkout_create_order' ), 999, 2 );

		// Last, so other plugins' handlers run: the checkout is not submitted again after the popup
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'wc_zelle_checkout_order_processed' ), PHP_INT_MAX, 3 );

		// The block checkout's popup asks for its contents here. @see wc_zelle_blocks_popup()
		add_action( 'wc_ajax_wc_zelle_blocks_popup', array( $this, 'wc_zelle_blocks_popup' ) );

		// Both popups' confirm button. @see wc_zelle_confirm_order()
		add_action( 'wc_ajax_wc_zelle_confirm_order', array( $this, 'wc_zelle_confirm_order' ) );
	}

	/**
	 * Right before the order's first save: saves the popup's order as pending payment
	 *
	 * @param WC_Order $order Order about to be saved.
	 * @param array    $data  Posted checkout data.
	 */
	public function wc_zelle_checkout_create_order( $order, $data ) {
		if ( ! $order instanceof WC_Order || $this->id !== $order->get_payment_method() ) { return; }

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC_Checkout::process_checkout() verified the checkout nonce before this ran
		if ( empty( $_POST['wc_zelle_popup'] ) || $this->wc_zelle_skip_popup() ) {
			if ( 'checkout-draft' === $order->get_status() ) {
				$order->set_status( 'pending', __( 'Order number previously shown to the customer in the Zelle checkout popup, awaiting their confirmation.', WCZELLE_PLUGIN_TEXT_DOMAIN ) );
			}
			return;
		}

		if ( ! $this->wc_zelle_use_popup() ) { return; }

		$order->set_status( 'pending', __( 'Order number shown to the customer in the Zelle checkout popup, awaiting their confirmation.', WCZELLE_PLUGIN_TEXT_DOMAIN ) );
	}

	/**
	 * What the block checkout's popup shows, rendered fresh on request.
	 *
	 * The block checkout does not run WC_Checkout::process_checkout(), so none of the
	 * classic popup order handling above applies to it. The Store API keeps its own order
	 * in the session (created as checkout-draft, switched to pending below once its number
	 * is shown) and finalises that very same order on submit, so the number shown here is
	 * the number the customer will be charged against.
	 * @see wc_zelle_blocks_popup_order() for where that order comes from.
	 *
	 * The markup is rendered here rather than passed through get_payment_method_data()
	 * because that is localised once when the page is built - the amount in it would go
	 * stale the moment the customer changed their cart or shipping.
	 *
	 * @see Automattic\WooCommerce\StoreApi\Utilities\DraftOrderTrait
	 */
	public function wc_zelle_blocks_popup() {
		if ( ! $this->wc_zelle_use_popup() ) {
			wp_send_json( array( 'html' => '', 'number' => '' ) );
		}

		// Not sent by the checkout page: no order, and the checkout is placed without the popup
		if ( ! check_ajax_referer( 'wc-zelle-popup', 'security', false ) ) {
			wp_send_json( array( 'html' => '', 'number' => '' ) );
		}

		// Paid or confirmed since its popup was shown: that order, not a new one
		$settled = $this->wc_zelle_settled_popup_order();
		if ( $settled ) {
			wp_send_json( array( 'redirect' => $this->wc_zelle_finish_popup_order( $settled ) ) );
		}

		// No popup for this cart: the Store API checkout places the order outright
		if ( $this->wc_zelle_skip_popup() ) {
			wp_send_json( array( 'html' => '', 'number' => '' ) );
		}

		// The Store API's own cart checks (stock, coupons), before the customer pays rather than after
		$error = $this->wc_zelle_blocks_cart_error();
		if ( $error ) {
			wp_send_json( array( 'error' => $error ) );
		}

		$order  = $this->wc_zelle_blocks_popup_order();
		$number = $order ? $order->get_order_number() : '';

		$html = '';
		$gateways = WC()->payment_gateways ? WC()->payment_gateways->payment_gateways() : array();

		$changed = false;

		// The Store API gives a new draft the customer's saved or first enabled payment
		// method, but this popup only opens with Zelle selected
		// Also when only the title is missing: create_order_from_cart() sets the method id alone
		if ( $order && ! empty( $gateways[ $this->id ] ) && ( $this->id !== $order->get_payment_method() || '' === $order->get_payment_method_title() ) ) {
			$order->set_payment_method( $gateways[ $this->id ] );
			$changed = true;
		}

		// The customer now sees its number and may pay with it, so it can no longer be a checkout-draft, which WooCommerce deletes after a day
		if ( $order && 'checkout-draft' === $order->get_status() ) {
			$order->set_status( 'pending', __( 'Order number previously shown to the customer in the Zelle checkout popup, awaiting their confirmation.', WCZELLE_PLUGIN_TEXT_DOMAIN ) );
			$changed = true;
		}

		if ( $changed ) {
			$order->save();
		}

		if ( $order ) {
			$previous = $this->wc_zelle_popup_order_id();
			if ( $previous && $previous !== $order->get_id() ) {
				$this->wc_zelle_release_popup_order( sprintf( 'WooCommerce opened order %d for the popup instead', $order->get_id() ) );
			}
			WC()->session->set( self::SESSION_KEY, $order->get_id() );
			WC()->session->save_data();
		}

		// The template reads the cart for the amount, so without one there is nothing
		// worth rendering and the block checkout falls through to placing the order
		if ( WC()->cart && ! empty( $gateways[ $this->id ] ) ) {
			// Reuses the classic template verbatim, so the two checkouts cannot drift
			ob_start();
			$gateways[ $this->id ]->payment_fields();
			$html = ob_get_clean();
		}

		wp_send_json( array(
			'html'        => $html,
			'number'      => $number,
			// Sent back on confirm, so it is this order that is confirmed even if the session has moved on
			'order_id'    => $order ? $order->get_id() : 0,
			'order_key'   => $order ? $order->get_order_key() : '',
			'total'       => $order ? wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) : '',
			'nonce'       => wp_create_nonce( 'wc-zelle-confirm' ),
			'popup_nonce' => wp_create_nonce( 'wc-zelle-popup' ),
		) );
	}

	/**
	 * Stock and coupon problems the Store API would reject the order for, as one message
	 * @return string Empty when the cart is fine
	 */
	protected function wc_zelle_blocks_cart_error() {
		$controller = '\Automattic\WooCommerce\StoreApi\Utilities\CartController';
		if ( ! class_exists( $controller ) || ! WC()->cart || WC()->cart->is_empty() ) { return ''; }

		try {
			( new $controller() )->validate_cart();
		} catch ( \Exception $e ) {
			$error = method_exists( $e, 'getError' ) ? $e->getError() : null;
			return wp_strip_all_tags( is_wp_error( $error ) ? implode( ' ', $error->get_error_messages() ) : $e->getMessage() );
		} catch ( \Throwable $e ) {
			return '';
		}

		return '';
	}

	/**
	 * The customer confirmed in the popup. The order behind it is their order: it is moved to
	 * on-hold, or left alone when already paid, and they are sent to its order received page.
	 *
	 * Block checkout: an unpaid order is handed back so the Store API checkout runs, as it
	 * carries the order note, extra fields and account creation, and it resumes this order.
	 */
	public function wc_zelle_confirm_order() {
		check_ajax_referer( 'wc-zelle-confirm', 'security' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above
		$block    = ! empty( $_POST['block'] );
		$current  = $this->wc_zelle_popup_order_id();
		$order_id = $current;

		// The order the popup showed, which its key proves, even when the session has since moved on to another popup
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above
		$shown = isset( $_POST['order_id'], $_POST['order_key'] ) ? wc_get_order( absint( $_POST['order_id'] ) ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above
		if ( $shown instanceof WC_Order && $shown->key_is_valid( wc_clean( wp_unslash( $_POST['order_key'] ) ) ) ) {
			$order_id = $shown->get_id();
		}

		// The same confirm again, e.g. its first response never reached the browser
		if ( ! $order_id && WC()->session ) {
			$confirmed = wc_get_order( absint( WC()->session->get( self::CONFIRMED_KEY, 0 ) ) );
			if ( $confirmed instanceof WC_Order && ( $confirmed->is_paid() || $confirmed->has_status( 'on-hold' ) ) ) {
				$order_id = $confirmed->get_id();
			}
		}

		$order = $order_id ? wc_get_order( $order_id ) : null;
		$gateways = WC()->payment_gateways ? WC()->payment_gateways->payment_gateways() : array();

		// Anything unexpected falls back to the checkout submit
		if ( ! $this->wc_zelle_use_popup() || ! $order instanceof WC_Order || $this->id !== $order->get_payment_method() || empty( $gateways[ $this->id ] ) ) {
			wp_send_json( array( 'result' => 'failure' ) );
		}
		$gateway = $gateways[ $this->id ];

		// Cancelled by WooCommerce's hold stock timer while the popup was open, and never paid
		if ( $order->has_status( 'cancelled' ) && ! $order->get_date_paid() && ! $order->get_transaction_id() && in_array( $order->get_created_via(), array( 'checkout', 'store-api' ), true ) ) {
			$order->update_status( 'pending', __( 'Reopened: the customer confirmed this order in the Zelle checkout popup after it had been cancelled.', WCZELLE_PLUGIN_TEXT_DOMAIN ) );
		}

		// Only the session's current popup order is the one the Store API checkout resumes; an older popup's order is confirmed directly, as on the classic checkout
		if ( $block && $order->needs_payment() && $current === $order->get_id() ) {
			wp_send_json( array( 'result' => 'proceed' ) );
		}

		if ( $order->needs_payment() ) {
			$result = $gateway->process_payment( $order->get_id() );
		} else {
			// Already paid, or already confirmed: its status is not altered
			$result = array( 'result' => 'success', 'redirect' => $gateway->get_return_url( $order ) );
		}

		if ( ! is_array( $result ) || empty( $result['result'] ) || 'success' !== $result['result'] ) {
			wp_send_json( array( 'result' => 'failure' ) );
		}
		$result = apply_filters( 'woocommerce_payment_successful_result', $result, $order->get_id() );

		$this->wc_zelle_finish_popup_order( $order );

		wp_send_json( $result );
	}

	/**
	 * The order this session's popup showed, once it is paid or confirmed and the cart is still its cart
	 * @return WC_Order|null
	 */
	protected function wc_zelle_settled_popup_order() {
		if ( ! WC()->cart || WC()->cart->is_empty() ) { return null; }

		$order_id = $this->wc_zelle_popup_order_id();
		$order    = $order_id ? wc_get_order( $order_id ) : null;

		if ( ! $order instanceof WC_Order || $this->id !== $order->get_payment_method() ) { return null; }
		if ( ! $order->is_paid() && ! $order->has_status( 'on-hold' ) ) { return null; }

		return $order->has_cart_hash( WC()->cart->get_cart_hash() ) ? $order : null;
	}

	/**
	 * The popup's order is done with: its cart is emptied and the session lets go of it
	 * @param WC_Order $order The popup's order.
	 * @return string Its order received page
	 */
	protected function wc_zelle_finish_popup_order( $order ) {
		if ( WC()->cart && $order->has_cart_hash( WC()->cart->get_cart_hash() ) ) {
			WC()->cart->empty_cart();
		}

		// Only this order's keys: an older popup's confirm must not drop the order the session holds now
		if ( $this->wc_zelle_popup_order_id() === $order->get_id() ) {
			$this->wc_zelle_forget_popup_order();
		}
		if ( absint( WC()->session->get( 'order_awaiting_payment' ) ) === $order->get_id() ) {
			WC()->session->set( 'order_awaiting_payment', null );
		}
		if ( absint( WC()->session->get( self::BLOCKS_POPUP_KEY, 0 ) ) === $order->get_id() ) {
			WC()->session->set( self::BLOCKS_POPUP_KEY, null );
		}
		WC()->session->set( self::CONFIRMED_KEY, $order->get_id() );
		WC()->session->save_data();

		$gateways = WC()->payment_gateways ? WC()->payment_gateways->payment_gateways() : array();
		return ! empty( $gateways[ $this->id ] ) ? $gateways[ $this->id ]->get_return_url( $order ) : $order->get_checkout_order_received_url();
	}

	/**
	 * The checkout reloaded after the popup's order was paid or confirmed, e.g. while the customer was in their banking app
	 */
	public function wc_zelle_checkout_redirect() {
		if ( ! WC()->session || ! is_checkout() || is_wc_endpoint_url() || ! $this->wc_zelle_use_popup() ) { return; }

		$order = $this->wc_zelle_settled_popup_order();
		if ( ! $order ) { return; }

		wp_safe_redirect( $this->wc_zelle_finish_popup_order( $order ) );
		exit;
	}

	/**
	 * The block checkout is placing the popup's order
	 * @param WC_Order $order Order being placed.
	 */
	public function wc_zelle_store_api_order_processed( $order ) {
		if ( $order instanceof WC_Order && $this->wc_zelle_popup_order_id() === $order->get_id() ) {
			$this->wc_zelle_forget_popup_order();
		}
	}

	/**
	 * Block checkout Place order: WooCommerce resumes its session order only while its cart hash matches, so the popup's order is
	 * brought up to date with the posted address before the Store API looks, and parked under its key
	 */
	public function wc_zelle_store_api_resume_popup_order( $customer = null, $request = null ) {
		$order_id = $this->wc_zelle_popup_order_id();
		$order    = $order_id ? wc_get_order( $order_id ) : null;
		if ( ! $order instanceof WC_Order || ! WC()->cart ) { return; }

		// Another payment method gets its own order, as on the classic checkout. @see wc_zelle_checkout_process()
		$method = $request instanceof WP_REST_Request ? (string) $request->get_param( 'payment_method' ) : $this->id;
		if ( $this->id !== $method ) {
			if ( absint( WC()->session->get( self::BLOCKS_POPUP_KEY, 0 ) ) === $order_id ) {
				WC()->session->set( self::BLOCKS_POPUP_KEY, null );
			}
			$this->wc_zelle_release_popup_order( sprintf( 'the customer placed the checkout with %s instead', $method ? $method : 'another payment method' ) );
			return;
		}

		if ( $this->wc_zelle_blocks_update_popup_order( $order ) ) {
			WC()->session->set( self::BLOCKS_POPUP_KEY, $order->get_id() );
		}
	}

	/**
	 * The block checkout's own Store API popup order, created now if it does not exist yet.
	 *
	 * Up to WooCommerce 10.7 the Store API created this draft as soon as the checkout
	 * block loaded, so it was already there by the time the popup opened. From 10.8 it
	 * defers that to the place-order POST, which only happens after the customer confirms
	 * in the popup - so without this the Memo would be empty.
	 *
	 * The same principle as the classic popup: WooCommerce creates the order, not this
	 * class. It goes through the Store API's own OrderController::create_order_from_cart()
	 * and is parked in the Store API's own session key (self::BLOCKS_POPUP_KEY), exactly as
	 * that POST would do it, so when the customer confirms, the POST finds it through
	 * DraftOrderTrait::get_draft_order() and finalises this very order instead of opening
	 * a second one. On WooCommerce 10.7 and older the draft already exists and is simply
	 * read.
	 *
	 * Block checkout only. The classic checkout never calls this and never reads that session key
	 * - its draft lives in self::SESSION_KEY and order_awaiting_payment
	 *
	 * @see Automattic\WooCommerce\StoreApi\Routes\V1\Checkout::create_or_update_draft_order()
	 * @return WC_Order|null Null when there is nothing to create it from, or it failed,
	 *                       in which case the popup is skipped and the order is placed
	 */
	protected function wc_zelle_blocks_popup_order() {
		if ( ! WC()->session || ! WC()->cart ) { return null; }

		$order_id = absint( WC()->session->get( self::BLOCKS_POPUP_KEY, 0 ) );
		$order    = $order_id ? wc_get_order( $order_id ) : null;

		// The Store API's own reuse test, so this never strands an order it would pick up
		if ( $order instanceof WC_Order ) {
			// The popup shows its total as the amount to send, and the cart may have changed since the draft was made
			if ( $order->has_status( 'checkout-draft' ) ) {
				$controller = '\Automattic\WooCommerce\StoreApi\Utilities\OrderController';
				if ( ! class_exists( $controller ) ) { return null; }
				try {
					( new $controller() )->update_order_from_cart( $order, true );
				} catch ( \Throwable $e ) {
					$gateways = WC()->payment_gateways ? WC()->payment_gateways->payment_gateways() : array();
					if ( ! empty( $gateways[ $this->id ] ) ) {
						$gateways[ $this->id ]->wcz_log( 'Block checkout popup skipped, could not update draft order ' . $order->get_id() . ': ' . $e->getMessage(), 'error' );
					}
					return null;
				}
				return $order;
			}
			if ( $order->needs_payment() && $order->has_cart_hash( WC()->cart->get_cart_hash() ) ) { return $order; }

			// The order this popup already showed, after a cart change or the hold stock timer: kept, so its number stays the memo
			$updated = $this->wc_zelle_blocks_update_popup_order( $order );
			if ( $updated ) { return $updated; }
		}

		$controller = '\Automattic\WooCommerce\StoreApi\Utilities\OrderController';
		if ( ! class_exists( $controller ) || WC()->cart->is_empty() ) { return null; }

		// No new order without the customer's email, so bots and empty forms create none
		if ( ! WC()->customer || ! is_email( WC()->customer->get_billing_email() ) ) { return null; }

		try {
			$controller = new $controller();
			$order      = $controller->create_order_from_cart();
		} catch ( \Throwable $e ) {
			$gateways = WC()->payment_gateways ? WC()->payment_gateways->payment_gateways() : array();
			if ( ! empty( $gateways[ $this->id ] ) ) {
				$gateways[ $this->id ]->wcz_log( 'Block checkout popup skipped, WooCommerce could not create its order: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 'error' );
			}
			return null;
		}

		if ( ! $order instanceof WC_Order || ! $order->get_id() ) { return null; }

		WC()->session->set( self::BLOCKS_POPUP_KEY, $order->get_id() );
		// wp_send_json() follows, so the session is written now rather than left to shutdown
		WC()->session->save_data();

		return $order;
	}

	/**
	 * Bring the unpaid order this session's popup showed up to date with the cart, as the classic checkout does
	 * @param WC_Order $order The Store API's session order.
	 * @return WC_Order|null Null when it is not that order, or cannot be updated
	 */
	protected function wc_zelle_blocks_update_popup_order( $order ) {
		if ( $this->wc_zelle_popup_order_id() !== $order->get_id() || 'store-api' !== $order->get_created_via() ) { return null; }
		if ( $order->get_date_paid() || $order->get_transaction_id() || ! $order->has_status( array( 'pending', 'failed', 'cancelled' ) ) ) { return null; }

		$controller = '\Automattic\WooCommerce\StoreApi\Utilities\OrderController';
		if ( ! class_exists( $controller ) ) { return null; }

		try {
			if ( $order->has_status( 'cancelled' ) ) {
				$order->set_status( 'pending', __( 'Reopened: the customer opened the Zelle checkout popup again after this order had been cancelled.', WCZELLE_PLUGIN_TEXT_DOMAIN ) );
			}
			// Saves the order, with the cart hash the Store API checkout looks for
			( new $controller() )->update_order_from_cart( $order, true );
		} catch ( \Throwable $e ) {
			$gateways = WC()->payment_gateways ? WC()->payment_gateways->payment_gateways() : array();
			if ( ! empty( $gateways[ $this->id ] ) ) {
				$gateways[ $this->id ]->wcz_log( 'Block checkout popup could not update order ' . $order->get_id() . ': ' . $e->getMessage(), 'error' );
			}
			return null;
		}

		return $order;
	}

	/**
	 * Release the popup's order when the checkout is placed with another payment method, before that method creates its own order
	 */
	public function wc_zelle_checkout_process() {
		$this->creates_account = null;
		$this->creates_account = $this->wc_zelle_creates_account();

		if ( ! $this->wc_zelle_use_popup() ) { return; }

		// Paid or confirmed since the popup showed it, e.g. emailreceipts.io matched it while the popup was open: this cart is that order
		$settled = $this->wc_zelle_settled_popup_order();
		if ( $settled ) {
			$redirect = $this->wc_zelle_finish_popup_order( $settled );
			if ( wp_doing_ajax() ) {
				wp_send_json( array( 'result' => 'success', 'redirect' => $redirect ) );
			}
			wp_safe_redirect( $redirect );
			exit;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC_Checkout::process_checkout() verified the checkout nonce before this ran
		$method = isset( $_POST['payment_method'] ) ? wc_clean( wp_unslash( $_POST['payment_method'] ) ) : '';
		if ( $this->id === $method ) { return; }

		$this->wc_zelle_release_popup_order( sprintf( 'the customer placed the checkout with %s instead', $method ? $method : 'another payment method' ) );
	}

	/**
	 * The order this session is holding open behind the popup, if any. 0 with no session,
	 * which is every admin screen and cron run
	 * @return int
	 */
	protected function wc_zelle_popup_order_id() {
		return WC()->session ? absint( WC()->session->get( self::SESSION_KEY ) ) : 0;
	}

	/**
	 * Keep the order behind the popup in step with the cart, right before create_order() decides whether to resume it.
	 *
	 * create_order() resumes an order only while it is pending or failed and its cart hash
	 * still matches - a cart, coupon or shipping change between opening the popup and
	 * confirming would otherwise strand one order and start another. Handing it the current
	 * hash makes it resume this one, and it then rebuilds the line items, addresses and
	 * totals from the cart, so the order is updated in place and keeps the number the
	 * customer was given for their memo.
	 *
	 * @see WC_Checkout::create_order()
	 */
	public function wc_zelle_refresh_popup_cart_hash() {
		if ( ! $this->wc_zelle_use_popup() || ! WC()->session || ! WC()->cart ) { return; }

		// Emptied by wc_zelle_checkout_process() when another payment method is placing this order,
		// which is what keeps that order from resuming the draft
		$order_id = $this->wc_zelle_popup_order_id();
		if ( ! $order_id ) { return; }

		$order = wc_get_order( $order_id );
		if ( ! $order || $this->id !== $order->get_payment_method() ) { return; }
		if ( ! $order->has_status( array( 'pending', 'failed', 'checkout-draft', 'cancelled' ) ) ) { return; }

		$changed = false;

		// Cancelled by WooCommerce's hold stock timer while the popup was open: reopened so the customer confirms the order number they may already have paid with
		if ( 'cancelled' === $order->get_status() ) {
			if ( $order->is_paid() || $order->get_date_paid() || $order->get_transaction_id() || 'checkout' !== $order->get_created_via() ) { return; }
			$order->set_status( 'pending', __( 'Reopened: the customer opened the Zelle checkout popup again after this order had been cancelled.', WCZELLE_PLUGIN_TEXT_DOMAIN ) );
			$changed = true;
		}

		// A checkout-draft whose popup was open during the update: create_order() only resumes pending or failed
		if ( 'checkout-draft' === $order->get_status() ) {
			$order->set_status( 'pending', __( 'Order number shown to the customer in the Zelle checkout popup, awaiting their confirmation.', WCZELLE_PLUGIN_TEXT_DOMAIN ) );
			$changed = true;
		}

		$cart_hash = WC()->cart->get_cart_hash();
		if ( ! $order->has_cart_hash( $cart_hash ) ) {
			$order->set_cart_hash( $cart_hash );
			$changed = true;
		}

		// create_order() holds its coupons again, and its own earlier hold would count against a limited coupon
		if ( $order->get_coupon_codes() && function_exists( 'wc_release_coupons_for_order' ) ) {
			wc_release_coupons_for_order( $order, false );
			$changed = true;
		}

		if ( $changed ) { $order->save(); }
	}

	/**
	 * Is the checkout popup the active design? Read from the stored settings through the gateway's own design rules
	 *
	 * @see WC_Zelle_Gateway::wc_zelle_design()
	 * @return bool
	 */
	public function wc_zelle_use_popup() {
		if ( ! class_exists( 'WC_Zelle_Gateway' ) || ! function_exists( 'zelle_fs' ) ) { return false; }

		$settings = get_option( "woocommerce_{$this->id}_settings", array() );
		$stored   = is_array( $settings ) && isset( $settings['display_zelle'] ) ? $settings['display_zelle'] : '';

		return '1' === WC_Zelle_Gateway::wc_zelle_design( $stored );
	}

	/**
	 * Carts placed without the popup, straight to the order received page, which shows the same Zelle details
	 * @return bool
	 */
	public function wc_zelle_skip_popup() {
		// The popup's checkout would create the account and send its welcome email before the customer confirms
		if ( $this->wc_zelle_creates_account() ) { return true; }

		if ( ! WC()->cart ) { return false; }

		// Once per cart and request, so the order's own stock hold, taken mid-checkout, cannot change the answer
		$hash = WC()->cart->get_cart_hash();
		if ( ! isset( $this->skip_popup[ $hash ] ) ) {
			$this->skip_popup[ $hash ] = $this->wc_zelle_cart_skips_popup( WC()->cart );
		}
		return $this->skip_popup[ $hash ];
	}

	/**
	 * Will the classic checkout create an account for this guest? WooCommerce's own test. @see WC_Checkout::process_customer()
	 * @return bool
	 */
	public function wc_zelle_creates_account() {
		if ( null !== $this->creates_account ) { return $this->creates_account; }

		// The block checkout creates its account only on the checkout that follows the confirm
		if ( doing_action( 'wc_ajax_wc_zelle_blocks_popup' ) || is_user_logged_in() || ! function_exists( 'WC' ) || ! WC()->checkout() ) { return false; }

		$checkout = WC()->checkout();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- only reads whether the guest asked for an account
		$ticked = $checkout->is_registration_enabled() && ! empty( $_POST['createaccount'] );

		return $checkout->is_registration_required() || $ticked;
	}

	/**
	 * @param WC_Cart $cart The cart being checked out.
	 * @return bool
	 */
	protected function wc_zelle_cart_skips_popup( $cart ) {
		// The popup's waiting order would hold its coupons, and the next checkout pass counts that hold against them
		if ( $cart->get_applied_coupons() ) { return true; }

		// Gift cards, store credit, points or deposits as a negative fee: the waiting order would spend the balance
		foreach ( $cart->get_fees() as $fee ) {
			if ( isset( $fee->total ) && (float) $fee->total < 0 ) { return true; }
		}
		// A total another plugin changed after WooCommerce added it up, either way: the waiting order may not keep it on a later pass
		$parts = (float) $cart->get_cart_contents_total() + (float) $cart->get_cart_contents_tax() + (float) $cart->get_shipping_total() + (float) $cart->get_shipping_tax() + (float) $cart->get_fee_total() + (float) $cart->get_fee_tax();
		if ( abs( round( $parts - (float) $cart->get_total( 'edit' ), wc_get_price_decimals() ) ) > 0.01 ) { return true; }

		// Subscriptions, bookings, tickets and other plugin types create their own records with the order
		foreach ( $cart->get_cart() as $item ) {
			$product = isset( $item['data'] ) ? $item['data'] : null;
			if ( $product instanceof WC_Product && ! in_array( $product->get_type(), array( 'simple', 'variable', 'variation', 'grouped', 'external' ), true ) ) { return true; }
		}

		// The last units: the waiting order's stock hold expires, and a late confirm would sell stock already sold
		$exclude = $this->wc_zelle_popup_order_id();
		if ( ! $exclude && WC()->session ) { $exclude = absint( WC()->session->get( self::BLOCKS_POPUP_KEY, 0 ) ); }
		foreach ( $cart->get_cart_item_quantities() as $product_id => $quantity ) {
			$product = wc_get_product( $product_id );
			if ( ! $product || ! $product->managing_stock() || $product->backorders_allowed() ) { continue; }
			if ( (float) $product->get_stock_quantity() - wc_get_held_stock_quantity( $product, $exclude ) - $quantity <= 0 ) { return true; }
		}

		return false;
	}

	/**
	 * The order has been created and is still pending. Either hand it to the popup, or
	 * let the checkout carry on to the payment because the customer confirmed it
	 *
	 * @param int      $order_id Order ID.
	 * @param array    $posted_data Posted checkout data.
	 * @param WC_Order $order Order object.
	 */
	public function wc_zelle_checkout_order_processed( $order_id, $posted_data, $order ) {
		if ( ! $order || $this->id !== $order->get_payment_method() ) { return; }

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC_Checkout::process_checkout() verified the checkout nonce before this ran
		$is_popup = ! empty( $_POST['wc_zelle_popup'] ) && ! $this->wc_zelle_skip_popup();

		if ( ! $is_popup || ! $this->wc_zelle_use_popup() ) {
			// This is the order the customer confirmed, no longer held for the popup
			$this->wc_zelle_forget_popup_order();
			return;
		}

		// WooCommerce normally resumes the popup's last order; if it opened a new one, the old one will never be paid
		$previous = $this->wc_zelle_popup_order_id();
		if ( $previous && $previous !== absint( $order_id ) ) {
			$this->wc_zelle_release_popup_order( sprintf( 'WooCommerce opened order %d for the popup instead', $order_id ) );
		}
		if ( $previous !== absint( $order_id ) ) {
			$order->add_order_note( __( 'Order number shown to the customer in the Zelle checkout popup, awaiting their confirmation.', WCZELLE_PLUGIN_TEXT_DOMAIN ) );
		}

		// So the checkout that follows resumes this order instead of creating a second one
		if ( WC()->session ) {
			WC()->session->set( 'order_awaiting_payment', $order_id );
			WC()->session->set( self::SESSION_KEY, $order_id );
			WC()->session->save_data();
		}

		// Already pending. Stops before process_order_payment(), so no stock, email or cart changes until wc_zelle_confirm_order()
		wp_send_json( array(
			'result'   => 'zelle_popup',
			'order_id' => $order_id,
			// Sent back on confirm, so it is this order that is confirmed even if the session has moved on
			'order_key' => $order->get_order_key(),
			'number'   => $order->get_order_number(),
			// The order's own total, which may differ from the one rendered at the last checkout refresh
			'total'    => wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ),
			'nonce'    => wp_create_nonce( 'wc-zelle-confirm' ),
		) );
	}

	/**
	 * Stop holding the popup's order for this session. Never deleted: the customer may already have paid with its number
	 * @param string $reason Why, for the order note and the log.
	 */
	protected function wc_zelle_release_popup_order( $reason ) {
		if ( ! WC()->session ) { return; }

		$order_id = $this->wc_zelle_popup_order_id();
		if ( ! $order_id ) { return; }

		$this->wc_zelle_forget_popup_order();

		if ( absint( WC()->session->get( 'order_awaiting_payment' ) ) === $order_id ) {
			WC()->session->set( 'order_awaiting_payment', null );
			WC()->session->save_data();
		}

		$order = wc_get_order( $order_id );
		if ( $order instanceof WC_Order ) {
			/* translators: %s: reason the order was released */
			$order->add_order_note( sprintf( __( 'No longer held for the Zelle checkout popup (%s). Kept in case the customer already paid with this order number.', WCZELLE_PLUGIN_TEXT_DOMAIN ), $reason ) );
		}
	}

	/** Stop holding the stored order for this session's popup. */
	protected function wc_zelle_forget_popup_order() {
		if ( ! WC()->session || ! WC()->session->get( self::SESSION_KEY ) ) { return; }
		WC()->session->set( self::SESSION_KEY, null );
		WC()->session->save_data();
	}

}

add_action( 'init', function () {
	global $WC_Zelle_Popup_Order;
	$WC_Zelle_Popup_Order = new WC_Zelle_Popup_Order();
	$WC_Zelle_Popup_Order->register();
}, 5 );

endif;
