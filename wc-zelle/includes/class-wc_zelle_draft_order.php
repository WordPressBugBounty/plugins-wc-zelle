<?php if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'WC_Zelle_Draft_Order' ) ):

/**
 * The pending order behind the checkout popup (PRO, checkout design 1).
 *
 * A Zelle payment is made in the customer's own banking app, and it needs the order
 * number as its memo, so the popup cannot open with "available once you place order"
 * in it. The order is therefore created before the popup opens.
 *
 * WooCommerce creates it, not this class. The popup posts the checkout form to
 * WooCommerce's own checkout endpoint with wc_zelle_draft=1, so every validation,
 * hook and field mapping runs exactly as it would for a normal order. We only step in
 * once the order exists, to answer with its details instead of taking the payment.
 *
 * When the customer confirms, the checkout runs again for real and
 * WC_Checkout::create_order() picks this very order back up - same cart hash - through
 * the session's order_awaiting_payment key, which is the same mechanism WooCommerce
 * uses when a customer retries a failed payment. No second order is created, and the
 * number in the popup is the number on the order.
 *
 * That resuming is also why closing the popup keeps the order: reopening it lands on
 * the same one, so nothing is gained by churning through a delete and a re-create. It
 * is only removed once it can no longer be paid - when the customer places the order
 * with a different payment method, or when the cart has moved on far enough that
 * WooCommerce opened a fresh order rather than resuming this one. Anything the
 * customer simply abandons is left to WooCommerce's own unpaid order cleanup
 *
 * From the moment it is inserted until the customer confirms, it sits in the same
 * checkout-draft status the block checkout's own Store API draft order uses
 * (@see \Automattic\WooCommerce\Blocks\Domain\Services\DraftOrders), so it is hidden
 * from the merchant's Orders list the same way - the Drafts tab only, never the count
 * next to "All" - rather than showing up there the instant the popup opens, before the
 * customer has even seen the Zelle details, let alone paid. It never passes through
 * pending on the way, not even for the length of one request: the status is set on
 * woocommerce_checkout_create_order, which fires immediately before the first save, so
 * the row is written as checkout-draft rather than written as pending and corrected.
 *
 * What gets in the way is that core still has places which assume the order a session is
 * holding open for payment is pending, because before the block checkout existed it
 * always was. create_order() resumes only a pending or failed order, and
 * wc_clear_cart_after_payment() runs on every front end page load and reads anything
 * that is not pending, failed or cancelled as an order that has already gone through -
 * so it empties the cart out from under a customer whose popup is merely open. Rather
 * than flipping the stored status back and forth around them,
 * wc_zelle_order_has_status() answers those two questions for that one order, so the row
 * itself never moves off checkout-draft.
 *
 * On confirm it leaves checkout-draft for pending just before payment, exactly as a block
 * checkout order does, so the gateway's process_payment() moves it pending to on-hold
 * and WooCommerce sends the same new order and on-hold emails it sends for any other
 * order. @see wc_zelle_checkout_create_order()
 *
 * @see WC_Checkout::create_order()
 */
class WC_Zelle_Draft_Order {

	public $id = 'zelle';

	/** Session key holding the draft this session may still remove. */
	const SESSION_KEY = 'wc_zelle_draft_order';

	/**
	 * The one order id wc_zelle_order_has_status() may answer the resume check for, and
	 * only while WC_Checkout::create_order() is deciding. 0 the rest of the time
	 * @var int
	 */
	protected $resuming = 0;

