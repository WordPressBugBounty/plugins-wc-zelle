<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( class_exists( 'WC_Payment_Gateway' ) ) {
    class WC_Zelle_Gateway extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'zelle';
            // payment gateway plugin ID
            $this->icon = WCZELLE_PLUGIN_DIR_URL . 'assets/images/zelle_35.png';
            // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true;
            // in case you need a custom form
            $this->method_title = 'Zelle';
            $this->method_description = 'Easily receive Zelle payments';
            // will be displayed on the options page
            global $zelle_fs;
            $upgrade_url = zelle_fs()->get_upgrade_url();
            $this->method_description .= '<br><p>Unlock <a href="' . esc_url( admin_url( 'admin.php?page=wc_zelle_automated_status' ) ) . '">automatic order updates</a> when payment is received</p>';
            $this->init_settings();
            $this->enabled = $this->get_option( 'enabled' );
            $this->title = ( $this->get_option( 'checkout_title' ) ? $this->get_option( 'checkout_title' ) : $this->method_title );
            $this->ReceiverZELLENo = $this->get_option( 'ReceiverZELLENo' );
            $this->ReceiverZelleOwner = $this->get_option( 'ReceiverZelleOwner' );
            $this->ReceiverZELLEEmail = $this->get_option( 'ReceiverZELLEEmail' );
            $this->ZelleForwardingURL = wp_kses_post( get_bloginfo( 'url' ) . '/wp-json/wc-zelle/v1/update-zelle-order' );
            $this->update_option( 'ZelleForwardingURL', $this->ZelleForwardingURL );
            $this->ZelleStockManagement = $this->get_option( 'ZelleStockManagement' );
            $this->checkout_description = $this->get_option( 'checkout_description' );
            $this->zelle_notice = $this->get_option( 'zelle_notice' );
            $this->store_instructions = $this->get_option( 'store_instructions' );
            $this->display_zelle = $this->get_option( 'display_zelle' );
            $this->enableQRCode = $this->get_option( 'enableQRCode' );
            $this->ZelleQRCode = $this->get_option( 'ZelleQRCode' );
            $this->enableNote = $this->get_option( 'enableNote' );
            $this->order_note = $this->get_option( 'order_note' );
            $this->disableMenu = $this->get_option( 'disableMenu' ) ?? 'no';
            $this->processOrder = $this->get_option( 'processOrder' ) ?? 'no';
            $this->enable_debug = $this->get_option( 'enable_debug' );
            $this->toggleSupport = $this->get_option( 'toggleSupport' );
            $this->toggleTutorial = $this->get_option( 'toggleTutorial' );
            $this->toggleCredits = $this->get_option( 'toggleCredits' );
            // hold stock admin_url('admin.php?page=wc-settings&tab=products&section=inventory)
            $new = ' <sup style="color:#0c0">NEW</sup>';
            $newFeature = " <sup style='color:#c00;'>NEW FEATURE</sup>";
            $improved = " <sup style='color:#0c0;'>IMPROVED</sup>";
            $improvedFeature = " <sup style='color:#0c0;'>IMPROVED FEATURE</sup>";
            $comingSoon = " <sup style='color:#00c;'>COMING SOON</sup>";
            $emrcpts = ' <a href="' . esc_attr( wp_kses_post( admin_url( 'admin.php?page=wc_zelle_automated_status' ) ) ) . '" target="_blank">CONNECT</a>';
            $default_checkout_description = '<p>Please <strong>use your Order Number (available once you place order)</strong> as the payment reference.</p>';
            $default_zelle_notice = "<p>We are checking our systems to confirm that we received. If you haven't sent the money already, please make sure to do so now.</p>" . '<p>Once confirmed, we will proceed with the shipping and delivery options you chose.</p>' . '<p>Thank you for doing business with us! You will be updated regarding your order details soon.</p>';
            $default_store_instructions = "Please send the total amount requested to our store if you haven't yet";
            $default_order_note = esc_html__( 'Your order was received!', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '<br><br>' . sprintf( __( 'We are checking Zelle to confirm that we received the %s you sent so we can start processing your order.', WCZELLE_PLUGIN_TEXT_DOMAIN ), '<strong>**order_total**</strong>' ) . '<br><br>' . esc_html__( 'Thank you for doing business with us', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '!<br> ' . esc_html__( 'You will be updated regarding your order details soon', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '<br><br>' . esc_html__( 'Kindest Regards', WCZELLE_PLUGIN_TEXT_DOMAIN ) . ',<br>**shop_name**<br>**shop_email**<br>**shop_url**<br>';
            $payment_url = $this->wc_zelle_url( 1 );
            $qr_code_url = $this->wc_zelle_qrcode_url( 1 );
            $qr_code = $this->wc_zelle_qrcode( 1, 'advanced' );
            // upgrade display_zelle
            if ( $this->display_zelle === 'no' ) {
                $this->update_option( 'display_zelle', '1' );
            } else {
                if ( $this->display_zelle === 'yes' ) {
                    $this->update_option( 'display_zelle', '2' );
                }
            }
            $pro = ' <a style="text-decoration:none" href="' . esc_url( $upgrade_url ) . '" target="_blank"><sup style="color:red">PRO</sup></a>';
            $edit_with_pro = ' <a style="text-decoration:none" href="' . esc_url( $upgrade_url ) . '" target="_blank"><sup style="color:red">' . __( 'EDIT WITH', WCZELLE_PLUGIN_TEXT_DOMAIN ) . ' PRO</sup></a>';
            $this->form_fields = array(
                'enabled'              => array(
                    'title'   => 'Enable ZELLE',
                    'label'   => 'Check to Enable / Uncheck to Disable',
                    'type'    => 'checkbox',
                    'default' => 'no',
                ),
                'checkout_title'       => array(
                    'title'       => 'Checkout Title',
                    'type'        => 'text',
                    'description' => 'This is the title which the user sees on the checkout page.',
                    'default'     => 'Zelle',
                    'placeholder' => 'Zelle',
                ),
                'ReceiverZELLENo'      => array(
                    'title'       => 'Receiver Zelle No',
                    'type'        => 'text',
                    'description' => 'This is the phone number associated with your store Zelle/Bank account or your receiving Zelle/Bank account. Customers will send money to this number',
                    'placeholder' => "+1234567890",
                ),
                'ReceiverZelleOwner'   => array(
                    'title'       => "Receiver Zelle Owner's Name",
                    'type'        => 'text',
                    'description' => 'This is the name associated with your store Zelle/Bank account. Customers will send money to this Zelle/Bank account name',
                    'placeholder' => 'Jane D',
                ),
                'ReceiverZELLEEmail'   => array(
                    'title'       => "Receiver Zelle Owner's Email",
                    'type'        => 'text',
                    'description' => 'This is the email associated with your store Zelle/Bank account or your receiving Zelle/Bank account. Customers will send money to this email',
                    'default'     => "@gmail.com",
                    'placeholder' => "email@website.com",
                ),
                'ZelleForwardingURL'   => array(
                    'title'       => 'Connect your Email Receipts via emailreceipts.io' . $emrcpts,
                    'type'        => 'text',
                    'description' => 'This is the URL that will be imported to emailreceipts.io while setting up' . $emrcpts,
                    'default'     => $this->ZelleForwardingURL,
                    'placeholder' => $this->ZelleForwardingURL,
                    'css'         => 'width:80%; pointer-events: none;',
                    'class'       => 'disabled',
                ),
                'display_zelle'        => array(
                    'title'       => 'Checkout page design templates' . $improved . $pro,
                    'label'       => 'Choose how you want customers to see the Zelle info on checkout' . $edit_with_pro,
                    'type'        => 'select',
                    'description' => 'Choose how you want customers to see the Zelle info on checkout.
						<p><strong>PRO designs</strong> are enhanced with extra features such as <strong>copy to clipboard</strong>, <strong>QR code</strong>, <strong>Zelle button/link</strong>, etc to help autofill info when moving to Zelle.</p>
						<p><strong>Design 1:</strong> removes the Zelle info on checkout.</p>
						<p><strong>Design 2:</strong> shows the Zelle info on checkout in full width columns.</p>
						<p><strong>Design 3:</strong> shows the Zelle info on checkout in half width columns.</p>' . $edit_with_pro,
                    'default'     => '2',
                    'options'     => array(
                        '1' => '1: remove the Zelle info on checkout' . $edit_with_pro,
                        '2' => '2: show the Zelle info on checkout (full width columns)',
                        '3' => '3: show the Zelle info on checkout (half width columns)' . $edit_with_pro,
                    ),
                    'css'         => 'pointer-events: none;',
                    'class'       => 'disabled',
                ),
                'enableQRCode'         => array(
                    'title'       => 'Show the Zelle QR code and button on the checkout page and the thank you page' . $improved . $pro,
                    'label'       => 'Check to show the QR code and button / Uncheck to remove the QR code and button' . $edit_with_pro,
                    'type'        => 'select',
                    'description' => "Test the Zelle QR code and button that is displayed on the checkout page and the thank you page.<br><strong>Make sure your institution allows QR codes</strong><br>{$qr_code}",
                    'default'     => 'no',
                    'options'     => array(
                        'yes' => 'Yes, show the QR code and button',
                        'no'  => 'No, do not show the QR code and button',
                    ),
                    'css'         => 'pointer-events: none;',
                    'class'       => 'disabled',
                ),
                'ZelleQRCode'          => array(
                    'title'       => 'Your Zelle QR code URL on the checkout page and the thank you page' . $improved . $pro,
                    'label'       => 'This is for your Zelle QR code and button shown on the checkout page and the thank you page',
                    'type'        => 'text',
                    'description' => 'Input your https://enroll.zellepay.com/qr-codes?data=*** Zelle QR code URL.' . "<br>To get it, scan the Zelle QR code you get from your bank, institution or Zelle app with your camera app and extract the URL it redirects to in your browser. It should look like this:<br>https://enroll.zellepay.com/qr-codes?data=***" . '<br>Otherwise <a href="' . admin_url( '/media-new.php' ) . '" target="_blank">Upload your Zelle QR code</a> to your media uploads then <br>copy and input the uploaded ' . wp_upload_dir()['baseurl'] . '/*** URL' . $edit_with_pro,
                    'placeholder' => 'https://enroll.zellepay.com/qr-codes?data=***',
                    'css'         => 'pointer-events: none;',
                    'class'       => 'disabled',
                ),
                'ZelleStockManagement' => array(
                    'title'       => 'Reduce Stock ONLY after payment receipt' . $pro,
                    'label'       => 'Check to to reduce stock when order goes to processing / Uncheck to reduce stock when order goes on-hold' . $edit_with_pro,
                    'type'        => 'checkbox',
                    'description' => 'If you want to reduce stock once payment is received, check this box',
                    'default'     => 'no',
                    'css'         => 'pointer-events: none;',
                    'class'       => 'disabled',
                ),
                'checkout_description' => array(
                    'title'       => 'Checkout Page Notice' . $pro,
                    'type'        => 'textarea',
                    'description' => "This is the text a customer sees in the payment gateway box on the checkout page. {$edit_with_pro}<br>Default:<br>{$default_checkout_description}",
                    'default'     => $default_checkout_description,
                    'css'         => 'width:80%; pointer-events: none;',
                    'class'       => 'disabled',
                ),
                'zelle_notice'         => array(
                    'title'       => 'Thank You Notice' . $pro,
                    'type'        => 'textarea',
                    'description' => "This is the text a customer sees on the thank you/order confirmation page after placing an order. {$edit_with_pro}<br>Default:<br>{$default_zelle_notice}",
                    'default'     => $default_zelle_notice,
                    'css'         => 'width:80%; pointer-events: none;',
                    'class'       => 'disabled',
                ),
                'store_instructions'   => array(
                    'title'       => 'Store Instructions' . $pro,
                    'type'        => 'textarea',
                    'description' => "Store Instructions that will be added to the thank you page and emails. {$edit_with_pro}<br>Default:<br>{$default_store_instructions}",
                    'default'     => $default_store_instructions,
                    'css'         => 'width:80%; pointer-events: none;',
                    'class'       => 'disabled',
                ),
                'enableNote'           => array(
                    'title'       => 'Enable/Disable adding a note to orders' . $pro,
                    'label'       => 'Check to enable sending note / Uncheck to disable sending note' . $edit_with_pro,
                    'type'        => 'checkbox',
                    'description' => 'A note will be added to your order and an email about that note will be sent to your email',
                    'default'     => 'yes',
                    'css'         => 'pointer-events: none;',
                    'class'       => 'disabled',
                ),
                'order_note'           => array(
                    'title'       => 'Admin Order Note',
                    'type'        => 'textarea',
                    'description' => "This is a note added to the order email. You may use available shortcodes as needed like in this default order note below: {$edit_with_pro}<br>{$default_order_note}",
                    'default'     => $default_order_note,
                    'css'         => 'pointer-events: none;',
                    'class'       => 'disabled',
                ),
                'processOrder'         => array(
                    'title'       => 'Enable/Disable processing orders automatically' . $pro,
                    'label'       => 'Check to enable processing orders / Uncheck to disable processing orders' . $edit_with_pro,
                    'type'        => 'checkbox',
                    'description' => '<p>When checked, orders will automatically be processed after checkout (whether payment was sent or not).</p>
							<p>When unchecked, orders will be put on-hold until you manually process them or use emailreceipts.io to auto-process them</p>',
                    'default'     => 'no',
                    'css'         => 'pointer-events: none;',
                    'class'       => 'disabled',
                ),
                'enable_debug'         => array(
                    'title'       => 'Enable Debug' . $pro,
                    'label'       => 'Check to Enable / Uncheck to Disable' . $edit_with_pro,
                    'type'        => 'checkbox',
                    'description' => 'This will enable debug mode to help you troubleshoot issues. <a href="' . admin_url( 'admin.php?page=wc-status&tab=logs' ) . '" target="_blank">Access Logs here</a>',
                    'default'     => 'no',
                    'css'         => 'pointer-events: none;',
                    'class'       => 'disabled',
                ),
                'toggleSupport'        => array(
                    'title'       => 'Enable Support message',
                    'label'       => 'Check to Enable / Uncheck to Disable',
                    'type'        => 'checkbox',
                    'description' => 'Help your customers checkout with ease by letting them know how to contact you',
                    'default'     => 'yes',
                ),
                'toggleTutorial'       => array(
                    'title'       => 'Enable Tutorial to display 1min video link',
                    'label'       => 'Check to Enable / Uncheck to Disable',
                    'type'        => 'checkbox',
                    'description' => 'Help your customers checkout with ease by showing this tutorial link',
                    'default'     => 'no',
                ),
                'toggleCredits'        => array(
                    'title'       => 'Enable Credits to display Powered by The African Boss',
                    'label'       => 'Check to Enable / Uncheck to Disable',
                    'type'        => 'checkbox',
                    'description' => 'Help us spread the word about this plugin by sharing that we made this plugin',
                    'default'     => 'no',
                ),
            );
            // Gateways can support subscriptions, refunds, saved payment methods
            $this->supports = array('products');
            // This action hook saves the settings
            add_action( "woocommerce_update_options_payment_gateways_{$this->id}", array($this, 'process_admin_options') );
            // We need custom JavaScript to obtain a token
            add_action( 'wp_enqueue_scripts', array($this, 'wc_zelle_payment_scripts') );
            // Thank you page
            add_action( "woocommerce_thankyou_{$this->id}", array($this, 'wc_zelle_thankyou_page') );
            add_action(
                'woocommerce_checkout_order_processed',
                array($this, 'wc_zelle_processed'),
                10,
                3
            );
            // Customer Emails
            add_action(
                'woocommerce_email_order_details',
                array($this, 'wc_zelle_email_instructions'),
                10,
                3
            );
            if ( zelle_fs()->is_plan__premium_only( 'pro' ) && is_plugin_active( 'zoho-mail/zohoMail.php' ) ) {
                add_action( 'woocommerce_order_status_pending_to_on-hold_notification', array($this, 'wczelle_zoho_notification') );
            }
            // WooCommerce Blocks support
            add_action( 'woocommerce_blocks_loaded', array($this, 'zelle_woocommerce_blocks_support') );
        }

        public function zelle_woocommerce_blocks_support() {
            if ( class_exists( 'WC_Payment_Gateway' ) && class_exists( 'Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType' ) ) {
                require_once WCZELLE_PLUGIN_DIR . 'includes/class-wc_zelle_gateway_blocks.php';
                add_action( 'woocommerce_blocks_payment_method_type_registration', function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                    $payment_method_registry->register( new WC_Zelle_Gateway_Blocks_Support() );
                } );
            }
        }

        public function wc_zelle_url( $amount = 0, $note = '' ) {
            $payment_url = "";
            if ( !empty( $this->ZelleQRCode ) ) {
                $payment_url = $this->ZelleQRCode;
            }
            return esc_attr( trim( $payment_url ) );
            // if ( !empty($this->ZelleQRCode) && strpos($this->ZelleQRCode, wp_upload_dir()['baseurl']) !== false ) {
            // 	// try {
            // 	// 	$api_response = wp_remote_get( 'https://api.qrserver.com/v1/read-qr-code/?fileurl=' . urlencode(trim($this->ZelleQRCode)) );
            // 	// 	$response = ! is_wp_error( $api_response ) ? wp_remote_retrieve_body( $api_response ) : null;
            // 	// 	$result = $response ? json_decode( $response, true ) : null;
            // 	// 	if ( !empty($result) && json_last_error() === JSON_ERROR_NONE ) {
            // 	// 		// $data = $result['data'];
            // 	// 		// if ( !empty($data) && strpos($data, 'https://enroll.zellepay.com/qr-codes') !== false ) {
            // 	// 		// 	$payment_url = esc_attr(trim($data));
            // 	// 		// }
            // 	// 	}
            // 	// } catch (\Throwable $th) {
            // 	// 	// // Executed only in PHP 7, will not match in PHP 5.x
            // 	// 	// print_r($th);
            // 	// 	$this->wcz_log( "zelle_url: " . $th->getMessage(), 'error' );
            // 	// } catch (\Exception $e) {
            // 	// 	// // Executed only in PHP 5.x, will not be reached in PHP 7
            // 	// 	// print_r($e);
            // 	// 	$this->wcz_log( "zelle_url: " . $e->getMessage(), 'error' );
            // 	// }
            // 	$payment_url = !empty($payment_url) ? $payment_url : $this->ZelleQRCode;
            // } else if ( !empty($this->ZelleQRCode) ) {
            // 	$payment_url = $this->ZelleQRCode;
            // }
            // return esc_attr(trim($payment_url));
            // if ( empty($this->ReceiverZelleOwner) ) return esc_attr(trim($payment_url));
            // if (!empty(trim($this->ReceiverZELLENo))) {
            // 	// $payment_url = esc_attr( 'https://enroll.zellepay.com/qr-codes'. esc_attr( trim($this->ReceiverZELLENo) ) );
            // 	$data = array("token" => esc_attr( substr(filter_var(str_replace("-", "", trim($this->ReceiverZELLENo) ), FILTER_SANITIZE_NUMBER_INT), -10) ), "action" => "payment", "name" => esc_attr( strtoupper( explode(" ",trim($this->ReceiverZelleOwner))[0] ) ) );
            // }
            // if (!empty(trim($this->ReceiverZELLEEmail))) {
            // 	// $payment_url = esc_attr( 'https://enroll.zellepay.com/qr-codes'. esc_attr( trim($this->ReceiverZELLEEmail) ) );
            // 	$data = array("token" => esc_attr( trim($this->ReceiverZELLEEmail) ), "action" => "payment", "name" => esc_attr( strtoupper( explode(" ",trim($this->ReceiverZelleOwner))[0] ) ) );
            // }
            // // // unset($data['name']);
            // $data['amount'] = floatval($amount);
            // $payment_url = esc_attr( 'https://enroll.zellepay.com/qr-codes'. base64_encode(json_encode($data)) );
            // // wp_die(json_encode($data));
            // return esc_attr(trim($payment_url));
        }

        public function wc_zelle_qrcode_url( $amount = 0, $note = '' ) {
            $qr_code_url = "";
            $payment_url = $this->wc_zelle_url( $amount );
            if ( strpos( $payment_url, 'https://enroll.zellepay.com/qr-codes' ) === false ) {
                $qr_code_url = $payment_url;
            } else {
                if ( !empty( $payment_url ) ) {
                    $qr_code_url = "https://emailreceipts.io/qr?d=150&t=" . urlencode( $payment_url );
                }
            }
            return esc_attr( $qr_code_url );
        }

        public function wc_zelle_qrcode( $amount = 0, $type = "simple", $note = '' ) {
            $qr_code = "";
            $payment_url = $this->wc_zelle_url( $amount );
            $qr_code_url = $this->wc_zelle_qrcode_url( $amount );
            if ( strpos( $payment_url, 'https://enroll.zellepay.com/qr-codes' ) === false ) {
                $qr_code = '<img style="float: none!important; min-height:250px; min-width:250px; max-height:auto!important; max-width:250px!important;" alt="' . esc_attr( $this->method_title ) . ' link" src="' . esc_attr( $qr_code_url ) . '">';
                $qr_code = '<a class="qr" href="' . esc_url( $payment_url ) . '" target="_blank">' . $qr_code . '</a>';
                return wp_kses_post( $qr_code );
            }
            if ( empty( trim( $qr_code_url ) ) ) {
                return $qr_code;
            }
            if ( $type === "advanced" ) {
                $qr_code .= '<a href="' . esc_url( $payment_url ) . '" target="_blank">';
                // $qr_code .= '<p>' . esc_html__( 'If using the Zelle app, scan/click below', WCZELLE_PLUGIN_TEXT_DOMAIN ) . ':</p>';
                $default_qrcode = '<img class="logo-qr mb-1" width="150px" height="150px" src="' . esc_attr( $qr_code_url ) . '" />';
                $qr_code .= '<div id="wc_zelle_qrcode">' . $default_qrcode . '</div>';
                $qr_code .= '</a><p class="text-center mb-1">' . esc_html__( 'Scan with your Camera app', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '<br />' . esc_html__( 'or click the button below', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '</p>
				<a class="btn btn-dark" role="button" href="' . esc_url( $payment_url ) . '" target="_blank" style="padding: 10px 35px;border-radius: 30px;">Pay with Zelle  <img width="30px" height="30px" alt="Zelle logo" src="' . esc_attr( WCZELLE_PLUGIN_DIR_URL . 'assets/images/zelle_35.png' ) . '" /></a>';
            } else {
                $qr_code = '<a class="logo-qr" href="' . esc_url( $payment_url ) . '" target="_blank"><img style="float: none!important; max-height:150px!important; max-width:100px!important;" alt="' . esc_attr( $this->method_title ) . ' link" src="' . esc_attr( $qr_code_url ) . '"></a>';
                // // or with local QR code JS, no logo, no button
                // $qr_code .= '<a href="'. esc_url( $payment_url ) . '" target="_blank">';
                // // $qr_code .= '<p>' . esc_html__( 'If using the Zelle app, scan/click below', WCZELLE_PLUGIN_TEXT_DOMAIN ) . ':</p>';
                // $default_qrcode = '<img class="logo-qr mb-1" width="150px" height="150px" src="'. esc_attr( $qr_code_url ) . '" />';
                // $qr_code .= '<div id="wc_zelle_qrcode">' . $default_qrcode . '</div>';
                // $qr_code .= '</a>';
            }
            return wp_kses_post( $qr_code );
        }

        public function wc_zelle_emrcpts_connect_url() {
            $emrcpts_connect_url = '';
            if ( !is_admin() ) {
                return $emrcpts_connect_url;
            }
            global $current_user;
            $first_name = '';
            $last_name = '';
            $phone = '';
            if ( $current_user && is_php_version_compatible( '7.0' ) ) {
                $first_name = $current_user->user_firstname ?? get_user_meta( get_current_user_id(), 'first_name', true ) ?? '';
                $last_name = $current_user->user_lastname ?? get_user_meta( get_current_user_id(), 'last_name', true ) ?? '';
                $phone = get_user_meta( get_current_user_id(), 'billing_phone', true ) ?? '';
            } else {
                if ( $current_user ) {
                    $first_name = ( $current_user->user_firstname ? $current_user->user_firstname : get_user_meta( get_current_user_id(), 'first_name', true ) );
                    $last_name = ( $current_user->user_lastname ? $current_user->user_lastname : get_user_meta( get_current_user_id(), 'last_name', true ) );
                    $phone = get_user_meta( get_current_user_id(), 'billing_phone', true );
                }
            }
            $sn = urlencode( get_bloginfo( "name" ) );
            $su = urlencode( get_site_url() );
            $fn = urlencode( $first_name );
            $ln = urlencode( $last_name );
            $ph = urlencode( $phone );
            $em = urlencode( get_bloginfo( "admin_email" ) );
            $th = urlencode( get_site_icon_url() );
            $_wpnonce = urlencode( wp_create_nonce( 'connect_store_to_emailreceipts' ) );
            $ref = WCZELLE_PLUGIN_SLUG;
            $zn = urlencode( $this->ReceiverZelleOwner );
            $zp = urlencode( $this->ReceiverZELLENo );
            $ze = urlencode( $this->ReceiverZELLEEmail );
            // $square = ' <a href="https://emailreceipts.io/store/connect?sn=' . urlencode(get_bloginfo("name")) . '&su=' . urlencode(get_site_url()) . '&fn=' . urlencode($first_name) . '&ln=' . urlencode($last_name) . '&em=' . urlencode(get_bloginfo("admin_email")) . '&ph=' . urlencode($phone) . '&th=' . urlencode(get_site_icon_url()) . '&_wpnonce=' . urlencode(wp_create_nonce( 'connect_store_to_emailreceipts' )) . '&ref=' . WCZELLE_PLUGIN_SLUG . '" target="_blank">Get it here</a>';
            $emrcpts_connect_url = "https://emailreceipts.io/store/connect?sn={$sn}&su={$su}&fn={$fn}&ln={$ln}&em={$em}&ph={$ph}&th={$th}&_wpnonce={$_wpnonce}&ref={$ref}";
            return $emrcpts_connect_url;
        }

        /**
         * Logging method.
         *
         * @param string $message Log message.
         * @param string $level Optional. Default 'info'
         * Possible values: emergency|alert|critical|error|warning|notice|info|debug.
         */
        protected function wcz_log( $message, $level = 'info' ) {
            // logs at admin.php?page=wc-status&tab=logs
            if ( !empty( $message ) && $this->enable_debug == 'yes' && zelle_fs()->is_plan__premium_only( 'pro' ) ) {
                $logger = wc_get_logger();
                // $logger->debug( 'Detailed debug information', $context );
                // $logger->info( 'Interesting events', $context );
                // $logger->notice( 'Normal but significant events', $context );
                // $logger->warning( 'Exceptional occurrences that are not errors', $context );
                // $logger->error( 'Runtime errors that do not require immediate', $context );
                // $logger->critical( 'Critical conditions', $context );
                // $logger->alert( 'Action must be taken immediately', $context );
                // $logger->emergency( 'System is unusable', $context );
                // // The `log` method accepts any valid level as its first argument.
                // // $context may hold arbitrary data.
                // // If you provide a "source", it will be used to group your logs.
                // $context = array( 'source' => 'my-extension-name' );
                // $logger->log( 'debug', '<- Provide a level', $context );
                $logger->log( $level, wp_strip_all_tags( wp_kses_post( $message ) ), array(
                    'source' => $this->id,
                ) );
            }
        }

        /**
         * Logging method.
         * @param string $message
         */
        // public static function log($message) {
        //     if (TRUE || self::$log_enabled) {
        //         if (empty(self::$log)) {
        //             self::$log = new WC_Logger();
        //         }
        //         self::$log->add($this->id, $message);
        //     }
        // }
        // // Check if we are forcing SSL on checkout pages
        // public function do_ssl_check() {
        //     if (( function_exists('wc_site_is_https') && !wc_site_is_https() ) && ( 'no' === get_option('woocommerce_force_ssl_checkout') && !class_exists('WordPressHTTPS') )) {
        //         echo '<div class="error"><p>' . sprintf(__('<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href="%s">forcing the checkout pages to be secured.</a>', WCZELLE_PLUGIN_TEXT_DOMAIN), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . '</p></div>';
        //     }
        // }
        // // Check if this gateway is enabled
        // public function is_available() {
        //     if (empty($this->ReceiverZELLEEmail) && empty($this->ReceiverZELLENo)) return false;
        //     return true;
        // }
        // Checkout page
        public function payment_fields() {
            require_once WCZELLE_PLUGIN_DIR . 'includes/pages/checkout.php';
        }

        // Payment Custom JS and CSS
        public function wc_zelle_payment_scripts() {
            if ( 'no' === $this->enabled || empty( $this->ReceiverZELLEEmail ) && empty( $this->ReceiverZELLENo ) ) {
                return;
            }
            require_once WCZELLE_PLUGIN_DIR . 'includes/functions/payment_scripts.php';
        }

        // Thank you page
        public function wc_zelle_thankyou_page( $order_id ) {
            if ( !$order_id ) {
                return;
            }
            $order = wc_get_order( $order_id );
            // deprecated since WC 3.0.0
            // $order = new WC_Order( $order_id ); // For WC 2.5.0+
            if ( $order && $this->id === $order->get_payment_method() ) {
                require_once WCZELLE_PLUGIN_DIR . 'includes/pages/thankyou.php';
            }
        }

        public function wc_zelle_processed( $order_id, $posted_data, $order ) {
            if ( !$order_id || !$order ) {
                return;
            }
            if ( $this->id === $order->get_payment_method() ) {
                require_once WCZELLE_PLUGIN_DIR . 'includes/functions/order_processed.php';
            }
        }

        // Add content to the WC emails
        public function wc_zelle_email_instructions( $order, $sent_to_admin, $plain_text = false ) {
            if ( !$sent_to_admin && 'on-hold' === $order->get_status() && $this->id === $order->get_payment_method() ) {
                $order_id = ( method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id );
                require_once WCZELLE_PLUGIN_DIR . 'includes/notifications/email.php';
            }
        }

        // Zoho support
        public function wczelle_zoho_notification( $order_id ) {
            if ( !$order_id ) {
                return;
            }
            $order = wc_get_order( $order_id );
            if ( !$order ) {
                return;
            }
        }

        // validate zelle_email
        public function validate_fields() {
            if ( isset( $_POST['zelle_email'] ) ) {
                $zelle_email = sanitize_text_field( trim( $_POST['zelle_email'] ) );
                if ( !$zelle_email || filter_var( $zelle_email, FILTER_VALIDATE_EMAIL ) == false ) {
                    wc_add_notice( esc_html( __( 'Invalid/Missing Zelle email', WCZELLE_PLUGIN_TEXT_DOMAIN ) ), 'error' );
                    $this->wcz_log( esc_html( __( 'Checkout: Invalid/Missing Zelle email', WCZELLE_PLUGIN_TEXT_DOMAIN ) ), 'error' );
                }
            }
            if ( isset( $_POST['zelle_no'] ) ) {
                $zelle_no = sanitize_text_field( trim( $_POST['zelle_no'] ) );
                if ( !$zelle_no || filter_var( $zelle_no, FILTER_SANITIZE_NUMBER_INT ) == false ) {
                    wc_add_notice( esc_html( __( 'Invalid/Missing Zelle phone number', WCZELLE_PLUGIN_TEXT_DOMAIN ) ), 'error' );
                    $this->wcz_log( esc_html( __( 'Checkout: Invalid/Missing Zelle phone number', WCZELLE_PLUGIN_TEXT_DOMAIN ) ), 'error' );
                }
            }
            if ( isset( $_POST['zelle_sender'] ) ) {
                $accountid_meta = sanitize_text_field( trim( $_POST['zelle_sender'] ) );
                // Validate Zelle email/phone
                if ( empty( $accountid_meta ) ) {
                    wc_add_notice( esc_html( __( 'Missing Zelle sender information', WCZELLE_PLUGIN_TEXT_DOMAIN ) ), 'error' );
                    $this->wcz_log( esc_html( __( 'Checkout: Missing Zelle sender information', WCZELLE_PLUGIN_TEXT_DOMAIN ) ), 'error' );
                } else {
                    if ( filter_var( $accountid_meta, FILTER_VALIDATE_EMAIL ) !== false ) {
                        //  && $accountid_meta == filter_var($accountid_meta, FILTER_VALIDATE_EMAIL)
                        // echo("$accountid_meta is a valid email address");
                        // wc_add_notice( esc_html( __("$accountid_meta is a valid email address", WCZELLE_PLUGIN_TEXT_DOMAIN ) ), 'success' );
                    } else {
                        if ( filter_var( $accountid_meta, FILTER_SANITIZE_NUMBER_INT ) !== false ) {
                            //  && $accountid_meta == filter_var($accountid_meta, FILTER_SANITIZE_NUMBER_INT)
                            // echo("$accountid_meta is a valid phone number");
                            // wc_add_notice( esc_html( __("$accountid_meta is a valid phone number", WCZELLE_PLUGIN_TEXT_DOMAIN ) ), 'success' );
                        } else {
                            // echo("$accountid_meta is not a valid phone number nor email");
                            wc_add_notice( esc_html( __( "Invalid Zelle sender information: {$accountid_meta} is not a valid phone number nor email", WCZELLE_PLUGIN_TEXT_DOMAIN ) ), 'error' );
                            $this->wcz_log( esc_html( __( "Checkout: Invalid Zelle sender information: {$accountid_meta} is not a valid phone number nor email", WCZELLE_PLUGIN_TEXT_DOMAIN ) ), 'error' );
                        }
                    }
                }
            }
            if ( isset( $_POST['do_not_checkout'] ) ) {
                wc_add_notice( esc_html( __( 'Please try another payment method', WCZELLE_PLUGIN_TEXT_DOMAIN ) ), 'error' );
                $this->wcz_log( esc_html( __( 'Checkout: Please try another payment method', WCZELLE_PLUGIN_TEXT_DOMAIN ) ), 'error' );
            }
        }

        // Process Order
        public function process_payment( $order_id ) {
            try {
                if ( !$order_id ) {
                    wc_add_notice( '<p>Something went terribly wrong.</p><p>Order information is missing</p>', 'error' );
                    $this->wcz_log( 'Checkout: Something went terribly wrong. Order information is missing</p>', 'error' );
                    return;
                }
                $order = wc_get_order( $order_id );
                if ( !$order ) {
                    wc_add_notice( '<p>Something went terribly wrong.</p><p>Order information is missing</p>', 'error' );
                    $this->wcz_log( 'Checkout: Something went terribly wrong. Order information is missing', 'error' );
                    return;
                }
                if ( !is_wp_error( $order ) && $this->id === $order->get_payment_method() ) {
                    if ( isset( $_POST['zelle_sender'] ) ) {
                        $accountid_meta = sanitize_text_field( trim( $_POST['zelle_sender'] ) );
                        if ( $accountid_meta ) {
                            // update_post_meta($order_id, 'zelle_sender', $accountid_meta);
                            if ( filter_var( $accountid_meta, FILTER_VALIDATE_EMAIL ) !== false ) {
                                // echo("$accountid_meta is a valid email address");
                                $order->update_meta_data( 'zelle_sender', filter_var( $accountid_meta, FILTER_VALIDATE_EMAIL ) );
                                $order->save();
                            } else {
                                if ( filter_var( $accountid_meta, FILTER_SANITIZE_NUMBER_INT ) !== false ) {
                                    // echo("$accountid_meta is a valid phone number");
                                    $order->update_meta_data( 'zelle_sender', filter_var( $accountid_meta, FILTER_SANITIZE_NUMBER_INT ) );
                                    $order->save();
                                }
                            }
                            $this->wcz_log( "Checkout: {$accountid_meta}", 'info' );
                        }
                    }
                    // reduce inventory
                    global $zelle_fs;
                    if ( zelle_fs()->is_plan__premium_only( 'pro' ) && $this->ZelleStockManagement == 'yes' ) {
                    } else {
                        // $order->reduce_order_stock(); // deprecated
                        wc_reduce_stock_levels( $order_id );
                    }
                    // Mark as on-hold (we're awaiting the payment).
                    if ( zelle_fs()->is_plan__premium_only( 'pro' ) && $this->processOrder == 'yes' ) {
                        $order->payment_complete();
                    } else {
                        $order->update_status( apply_filters( 'woocommerce_zelle_process_payment_order_status', 'on-hold', $order ), __( "Waiting for the {$this->method_title} payment", WCZELLE_PLUGIN_TEXT_DOMAIN ) );
                    }
                    if ( zelle_fs()->is_plan__premium_only( 'pro' ) && 'yes' == $this->enableNote ) {
                        require_once WCZELLE_PLUGIN_DIR . 'includes/notifications/note.php';
                    }
                    global $woocommerce;
                    $woocommerce->cart->empty_cart();
                    // Redirect to the thank you page
                    return array(
                        'result'   => 'success',
                        'redirect' => $this->get_return_url( $order ),
                    );
                } else {
                    wc_add_notice( 'Connection error.', 'error' );
                    $this->wcz_log( 'Checkout: Connection error.', 'error' );
                    return;
                }
            } catch ( \Throwable $th ) {
                // print_r($th);
                wc_add_notice( "Something went wrong. {$th}", 'error' );
                $this->wcz_log( "Checkout: Something went wrong. {$th}", 'error' );
                return;
            }
        }

        // Webhook
        public function webhook() {
            return;
            // $order = wc_get_order( $_GET['id'] );
            // $order->payment_complete();
            // update_option('webhook_debug', $_GET);
        }

    }

} else {
    require_once WCZELLE_PLUGIN_DIR . 'includes/notifications/woocommerce.php';
}