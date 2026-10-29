<?php if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( !class_exists( 'WC_Zelle_Update_Order' ) && class_exists( 'WC_Zelle_Gateway' ) ):
class WC_Zelle_Update_Order extends WC_Zelle_Gateway {

  function register() {
    add_action( 'init', array( $this, 'wc_zelle_cpt' ) );
    add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', array( $this, 'wc_zelle_order_number_query' ), 10, 2 );
  }

  // Create CPT
  function wc_zelle_cpt() {
    if ( class_exists( 'Woocommerce' ) && !post_type_exists( "{$this->id}-receipts" ) ) {
      register_post_type( "{$this->id}-receipts",
        array(
          'labels' => array(
            /* translators: %s: payment method title */
            "name" => sprintf( __( '%s Receipts', WCZELLE_PLUGIN_TEXT_DOMAIN ), $this->method_title ),
            /* translators: %s: payment method title */
            "singular_name" => sprintf( __( '%s Receipt', WCZELLE_PLUGIN_TEXT_DOMAIN ), $this->method_title ),
          ),
          'public' => false,
          'show_ui' => true,
          'show_in_rest' => false,
          'has_archive' => false,
          'rewrite' => array('slug' => "{$this->id}-receipts"),
          // The orders capabilities, so only the roles that can manage orders (administrators,
          // shop managers and the like) can read receipts - never editors or authors
          'capability_type' => 'shop_order', // do not use manage_woocommerce
          'map_meta_cap' => true,
          'capabilities' => array( 'create_posts' => 'do_not_allow' ),
          'menu_icon' => 'dashicons-money-alt',
          'menu_position' => 56,
        )
      );
    }
    add_action( 'rest_api_init', array( $this, 'wc_zelle_update_order_route' ) );
  }

  // Create REST API route
  function wc_zelle_update_order_route() {
    register_rest_route( "wc-{$this->id}/v1", "/update-{$this->id}-order", array(
      'methods' => 'POST',
      "callback" => array( $this, "wc_{$this->id}_emrcpts_order_update"),
      "permission_callback" => "__return_true",
    ) );
  }

  // Update order
  function wc_zelle_emrcpts_order_update( $data ) {
    header("Content-type: application/json");
    // header("Content-type: text/plain");

    $message_array = array();

    $body = $data->get_body_params();
    $signature = is_array($data->get_headers()) && !empty($data->get_headers()['x_api_key']) ? wp_kses_post($data->get_headers()['x_api_key'][0]) : null;
    // print_r($data->get_headers());
    // print_r($body);

    $accountid = wp_kses_post($body['transactionaccountid'] ?? '');
    $money = wp_kses_post($body['transactionamount'] ?? '');
    $currency = wp_kses_post($body['transactioncurrency'] ?? '');
    $amount = wp_kses_post($body['transactionamount'] ?? '');
    $order_id = wp_kses_post($body['transactionorderid'] ?? '');
    $note = wp_kses_post($body['transactionnote'] ?? '');
    $receipt_post_id = null;
    $email_subject = !empty($body['emailsubject']) ? wp_kses_post($body['emailsubject']) : null;

    $origin = get_bloginfo('url');
    $message = "Response by: $origin\n";
    $message_array['url'] = $origin;
    $message .= "Money: $money\n";
    $message_array['money'] = $money;
    $message .= "Currency: $currency\n";
    $message_array['currency'] = $currency;
    $message .= "Amount: $amount\n";
    $message_array['amount'] = $amount;
    $message .= "Note: $note\n";
    $message_array['note'] = $note;

    $verify = $this->wc_zelle_emrcpts_verify_signature($signature, true);
    if ( is_array($verify) && $verify['status'] === true ) {
      $amount = wp_kses_post(floatval($amount)); // $amount == $orderamount
      $find_order = $this->wc_zelle_find_zelle_order($money, $amount, $order_id, $accountid, $email_subject, $receipt_post_id);
      $receipt_post_id = $find_order['receipt_post_id'];
      $order = $find_order['order'];
      $message .= "Account ID: $accountid\n";
      $message_array['accountid'] = $accountid;
      $order_id = !empty($order) ? $order->get_id() : $order_id;
      $message .= "Order ID: $order_id\n";
      $message_array['orderid'] = $order_id;
      $message .= $find_order['post_content'];
      $message_array['find_order'] = $find_order['post_content'];
      // $this->wcz_log( "zelle_emrcpts_order_update: " . $find_order['post_content'] );
      // Not require_once: it sets $response_code for every request, not just the first
      require WCZELLE_PLUGIN_DIR . 'includes/admin/update-order.php';
      $status_code = ! empty($response_code) ? (int) $response_code : 200;
    } else {
      $message .= is_array($verify) ? "Invalid Signature: " . $verify['message'] . "\n" : "Invalid Request Signature was not verified.\n";
      $message_array['signature'] = is_array($verify) ? $verify['message'] : "Invalid Request Signature was not verified.";
      // $this->wcz_log( "zelle_emrcpts_order_update: " . $message_array['signature'] );
      $status_code = 401;
    }
    $message .= "Status: " . $status_code;

    $this->wcz_log(wp_kses_post($message));

    $emrcpts_response = array(
      'status' => $status_code,
      'message' => wp_kses_post($message),
      'data' => $message_array,
    );

    $response = rest_ensure_response( $emrcpts_response );
    $response->set_status( $status_code );
    return $response;
  }

