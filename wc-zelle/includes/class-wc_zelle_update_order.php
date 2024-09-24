<?php if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( !class_exists( 'WC_Zelle_Update_Order' ) && class_exists( 'WC_Zelle_Gateway' ) ):
class WC_Zelle_Update_Order extends WC_Zelle_Gateway {

  function register() {
    add_action( 'init', array( $this, 'wc_zelle_cpt' ) );
    add_action( 'rest_api_init', array( $this, 'wc_zelle_update_order_route' ) );
  }

  // Create CPT
  function wc_zelle_cpt() {
    if ( class_exists( 'Woocommerce' ) && !post_type_exists( 'zelle-receipts' ) ) {
      register_post_type( 'zelle-receipts',
        array(
          'labels' => array(
              'name' => __( 'Zelle Receipts', WCZELLE_PLUGIN_TEXT_DOMAIN ),
              'singular_name' => __( 'Zelle Receipt', WCZELLE_PLUGIN_TEXT_DOMAIN )
          ),
          'public' => false,
          'show_ui' => true,
          'show_in_rest' => false,
          'has_archive' => false,
          'rewrite' => array('slug' => 'zelle-receipts'),
          'show_in_rest' => false,
          'menu_icon' => 'dashicons-money-alt',
          'menu_position' => 56,
        )
      );
    }
  }

  // Create REST API route
  function wc_zelle_update_order_route() {
    register_rest_route( 'wc-zelle/v1', '/update-zelle-order', array(
      'methods' => 'POST',
      'callback' => array( $this, 'wc_zelle_emrcpts_order_update'),
      'permission_callback' => '__return_true',
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

    $accountid = wp_kses_post($body['transactionaccountid']);
    $money = wp_kses_post($body['transactionamount']);
    $currency = wp_kses_post($body['transactioncurrency']);
    $amount = wp_kses_post($body['transactionamount']);
    $order_id = wp_kses_post($body['transactionorderid']);
    $note = wp_kses_post($body['transactionnote']);
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
      $accountid = !empty($order) && $order->meta_exists('zelle_sender') ? $order->get_meta('zelle_sender') : $accountid;
      $message .= "Account ID: $accountid\n";
      $message_array['accountid'] = $accountid;
      $order_id = !empty($order) ? $order->get_id() : $order_id;
      $message .= "Order ID: $order_id\n";
      $message_array['orderid'] = $order_id;
      $message .= $find_order['post_content'];
      $message_array['find_order'] = $find_order['post_content'];
      // $this->wcz_log( "zelle_emrcpts_order_update: " . $find_order['post_content'] );
      require_once WCZELLE_PLUGIN_DIR . 'includes/admin/update-order.php';
    } else {
      $message .= is_array($verify) ? "Invalid Signature: " . $verify['message'] . "\n" : "Invalid Request Signature was not verified.\n";
      $message_array['signature'] = is_array($verify) ? $verify['message'] : "Invalid Request Signature was not verified.";
      // $this->wcz_log( "zelle_emrcpts_order_update: " . $message_array['signature'] );
      http_response_code(401);
    }
    $message .= "Status: " . http_response_code();
    // $message_array['status'] = http_response_code();

    // echo $message;
    $this->wcz_log($message);
    // return $message;

    $emrcpts_response = array(
      'status' => http_response_code(),
      'message' => $message,
      'data' => $message_array,
    );
    // echo json_encode($emrcpts_response);
    return $emrcpts_response;
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
        'message' => $message,
      );
      return $response;
    } else {
      return $verified;
    }
  }

  function wc_zelle_find_zelle_order( $money, $amount, $order_id = null, $accountid = null, $email_subject = null, $receipt_post_id = null ) {
    $order = array();
    $post_title = null;
    $post_content = null;
    if (!empty($order_id)) {
      $order = wc_get_order( $order_id );
      $order_id = !empty($order) ? $order->get_id() : $order_id;
      $accountid = empty($accountid) && $order ? $order->get_meta('zelle_sender') : $accountid;
      $post_title = "Receipt: $money from $accountid for $order_id";
      $post_content .= "$money from $accountid for $order_id.";
    }

    if (empty($order)) {
      // 'orderby' => '<' . ( time() - 3600 ), // ordered before the last hour
      $orders = wc_get_orders( ['limit' => 5, 'payment_method' => 'zelle', 'orderby' => time() - 3600, 'status' => array('wc-on-hold')] );
      // print_r($orders);
      $ordercountmsg = count($orders) . " recent order(s) match(es) your criteria: payment_method: zelle, ordered in the last hour, status: on-hold\n";
      $post_content .= $ordercountmsg;
      if (count($orders) > 0) {
        $found_order = false;
        $orderind = 0;
        while ($orderind < count($orders) && $found_order == false) {
          $order = $orders[$orderind];
          $orderid = wp_kses_post($order->get_id());
          $orderamount = wp_kses_post(floatval($order->get_total()));
          $accountid_meta = wp_kses_post($order->get_meta('zelle_sender'));
          $post_content .= "Recent order $orderid: $orderamount vs provided: $amount from $accountid_meta.\n";
          if ( $amount == $orderamount || (!empty($accountid) && $accountid_meta == $accountid) ) {
            $post_title = "Receipt: $money from $accountid for $order_id (extracted from recent Zelle order)";
            $post_content .= "$money from $accountid for $order_id.";
            $order_id = !empty($order) ? $orderid : $order_id;
            $accountid = empty($accountid) ? $accountid_meta : $accountid;
            $found_order = true;
            $post_content .= "Recent Zelle order $order_id with accountid: $accountid matched amount $amount == $orderamount\n";
          } else {
            $order = array();
            // $order_id = null;
          }
          $orderind++;
        }
      } else {
          $post_title = "Receipt: No valid orders matched the amount: $amount";
          $post_content .= "Since the order information was invalid, we tried looking for the most recent order to see if it was a match.<br>" . $ordercountmsg;
      }
    }

    if ($post_title && $post_content && post_type_exists( 'zelle-receipts' ) ) {
        $zelle_receipt = array(
            'post_title' => $post_title,
            'post_content' => "$post_content.<br><br>$email_subject",
            'post_type' => 'zelle-receipts',
            'post_status' => 'private',
        );
        $receipt_post_id = wp_insert_post( $zelle_receipt );
        if ($receipt_post_id) {
            $post_content .= "Zelle Receipt ID: $receipt_post_id created successfully\n";
            http_response_code(201);
        } else {
            $post_content .= "Zelle Receipt creation failed\n";
            http_response_code(500);
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

$WC_Zelle_Update_Order = new WC_Zelle_Update_Order();
$WC_Zelle_Update_Order->register();

endif;