	public function register() {
		// Before WC_Checkout::create_order(), so a different payment method never
		// inherits or races the order held open for the Zelle popup
		add_action( 'woocommerce_checkout_process', array( $this, 'wc_zelle_checkout_process' ) );

		// The last hook before WC_Checkout::create_order(), where the cart totals are
		// final, so the order the popup showed is updated rather than replaced
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'wc_zelle_refresh_draft_cart_hash' ), 999 );

		// Lets create_order() resume a draft without it ever leaving checkout-draft
		add_filter( 'woocommerce_order_has_status', array( $this, 'wc_zelle_order_has_status' ), 10, 3 );

		// Fires immediately before the order's first save, which is what lets a new
		// draft be written as checkout-draft instead of written as pending and corrected
		add_action( 'woocommerce_checkout_create_order', array( $this, 'wc_zelle_checkout_create_order' ), 999, 2 );

		// Priority 0: answer with the draft before anything reacts to a real order
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'wc_zelle_checkout_order_processed' ), 0, 3 );

		// The block checkout's popup asks for its contents here. @see wc_zelle_blocks_popup()
		add_action( 'wc_ajax_wc_zelle_blocks_popup', array( $this, 'wc_zelle_blocks_popup' ) );
	}

	/**
	 * Tell WooCommerce that this session's own draft is still an order awaiting payment.
	 *
	 * Core has two places that ask that question of whatever order the session is holding
	 * open, and both ask it as "is this pending or failed", because before the block
	 * checkout existed an order held open for payment was always pending. A draft parked
	 * at checkout-draft answers no to both, and each one breaks differently:
	 *
	 * - WC_Checkout::create_order() resumes an order only when it has_status( pending,
	 *   failed ). Answering no there means a second order on every popup open.
	 * - wc_clear_cart_after_payment() reads the same session key on every front end page
	 *   load and treats anything that is not pending, failed or cancelled as an order
	 *   that "must have gone through", so it empties the cart and clears the session key.
	 *   Answering no there means the customer's cart is thrown away behind them, and the
	 *   next Place order has nothing left to resume - which is the second order again,
	 *   this time on every single click.
	 *
	 * Rather than moving the stored status back and forth around core, this answers those
	 * two questions for that one order. It is truthful: a draft behind an open popup is
	 * exactly an order awaiting payment, which is what both callers are really asking.
	 *
	 * Deliberately narrow. Only for an order that really is a checkout-draft, only for
	 * this session's own draft, and only for the two exact status sets those two callers
	 * pass - never for the longer lists payment_complete() and friends check against. The
	 * pending+failed pair is narrowed further still, to the window in which create_order()
	 * is deciding, because that same pair is what needs_payment() asks about moments later
	 *
	 * @see wc_clear_cart_after_payment()
	 * @see WC_Checkout::create_order()
	 *
	 * @param bool     $has_status Whether the order already has one of these statuses.
	 * @param WC_Order $order      Order being asked about.
	 * @param mixed    $status     Status or array of statuses being checked.
	 * @return bool
	 */
	public function wc_zelle_order_has_status( $has_status, $order, $status ) {
		if ( $has_status || ! is_array( $status ) ) { return $has_status; }
		if ( ! $order instanceof WC_Order || $this->id !== $order->get_payment_method() ) { return $has_status; }

		$asked = $status;
		sort( $asked );

		if ( array( 'failed', 'pending' ) === $asked ) {
			// create_order()'s resume check, inside the window it asks in and no wider
			$draft_id = $this->resuming;
		} elseif ( array( 'cancelled', 'failed', 'pending' ) === $asked ) {
			// wc_clear_cart_after_payment(), which runs on every front end page load
			$draft_id = $this->wc_zelle_draft_order_id();
		} else {
			return $has_status;
		}

		if ( ! $draft_id || absint( $order->get_id() ) !== $draft_id ) { return $has_status; }

		// get_status() rather than has_status(), which would re-enter this filter
		if ( 'checkout-draft' !== $order->get_status() ) { return $has_status; }

		return true;
	}

	/**
	 * Right before the order's first save: keeps a draft on checkout-draft (pending once confirmed) and closes the resume window
	 *
	 * @param WC_Order $order Order about to be saved.
	 * @param array    $data  Posted checkout data.
	 */
	public function wc_zelle_checkout_create_order( $order, $data ) {
		$this->resuming = 0;

		if ( ! $order instanceof WC_Order || $this->id !== $order->get_payment_method() ) { return; }

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC_Checkout::process_checkout() verified the checkout nonce before this ran
		if ( empty( $_POST['wc_zelle_draft'] ) ) {
			if ( 'checkout-draft' === $order->get_status() ) {
				$order->set_status( 'pending' );
			}
			return;
		}

		if ( ! $this->wc_zelle_use_popup() ) { return; }

		$order->set_status( $this->wc_zelle_draft_status() );
	}

	/**
	 * What the block checkout's popup shows, rendered fresh on request.
	 *
	 * The block checkout does not run WC_Checkout::process_checkout(), so none of the
	 * classic draft order machinery above applies to it, and nothing here touches it.
	 * The block checkout has its own: the Store API keeps a draft order (status
	 * checkout-draft) in the session and finalises that very same order on submit, so the
	 * number shown here is the number the customer will be charged against.
	 * @see wc_zelle_blocks_draft_order() for where that draft comes from.
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

		$order  = $this->wc_zelle_blocks_draft_order();
		$number = $order ? $order->get_order_number() : '';

		$html = '';
		$gateways = WC()->payment_gateways ? WC()->payment_gateways->payment_gateways() : array();

		// The Store API gives a new draft the customer's saved or first enabled payment
		// method, but this popup only opens with Zelle selected
		// Also when only the title is missing: create_order_from_cart() sets the method id alone
		if ( $order && ! empty( $gateways[ $this->id ] ) && ( $this->id !== $order->get_payment_method() || '' === $order->get_payment_method_title() ) ) {
			$order->set_payment_method( $gateways[ $this->id ] );
			$order->save();
		}

		// The template reads the cart for the amount, so without one there is nothing
		// worth rendering and the block checkout falls through to placing the order
		if ( WC()->cart && ! empty( $gateways[ $this->id ] ) ) {
			// Reuses the classic template verbatim, so the two checkouts cannot drift
			ob_start();
			$gateways[ $this->id ]->payment_fields();
			$html = ob_get_clean();
		}

		wp_send_json( array( 'html' => $html, 'number' => $number ) );
	}

	/**
	 * The block checkout's own Store API draft order, created now if it does not exist yet.
	 *
	 * Up to WooCommerce 10.7 the Store API created this draft as soon as the checkout
	 * block loaded, so it was already there by the time the popup opened. From 10.8 it
	 * defers that to the place-order POST, which only happens after the customer confirms
	 * in the popup - so without this the Memo would be empty.
	 *
	 * The same principle as the classic popup: WooCommerce creates the order, not this
	 * class. It goes through the Store API's own OrderController::create_order_from_cart()
	 * and is parked in the Store API's own store_api_draft_order session key, exactly as
	 * that POST would do it, so when the customer confirms, the POST finds it through
	 * DraftOrderTrait::get_draft_order() and finalises this very order instead of opening
	 * a second one. On WooCommerce 10.7 and older the draft already exists and is simply
	 * read.
	 *
	 * Block checkout only. The classic checkout never calls this and never reads that
	 * session key - its draft lives in self::SESSION_KEY and order_awaiting_payment
	 *
	 * @see Automattic\WooCommerce\StoreApi\Routes\V1\Checkout::create_or_update_draft_order()
	 * @return WC_Order|null Null when there is nothing to create it from, or it failed,
	 *                       in which case the popup is skipped and the order is placed
	 */
	protected function wc_zelle_blocks_draft_order() {
		if ( ! WC()->session || ! WC()->cart ) { return null; }

		$order_id = absint( WC()->session->get( 'store_api_draft_order', 0 ) );
		$order    = $order_id ? wc_get_order( $order_id ) : null;

		// The Store API's own reuse test, so this never strands an order it would pick up
		if ( $order instanceof WC_Order ) {
			if ( $order->has_status( 'checkout-draft' ) ) { return $order; }
			if ( $order->needs_payment() && $order->has_cart_hash( WC()->cart->get_cart_hash() ) ) { return $order; }
		}

		$controller = '\Automattic\WooCommerce\StoreApi\Utilities\OrderController';
		if ( ! class_exists( $controller ) || WC()->cart->is_empty() ) { return null; }

		try {
			$controller = new $controller();
			$order      = $controller->create_order_from_cart();
		} catch ( \Throwable $e ) {
			return null;
		}

		if ( ! $order instanceof WC_Order || ! $order->get_id() ) { return null; }

		WC()->session->set( 'store_api_draft_order', $order->get_id() );
		// wp_send_json() follows, so the session is written now rather than left to shutdown
		WC()->session->save_data();

		return $order;
	}

	/**
	 * Remove the popup's draft when the checkout is placed with another payment method, before that method creates its own order
	 */
	public function wc_zelle_checkout_process() {
		if ( ! $this->wc_zelle_use_popup() ) { return; }

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC_Checkout::process_checkout() verified the checkout nonce before this ran
		$method = isset( $_POST['payment_method'] ) ? wc_clean( wp_unslash( $_POST['payment_method'] ) ) : '';
		if ( $this->id === $method ) { return; }

		$this->wc_zelle_delete_draft_order();
	}

	/**
	 * checkout-draft, the block checkout's draft status, or 'pending' if this install has not registered it
	 * @return string
	 */
	protected function wc_zelle_draft_status() {
		return wc_is_order_status( 'wc-checkout-draft' ) ? 'checkout-draft' : 'pending';
	}

	/**
	 * The order this session is holding open behind the popup, if any. 0 with no session,
	 * which is every admin screen and cron run
	 * @return int
	 */
	protected function wc_zelle_draft_order_id() {
		return WC()->session ? absint( WC()->session->get( self::SESSION_KEY ) ) : 0;
	}

	/**
	 * Keep the order behind the popup in step with the cart, and open the window in
	 * which wc_zelle_order_has_status() will let create_order() resume it.
	 *
	 * create_order() resumes an order only while its cart hash still matches - a cart,
	 * coupon or shipping change between opening the popup and confirming would otherwise
	 * strand one order and start another. Handing it the current hash makes it resume
	 * this one, and it then rebuilds the line items, addresses and totals from the cart,
	 * so the order is updated in place and keeps the number the customer was given for
	 * their memo.
	 *
	 * The last hook before create_order() runs, so this is also where $resuming is set;
	 * wc_zelle_checkout_create_order() closes it again the moment create_order() has
	 * decided
	 *
	 * @see WC_Checkout::create_order()
	 */
	public function wc_zelle_refresh_draft_cart_hash() {
		if ( ! $this->wc_zelle_use_popup() || ! WC()->session || ! WC()->cart ) { return; }

		// Emptied by wc_zelle_checkout_process() when another payment method is placing
		// this order, which is what keeps that order from resuming the draft
		$order_id = $this->wc_zelle_draft_order_id();
		if ( ! $order_id ) { return; }

		$order = wc_get_order( $order_id );
		if ( ! $order || $this->id !== $order->get_payment_method() ) { return; }
		if ( ! $order->has_status( array( 'pending', 'failed', 'checkout-draft' ) ) ) { return; }

		$this->resuming = $order_id;

		$cart_hash = WC()->cart->get_cart_hash();
		if ( ! $order->has_cart_hash( $cart_hash ) ) {
			$order->set_cart_hash( $cart_hash );
			$order->save();
		}
	}

	/**
	 * Is the checkout popup the active design? Read from the stored settings with the same legacy and PRO rules as WC_Zelle_Gateway::wc_zelle_display()
	 *
	 * @see WC_Zelle_Gateway::wc_zelle_display()
	 * @return bool
	 */
	public function wc_zelle_use_popup() {
		if ( ! function_exists( 'zelle_fs' ) || ! zelle_fs()->is_plan__premium_only('pro') ) { return false; }

		$settings = get_option( "woocommerce_{$this->id}_settings", array() );
		$design   = isset( $settings['display_zelle'] ) ? (string) $settings['display_zelle'] : '';

		return in_array( $design, array( '1', 'no' ), true );
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
		$is_draft = ! empty( $_POST['wc_zelle_draft'] );

		if ( ! $is_draft || ! $this->wc_zelle_use_popup() ) {
			// This is the order the customer confirmed, it is no longer ours to remove
			$this->wc_zelle_forget_draft_order();
			return;
		}

		// WooCommerce normally resumes the popup's last order; if it opened a new one, the old one will never be paid
		$previous = $this->wc_zelle_draft_order_id();
		if ( $previous && $previous !== absint( $order_id ) ) {
			$this->wc_zelle_delete_draft_order();
		}

		// So the checkout that follows resumes this order instead of creating a second
		// one, and so we know which order this session may still remove
		if ( WC()->session ) {
			WC()->session->set( 'order_awaiting_payment', $order_id );
			WC()->session->set( self::SESSION_KEY, $order_id );
			WC()->session->save_data();
		}

		// Already checkout-draft. Stops before process_order_payment(), so no stock, email or cart changes
		wp_send_json( array(
			'result'   => 'zelle_draft',
			'order_id' => $order_id,
			'number'   => $order->get_order_number(),
		) );
	}

	/**
	 * Delete this session's untouched draft order; anything paid, further along or someone else's is left alone
	 * @return bool Whether an order was deleted
	 */
	protected function wc_zelle_delete_draft_order() {
		if ( ! WC()->session ) { return false; }

		$order_id = $this->wc_zelle_draft_order_id();
		if ( ! $order_id ) { return false; }

		$this->wc_zelle_forget_draft_order();

		if ( absint( WC()->session->get( 'order_awaiting_payment' ) ) === $order_id ) {
			WC()->session->set( 'order_awaiting_payment', null );
			WC()->session->save_data();
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || $this->id !== $order->get_payment_method() ) { return false; }
		if ( ! $order->has_status( array( 'pending', 'checkout-draft' ) ) || $order->is_paid() || $order->get_date_paid() || $order->get_transaction_id() ) { return false; }
		if ( 'checkout' !== $order->get_created_via() ) { return false; }
		if ( absint( $order->get_customer_id() ) !== absint( get_current_user_id() ) ) { return false; }

		// Creating the order reserved its stock (woocommerce_checkout_order_created),
		// and nothing releases a reservation whose order simply disappears
		if ( function_exists( 'wc_release_stock_for_order' ) ) {
			wc_release_stock_for_order( $order );
		}

		$order->delete( true );

		return true;
	}

	/** Stop treating the stored order as a draft this session may remove. */
	protected function wc_zelle_forget_draft_order() {
		if ( ! WC()->session || ! WC()->session->get( self::SESSION_KEY ) ) { return; }
		WC()->session->set( self::SESSION_KEY, null );
		WC()->session->save_data();
	}

}

add_action( 'init', function () {
	global $WC_Zelle_Draft_Order;
	$WC_Zelle_Draft_Order = new WC_Zelle_Draft_Order();
	$WC_Zelle_Draft_Order->register();
}, 5 );

endif;