  // Verify signature hash
  function wc_zelle_emrcpts_verify_signature( $key, $isJSON = false ) {
    $verified = false;

    if ( empty($key) ) {
      $message = "No signature provided.";
      if ( $isJSON ) {
        $response = array(
          'status' => $verified,
          'message' => $message,
        );
        return $response;
      } else {
        return $verified;
      }
    }

    $response = wp_remote_post( 'https://emailreceipts.io/keys/verify', array(
      'method' => 'POST',
      'headers' => array(
        'Content-Type' => 'application/json; charset=utf-8',
      ),
      'body' => json_encode(array(
        'domain' => $this->ZelleForwardingURL,
        'key' => $key,
      )),
    ) );
    // print_r($response);

    if ( is_wp_error( $response ) ) {
      $error_message = $response->get_error_message();
      $message = "Something went wrong: $error_message";
    } else {
      $respose_body = wp_remote_retrieve_body($response);
      $body = json_decode($respose_body, true);
      if (json_last_error() === JSON_ERROR_NONE) {
        $verified = !empty($body) ? $body['status'] : false;
        $message = !empty($body) ? $body['message'] : '';
      } else {
        $message = 'Invalid response from emailreceipts.io';
      }
    }

    if ( $isJSON ) {
      $response = array(
        'status' => $verified,
        "message" => wp_kses_post($message),
      );
      return $response;
    } else {
      return $verified;
    }
  }

  function wc_zelle_order_number_query( $wp_query_args, $query_vars ) {
    if ( ! empty($query_vars['wc_zelle_order_number']) ) {
      $wp_query_args['meta_query'][] = array( 'key' => '_order_number', 'value' => (string) $query_vars['wc_zelle_order_number'] );
    }
    return $wp_query_args;
  }

  /**
   * The order from the order number is not the ID under sequential order number plugins
   * @param string|int $number Order number from the payment memo.
   * @param float|null $amount Amount paid, to pick between orders sharing the number.
   * @return WC_Order|null Null when no Zelle order, or more than one, fits
   */
  function wc_zelle_order_from_number( $number, $amount = null ) {
    $number = ltrim( trim( (string) $number ), '#' );
    if ( '' === $number ) { return null; }

    // Lets a custom order number setup answer for itself
    $order = apply_filters( 'wc_zelle_order_from_number', null, $number );
    if ( $order instanceof WC_Order ) { return $order; }

    // _order_number, used by sequential order number plugins. HPOS takes a meta_query; the classic store ignores one, so it gets a query var wc_zelle_order_number_query() maps
    $args = array( 'return' => 'ids', 'payment_method' => $this->id, 'limit' => 5 );
    if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
      $args['meta_query'] = array( array( 'key' => '_order_number', 'value' => $number ) );
    } else { $args['wc_zelle_order_number'] = $number; }
    $ids = wc_get_orders( $args );

    // Or the number is the ID, but only if the order agrees that it is its number
    if ( ctype_digit( $number ) ) { $ids[] = absint( $number ); }

    // Every Zelle order that really carries this number, once each
    $candidates = array();
    foreach ( array_unique( array_map( 'absint', $ids ) ) as $id ) {
      $order = wc_get_order( $id );
      if ( $order instanceof WC_Order && $this->id === $order->get_payment_method() && (string) $order->get_order_number() === $number ) {
        $candidates[ $id ] = $order;
      }
    }

    // Several orders share the number: only the one with the amount paid, and none if that is still ambiguous
    if ( count( $candidates ) > 1 ) {
      $this->wcz_log( "Order number $number is on " . count( $candidates ) . ' Zelle orders: ' . implode( ', ', array_keys( $candidates ) ), 'warning' );
      $candidates = null === $amount ? array() : array_filter( $candidates, function ( $order ) use ( $amount ) {
        return round( (float) $order->get_total(), 2 ) === round( (float) $amount, 2 );
      } );
    }

