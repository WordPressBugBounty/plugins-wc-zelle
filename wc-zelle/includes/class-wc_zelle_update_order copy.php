<?php if ( ! defined( 'ABSPATH' ) ) { exit; }

// use Automattic\WooCommerce\Client;

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
            'name' => __( 'Zelle Receipts' ),
            'singular_name' => __( 'Zelle Receipt' )
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
    register_rest_route( 'wc-zelle/v1', '/update-zelle-order/key', array(
      'methods' => 'POST',
      'callback' => array( $this, 'wc_zelle_emrcpts_update_key'),
      'permission_callback' => '__return_true',
    ) );
    register_rest_route( 'wc-zelle/v1', '/update-zelle-order', array(
      'methods' => 'POST',
      // 'callback' => array( $this, 'OLD_wc_zelle_emrcpts_order_update'),
      'callback' => array( $this, 'wc_zelle_emrcpts_order_update'),
      'permission_callback' => '__return_true',
    ) );
  }

  // Update key
  function wc_zelle_emrcpts_update_key( $data ) {
    header("Content-type: application/json");
    $verified = false;
    $message = '';

    // // retrieve request host and key
    // // $_SERVER['HTTP_HOST'] === "emailreceipts.io"
    // $host = wp_kses_post($data->get_headers()['host'][0]);
    // print_r($data->get_headers());
    $key = wp_kses_post($data->get_body_params()['key']);
    // print_r($data->get_body_params());

    // Verify key
    $response = wp_remote_post( 'http://localhost:8000/keys/verify', array(
    // $response = wp_remote_post( 'https://emailreceipts.io/keys/verify', array(
      'method' => 'POST',
      'headers' => array(
        'Content-Type' => 'application/json; charset=utf-8',
      ),
      'body' => json_encode(array(
        'key' => $key,
        // 'domain' => $this->ZelleForwardingURL,
      )),
    ) );
    // print_r($response);

    if ( is_wp_error( $response ) ) {
      $error_message = $response->get_error_message();
      $message .= "Something went wrong: $error_message";
    } else {
      $body = json_decode(wp_remote_retrieve_body($response), true);
      $verified = !empty($body) ? $body['status'] : false;
      $message = !empty($body) ? $body['message'] : '';

      if ( $verified ) { // && $host === "emailreceipts.io"
        $message .= " Key updated successfully.";
        update_option( 'ZelleEmailReceiptsKey', $key );
      } else {
        $message .= " Key NOT updated.";
      }
    }

    $response = array(
      'verified' => $verified,
      'message' => $message,
    );
    echo json_encode($response);
  }

  // Update order
  function wc_zelle_emrcpts_order_update( $data ) {
    // header("Content-type: application/json");
    header("Content-type: text/plain");

    $body = $data->get_body_params();
    $signature = wp_kses_post($data->get_headers()['x_api_key'][0]);
    // print_r($data->get_headers());
    // print_r($body);

    $accountid = wp_kses_post($body['transactionaccountid']);
    $money = wp_kses_post($body['transactionamount']);
    $currency = wp_kses_post($body['transactioncurrency']);
    $amount = wp_kses_post($body['transactionamount']);
    $order_id = wp_kses_post($body['transactionorderid']);
    $note = wp_kses_post($body['transactionnote']);
    $receipt_post_id = null;

    $origin = get_bloginfo('url');
    echo "Response by: $origin\n";

    // echo "Account ID: $accountid\n";
    echo "Money: $money\n";
    echo "Currency: $currency\n";
    echo "Amount: $amount\n";
    echo "Note: $note\n";

    if ( $this->wc_zelle_emrcpts_verify_signature($signature) ) {
      $post_dump = print_r($body, true);
      $email_subject = null;
      $shop = wp_kses_post(get_bloginfo('url'));
      $amount = wp_kses_post(floatval($amount)); // $amount == $orderamount
      $order = $this->wc_zelle_find_zelle_order($money, $amount, $order_id, $accountid, $email_subject, $receipt_post_id);
      $accountid = !empty($order) && $order->meta_exists('zelle_sender') ? $order->get_meta('zelle_sender') : $accountid;
      echo "Account ID: $accountid\n";
      $order_id = !empty($order) ? $order->get_id() : $order_id;
      echo "Order ID: $order_id\n";
      require_once WCZELLE_PLUGIN_DIR . 'includes/admin/update-order.php';
    } else {
      echo "Invalid Request Signature was not verified.\n";
      // http_response_code(422);
      http_response_code(401);
    }
    echo "Status: " . http_response_code();
  }
  // function OLD_wc_zelle_emrcpts_order_update( $data ) {
  //   // header("Content-type: application/json");
  //   header("Content-type: text/plain");

  //   $body = $data->get_body_params();
  //   // print_r($body);

  //   $accountid = wp_kses_post($body['transactionaccountid']);
  //   $money = wp_kses_post($body['transactionamount']);
  //   $currency = wp_kses_post($body['transactioncurrency']);
  //   $amount = wp_kses_post($body['transactionamount']);
  //   $order_id = wp_kses_post($body['transactionorderid']);
  //   $note = wp_kses_post($body['transactionnote']);
  //   $receipt_post_id = null;

  //   $origin = get_bloginfo('url');
  //   echo "Response by: $origin\n";

  //   // echo "Account ID: $accountid\n";
  //   echo "Money: $money\n";
  //   echo "Currency: $currency\n";
  //   echo "Amount: $amount\n";
  //   echo "Note: $note\n";

  //   if ( $_SERVER['HTTP_HOST'] === "emailreceipts.io" ) {
  //     $post_dump = print_r($body, true);
  //     $email_subject = null;
  //     $shop = wp_kses_post(get_bloginfo('url'));
  //     $amount = wp_kses_post(floatval($amount)); // $amount == $orderamount
  //     $order = $this->wc_zelle_find_zelle_order($money, $amount, $order_id, $accountid, $email_subject, $receipt_post_id);
  //     $accountid = !empty($order) && $order->meta_exists('zelle_sender') ? $order->get_meta('zelle_sender') : $accountid;
  //     echo "Account ID: $accountid\n";
  //     require_once WCZELLE_PLUGIN_DIR . 'includes/admin/update-order.php';
  //     echo "Order ID: $order_id\n";
  //   } else {
  //     http_response_code(422);
  //   }
  //   echo "Status: " . http_response_code();
  // }

  // Verify signature hash
  function wc_zelle_emrcpts_verify_signature( $key, $isJSON = false, $is64 = true ) {
    $verified = false;

    $json = array();
    // $json['domain'] = $this->ZelleForwardingURL;
    if ( $is64 ) {
      $json['key64'] = $key64;
    } else {
      $json['key'] = $key;
    }

    $response = wp_remote_post( 'http://localhost:8000/keys/verify', array(
    // $response = wp_remote_post( 'https://emailreceipts.io/keys/verify', array(
      'method' => 'POST',
      'headers' => array(
        'Content-Type' => 'application/json; charset=utf-8',
      ),
      'body' => json_encode($json),
    ) );
    // print_r($response);

    if ( is_wp_error( $response ) ) {
      $error_message = $response->get_error_message();
      $message .= "Something went wrong: $error_message";
    } else {
      $body = json_decode(wp_remote_retrieve_body($response), true);
      $verified = !empty($body) ? $body['status'] : false;
      $message = !empty($body) ? $body['message'] : '';
    }

    if ( $isJSON ) {
      return $verified;
    } else {
      $response = array(
        'verified' => $verified,
        'message' => $message,
      );
      return json_encode($response);
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
          $zelle_sender = wp_kses_post($order->get_meta('zelle_sender'));
          $post_content .= "Recent order ". $orderid .": $orderamount vs provided: $amount from ". $zelle_sender ."\n";
          if ( $amount == $orderamount || $zelle_sender == $accountid ) {
            $post_title = "Receipt: $money from $accountid for $order_id (extracted from recent Zelle order)";
            $post_content .= "$money from $accountid for $order_id.";
            $order_id = !empty($order) ? $orderid : $order_id;
            $accountid = empty($accountid) ? $zelle_sender : $accountid;
            $found_order = true;
            $post_content .= "Recent Zelle order $order_id with accountid: $accountid matched amount $amount == $orderamount\n";
          } else {
            $order = array();
          }
          $orderind++;
        }

          // $order = $orders[0];
          // $orderamount = wp_kses_post(floatval($order->get_total()));
          // $post_content .= "Recent order: $orderamount vs provided: $amount\n";
          // if ($order->get_payment_method() === 'zelle' && $amount == $orderamount) {
          //   $post_title = "Receipt: $money from $accountid for $order_id (extracted from recent Zelle order)";
          //   $post_content .= "$money from $accountid for $order_id.";
          //   $order_id = !empty($order) ? $order->get_id() : $order_id;
          //   $accountid = empty($accountid) ? $order->get_meta('zelle_sender') : $accountid;
          //   $post_content .= "Recent Zelle order $order_id with accountid: $accountid matched amount $amount == $orderamount\n";
          // } else {
          //     $post_title = "Receipt: Invalid {$order->get_payment_method_title()} order";
          //     $post_content .= "Since the order information was invalid, we tried looking for the most recent order to see if it was a match.<br>Invalid recent order {$order->get_id()} did not match amount or payment method.<br>{$order->get_payment_method_title()} Order of amount $orderamount";
          //     $post_content .= "Invalid {$order->get_payment_method_title()} recent order {$order->get_id()} of amount $orderamount != $amount \n";
          // }
      } else {
          $post_title = "Receipt: No valid orders matched the amount: $amount";
          $post_content .= "Since the order information was invalid, we tried looking for the most recent order to see if it was a match.<br>" . $ordercountmsg;
      }
    }

    echo $post_content;

    if ($post_title && $post_content && post_type_exists( 'zelle-receipts' ) ) {
        $zelle_receipt = array(
            'post_title' => $post_title,
            'post_content' => "$post_content.<br><br>$email_subject",
            'post_type' => 'zelle-receipts',
            'post_status' => 'private',
        );
        $receipt_post_id = wp_insert_post( $zelle_receipt );
        if ($receipt_post_id) {
            echo "Zelle Receipt ID: $receipt_post_id created successfully\n";
            http_response_code(201);
        } else {
            echo "Zelle Receipt creation failed\n";
            http_response_code(500);
        }
    }

    return $order; // v2, return an array to add post_content, etc
  }

}

$WC_Zelle_Update_Order = new WC_Zelle_Update_Order();
$WC_Zelle_Update_Order->register();

endif;