    return 1 === count( $candidates ) ? reset( $candidates ) : null;
  }

  function wc_zelle_find_zelle_order( $money, $amount, $order_id = null, $accountid = null, $email_subject = null, $receipt_post_id = null ) {
    $order = array();
    $post_title = null;
    $post_content = null;
    if (!empty($order_id)) {
      $order = $this->wc_zelle_order_from_number( $order_id, $amount );
      if ( ! $order instanceof WC_Order || $this->id !== $order->get_payment_method() ) { $order = array(); }
      $order_id = !empty($order) ? $order->get_id() : $order_id;
      $post_title = "Receipt: $money from $accountid for $order_id";
      $post_content .= "$money from $accountid for $order_id.";
    }

    // Settings > How emailreceipts.io payments are matched to orders
    $matching = (string) $this->emailreceipts_order_matching;

    if ( empty($order) && 'order_number' === $matching ) {
      $post_title = "Receipt: $money from $accountid, no order matched the order number";
      $post_content .= "No order matched the order number in the payment memo. Order matching is set to order number only, so no order was looked up by amount.\n";
    } elseif (empty($order)) {
      // 'orderby' => '<' . ( time() - 3600 ), 'fields' => 'ids' // ordered before the last hour
      // $orders = wc_get_orders( ['limit' => 5, 'payment_method' => $this->id, 'orderby' => time() - 3600, 'status' => array('wc-on-hold')] );
      // $orders = wc_get_orders( ['limit' => 5, 'payment_method' => $this->id, 'orderby' => 'date', 'order' => 'DESC', 'date_created' => '>' . ( time() - 3600 ), 'status' => array('wc-on-hold')] );
      // No usable order number: on-hold orders placed in the last hour, then popup orders still pending that were active in the last 30 minutes
      $orders = array(
        'on-hold' => wc_get_orders( ["limit" => 10, "payment_method" => $this->id, "orderby" => "date", "order" => "DESC", "status" => array("wc-on-hold"), "date_created" => ">" . ( time() - HOUR_IN_SECONDS )] ),
        'pending' => wc_get_orders( ["limit" => 10, "payment_method" => $this->id, "orderby" => "modified", "order" => "DESC", "status" => array("wc-pending"), "date_modified" => ">" . ( time() - 30 * MINUTE_IN_SECONDS )] ),
      );
      $ordercountmsg = count($orders['on-hold']) . " on-hold order(s) placed in the last hour and " . count($orders['pending']) . " pending order(s) active in the last 30 minutes match(es) your criteria: payment_method: {$this->id}\n";
      $post_content .= $ordercountmsg;
      if ( $orders['on-hold'] || $orders['pending'] ) {
        // single_amount: only when exactly one recent order has this amount; newest_amount: the newest of them
        $matches = array( 'on-hold' => array(), 'pending' => array() );
        foreach ( $orders as $status => $recent_orders ) {
          foreach ( $recent_orders as $recent ) {
            $orderamount = wp_kses_post(floatval($recent->get_total()));
            $post_content .= "Recent order " . $recent->get_id() . " (" . $recent->get_status() . "): $orderamount vs provided: $amount.\n";
            // Pending: checkout orders only (popup still open), never admin, API or renewal orders
            if ( $amount == $orderamount && ( 'on-hold' === $status || in_array( $recent->get_created_via(), array( 'checkout', 'store-api' ), true ) ) ) {
              $matches[ $status ][] = $recent;
            }
          }
        }
        // On-hold first: pending orders are only matched when no on-hold order has this amount
        $matches = $matches['on-hold'] ? $matches['on-hold'] : $matches['pending'];
        if ( 1 === count($matches) || ( 'newest_amount' === $matching && count($matches) > 1 ) ) {
          $order = $matches[0];
          $order_id = $order->get_id();
          $post_title = "Receipt: $money from $accountid for $order_id (extracted from recent Zelle order)";
          $post_content .= "$money from $accountid for $order_id.";
          $post_content .= 1 === count($matches)
            ? "Recent {$this->method_title} order $order_id with accountid: $accountid was the only one matching amount $amount\n"
            : "Recent {$this->method_title} order $order_id with accountid: $accountid is the newest of " . count($matches) . " orders matching amount $amount\n";
        } else {
          $order = array();
          $match_ids = implode( ', ', array_map( function ( $match ) { return $match->get_id(); }, $matches ) );
          $post_title = count($matches) > 1 ? "Receipt: $money matches " . count($matches) . " recent orders, none activated" : "Receipt: No recent order matched the amount: $amount";
          $post_content .= count($matches) > 1 ? "Recent orders $match_ids all match amount $amount, so none was activated. Match this payment manually.\n" : "No recent on-hold or pending order matched amount $amount.\n";
        }
      } else {
          $post_title = "Receipt: No valid orders matched the amount: $amount";
          $post_content .= "Since the order information was invalid, we tried looking for the most recent order to see if it was a match.<br>" . $ordercountmsg;
      }
    }

    if ($post_title && $post_content && post_type_exists( "{$this->id}-receipts" ) ) {
      $zelle_receipt = array(
          "post_title" => $post_title,
          "post_content" => "$post_content.<br><br>$email_subject",
          "post_type" => "{$this->id}-receipts",
          "post_status" => "private",
      );
      $receipt_post_id = wp_insert_post( $zelle_receipt );
      if ($receipt_post_id) {
          $post_content .= "{$this->method_title} Receipt ID: $receipt_post_id created successfully\n";
      } else {
          $post_content .= "{$this->method_title} Receipt creation failed\n";
      }
    }

    // echo $post_content;

    return array(
      'order' => $order,
      'post_content' => $post_content,
      'receipt_post_id' => $receipt_post_id,
    );
  }

}

// Instantiated on init: the inherited gateway constructor translates strings,
// which WordPress 6.7+ warns about when it happens before init.
add_action( 'init', function () {
  global $WC_Zelle_Update_Order;
  $WC_Zelle_Update_Order = new WC_Zelle_Update_Order();
  $WC_Zelle_Update_Order->register();
}, 5 );

endif;