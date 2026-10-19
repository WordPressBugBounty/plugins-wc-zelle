<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( class_exists( 'WC_Payment_Gateway' ) ) {
    class WC_Zelle_Gateway extends WC_Payment_Gateway {
        // // ✅ Explicitly declared properties (public, WooCommerce style)
        // public $id;
        // public $icon;
        // public $has_fields;
        // public $method_title;
        // public $method_description;
        // public $enabled;
        // public $title;
        public $ReceiverZelleTag;

        public $ReceiverZELLENo;

        public $ReceiverZelleOwner;

        public $ReceiverZELLEEmail;

        public $ZelleForwardingURL;

        public $ZelleReceiverAccounts;

        public $ZelleStockManagement;

        public $checkout_description;

        public $zelle_notice;

        public $store_instructions;

        public $display_zelle;

        public $ZelleQRCode;

        public $enableNote;

        public $order_note;

        public $disableMenu;

        public $processOrder;

        public $enable_debug;

        public $toggleSupport;

        public $toggleTutorial;

        public $toggleCredits;

        // Whether the gateway's hooks have been added yet. @see __construct()
        private static $hooks_registered = false;

        // public $form_fields;
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
            // PRO pitch, printed above this gateway's settings form
            $this->method_description .= '<div style="background:#fff; border:1px solid #dcdcde; border-left:4px solid #7f54b3; border-radius:2px; padding:16px 20px; margin-top:12px; max-width:700px;">' . '<h3 style="margin-top:0;">Get Zelle PRO</h3>' . '<p>The settings below are locked to the free plan\'s defaults. Upgrade to unlock:</p>' . '<ul style="list-style:disc; margin-left:20px;">' . '<li><strong>A popup checkout</strong>, just like modern gateways - the customer clicks <strong>Place order</strong>, gets their order number and Zelle details in a popup, and confirms once they have paid</li>' . '<li><strong>QR code and a Pay with Zelle button</strong> customers can scan or tap straight from checkout</li>' . '<li><strong><a href="' . esc_url( admin_url( 'admin.php?page=wc_zelle_automated_status' ) ) . '">Automatic order updates</a></strong> via emailreceipts.io - orders move from on-hold to processing on their own the moment payment is detected, no more digging through your bank for a matching payment</li>' . '<li>Import your synced emailreceipts.io accounts straight into the Receiver Zelle fields</li>' . '<li>Custom text for the checkout notice, the thank you page and the store instructions your customers see</li>' . '<li>Choose exactly when stock is reduced, and whether orders process automatically or wait on-hold</li>' . '<li>Debug logging when something needs troubleshooting</li>' . '</ul>' . '<p><a href="' . esc_url( $upgrade_url ) . '" target="_blank" style="display:inline-block; background:#7f54b3; color:#fff; text-decoration:none; padding:8px 18px; border-radius:3px; font-weight:600;">Upgrade to PRO</a></p>' . '</div>';
            $this->init_settings();
            $this->enabled = $this->get_option( 'enabled' );
            $this->title = ( $this->get_option( 'checkout_title' ) ? $this->get_option( 'checkout_title' ) : $this->method_title );
            $this->ReceiverZelleTag = $this->get_option( 'ReceiverZelleTag' );
            $this->ReceiverZELLENo = $this->get_option( 'ReceiverZELLENo' );
            $this->ReceiverZELLEEmail = $this->get_option( 'ReceiverZELLEEmail' );
            $this->ReceiverZelleOwner = $this->get_option( 'ReceiverZelleOwner' );
            $this->ZelleForwardingURL = wp_kses_post( get_bloginfo( 'url' ) . '/wp-json/wc-zelle/v1/update-zelle-order' );
            // In memory only, for the read only settings field: saving it here wrote to the database whenever the site URL varied (e.g. per language)
            $this->settings['ZelleForwardingURL'] = $this->ZelleForwardingURL;
            $this->ZelleReceiverAccounts = $this->get_option( 'ZelleReceiverAccounts' );
            $this->ZelleStockManagement = $this->get_option( 'ZelleStockManagement' );
            $this->checkout_description = $this->get_option( 'checkout_description' );
            $this->zelle_notice = $this->get_option( 'zelle_notice' );
            $this->store_instructions = $this->get_option( 'store_instructions' );
            $this->display_zelle = $this->get_option( 'display_zelle' );
            $this->ZelleQRCode = $this->get_option( 'ZelleQRCode' );
            $this->enableNote = $this->get_option( 'enableNote' );
            $this->order_note = $this->get_option( 'order_note' );
            $this->disableMenu = $this->get_option( 'disableMenu' ) ?? 'no';
            $this->processOrder = $this->get_option( 'processOrder' ) ?? 'no';
            $this->enable_debug = $this->get_option( 'enable_debug' );
            $this->toggleSupport = $this->get_option( 'toggleSupport' );
            $this->toggleTutorial = $this->get_option( 'toggleTutorial' );
            $this->toggleCredits = $this->get_option( 'toggleCredits' );
            // upgrade display_zelle
            $this->wc_zelle_upgrade_display_zelle();
            // Method with all the options fields
            $this->wc_zelle_init_form_fields();
            // Gateways can support subscriptions, refunds, saved payment methods
            $this->supports = array('products');
            if ( self::$hooks_registered ) {
                return;
            }
            self::$hooks_registered = true;
            // This action hook saves the settings
            add_action( "woocommerce_update_options_payment_gateways_{$this->id}", array($this, 'process_admin_options') );
            // Runs after the settings above are saved, to copy a chosen emailreceipts.io
            // account into the Receiver Zelle fields
            add_action( "woocommerce_update_options_payment_gateways_{$this->id}", array($this, 'wc_zelle_import_receiver_account'), 20 );
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
                add_action( 'woocommerce_order_status_pending_to_on-hold_notification', array($this, 'wc_zelle_zoho_notification') );
            }
            // WooCommerce Blocks support is hooked from zelle.php: `woocommerce_blocks_loaded`
            // has already run by the time this gateway is constructed
            // Extending stock hold time woocommerce > includes > wc-order-functions.php wc_cancel_unpaid_orders
            add_action( 'woocommerce_cancel_unpaid_orders', array($this, 'wc_zelle_cancel_on_hold_orders'), 20 );
            add_filter(
                'woocommerce_payment_complete_reduce_order_stock',
                array($this, 'wc_zelle_reduce_stock_on_hold'),
                10,
                2
            );
        }

        /**
         * Plugin options, one set for both plans.
         * PRO only fields carry the $pro / $edit_with_pro markers and are locked on the free plan by the $pro_only pass at the end
         */
        public function wc_zelle_init_form_fields() {
            $upgrade_url = zelle_fs()->get_upgrade_url();
            $pro = ( zelle_fs()->is_plan__premium_only( 'pro' ) ? '' : ' <a style="text-decoration:none" href="' . esc_url( $upgrade_url ) . '" target="_blank"><sup style="color:red">PRO</sup></a>' );
            $edit_with_pro = ( zelle_fs()->is_plan__premium_only( 'pro' ) ? '' : ' <a style="text-decoration:none" href="' . esc_url( $upgrade_url ) . '" target="_blank"><sup style="color:red">' . __( 'EDIT WITH', WCZELLE_PLUGIN_TEXT_DOMAIN ) . ' PRO</sup></a>' );
            $new = ' <sup style="color:#0c0">NEW</sup>';
            $newFeature = " <sup style='color:#0c0;'>NEW FEATURE</sup>";
            $improved = " <sup style='color:#0c0;'>IMPROVED</sup>";
            $improvedFeature = " <sup style='color:#0c0;'>IMPROVED FEATURE</sup>";
            $comingSoon = " <sup style='color:#00c;'>COMING SOON</sup>";
            $emrcpts = ' <a href="' . esc_attr( wp_kses_post( admin_url( 'admin.php?page=wc_zelle_automated_status' ) ) ) . '">CONNECT STORE</a>';
            $default_checkout_description = '<p>Please <strong>use your Order Number (available once you place order)</strong> as the payment reference.</p>';
            $default_zelle_notice = "<p>We are checking our systems to confirm that we received your payment. If you haven't sent the money already, please make sure to do so now.</p>" . '<p>Once confirmed, we will proceed with the shipping and delivery options you chose.</p>' . '<p>Thank you for doing business with us! You will be updated regarding your order details soon.</p>';
            $default_store_instructions = "Please send the total amount requested to our store if you haven't yet.";
            $default_order_note = esc_html__( 'Your order was received!', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '<br><br>' . sprintf( __( 'We are checking Zelle to confirm that we received the %s you sent so we can start processing your order.', WCZELLE_PLUGIN_TEXT_DOMAIN ), '<strong>**order_total**</strong>' ) . '<br><br>' . esc_html__( 'Thank you for doing business with us', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '!<br> ' . esc_html__( 'You will be updated regarding your order details soon', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '<br><br>' . esc_html__( 'Kindest Regards', WCZELLE_PLUGIN_TEXT_DOMAIN ) . ',<br>**shop_name**<br>**shop_email**<br>**shop_url**<br>';
            $payment_url = $this->wc_zelle_url( 1 );
            $qr_code_url = $this->wc_zelle_qrcode_url( 1 );
            $qr_code = $this->wc_zelle_qrcode( 1, 'advanced' );
            // Financial accounts emailreceipts.io synced to this store
            $receiver_accounts = $this->wc_zelle_emrcpts_accounts_options();
            $this->form_fields = array(
                'enabled'               => array(
                    'title'   => 'Enable ZELLE',
                    'label'   => 'Check to Enable / Uncheck to Disable',
                    'type'    => 'checkbox',
                    'default' => 'no',
                ),
                'checkout_title'        => array(
                    'title'       => 'Checkout Title',
                    'type'        => 'text',
                    'description' => 'This is the title which the user sees on the checkout page.',
                    'default'     => 'Zelle',
                    'placeholder' => 'Zelle',
                ),
                'display_zelle'         => array(
                    'title'       => 'Checkout page design templates' . $improved . $pro,
                    'label'       => 'Choose how you want customers to see the Zelle info on checkout' . $edit_with_pro,
                    'type'        => 'select',
                    'description' => 'Choose how you want customers to see the Zelle info on checkout.
					<p><strong>PRO designs</strong> are enhanced with extra features such as <strong>copy to clipboard</strong>, <strong>QR code</strong>, <strong>Zelle button/link</strong>, etc to help autofill info when moving to Zelle.</p>
					<p><strong>Design 1:</strong> keeps the Zelle info off the checkout page and shows it in a popup once the customer clicks <strong>Place order</strong>, the way modern payment methods do. Your <em>Checkout Page Notice</em> below is shown in that popup. The order is created as <strong>pending payment</strong> first so the popup can show its order number for the Zelle memo, and it is only confirmed once the customer confirms in the popup. Closing the popup keeps that pending order, so reopening it shows the same order number, and it is removed if the customer places the order with a different payment method instead. The Zelle info is still shown on the order confirmation page, which is where customers go if their browser cannot open the popup.</p>
					<p><strong>Design 2:</strong> shows the Zelle info on checkout in full width columns.</p>
					<p><strong>Design 3:</strong> shows the Zelle info on checkout in half width columns.</p>' . (( zelle_fs()->is_plan__premium_only( 'pro' ) ? '' : '<p>Designs 1 and 3 need an active PRO licence. Without one this store uses design 2, so the checkout always carries the full Zelle info.</p>' )) . $edit_with_pro,
                    'default'     => '2',
                    'options'     => array(
                        '1' => '1: show the Zelle info in a popup when Place order is clicked' . $edit_with_pro,
                        '2' => '2: show the Zelle info on checkout (full width columns)',
                        '3' => '3: show the Zelle info on checkout (half width columns)' . $edit_with_pro,
                    ),
                ),
                'ReceiverZelleTag'      => array(
                    'title'             => "Receiver Zelle Tag" . $new,
                    'type'              => 'text',
                    'description'       => 'This is the Zelle Tag (the custom handle you can claim in your Zelle/Bank mobile app) associated with your store Zelle/Bank account. Customers can send money to this tag without needing your email or phone number.
					<p>Zelle recommends 6 to 40 characters using only letters, numbers and hyphens (no spaces or other special characters). Tags are not case sensitive. Save whatever your bank gave you, then check your checkout page to confirm it is displayed exactly as you typed it.</p>',
                    'custom_attributes' => array(
                        'maxlength' => 50,
                    ),
                ),
                'ReceiverZELLENo'       => array(
                    'title'       => 'Receiver Zelle No',
                    'type'        => 'text',
                    'description' => 'This is the phone number associated with your store Zelle/Bank account or your receiving Zelle/Bank account. Customers will send money to this number',
                    'placeholder' => "+1234567890",
                ),
                'ReceiverZELLEEmail'    => array(
                    'title'       => "Receiver Zelle Owner's Email",
                    'type'        => 'text',
                    'description' => 'This is the email associated with your store Zelle/Bank account or your receiving Zelle/Bank account. Customers will send money to this email',
                    'default'     => "@gmail.com",
                    'placeholder' => "email@website.com",
                ),
                'ReceiverZelleOwner'    => array(
                    'title'       => "Receiver Zelle Owner's Name",
                    'type'        => 'text',
                    'description' => 'This is the name associated with your store Zelle/Bank account. Customers will send money to this Zelle/Bank account name',
                    'placeholder' => 'Jane D',
                ),
                'ZelleQRCode'           => array(
                    'title'       => 'Your Zelle QR code URL on the checkout page and the thank you page' . $improved . $pro,
                    'label'       => 'Fill this in to show the QR code and the Pay with Zelle button. Leave it empty to show neither' . $edit_with_pro,
                    'type'        => 'text',
                    'description' => 'Input your <strong>https://enroll.zellepay.com/qr-codes?data=***</strong> Zelle QR code URL.' . "<br>To get it, scan the Zelle QR code you get from your Zelle/Bank app with your camera app and extract the URL it redirects to in your browser.<br>It should have this format: https://enroll.zellepay.com/qr-codes?data=***" . '<br>Otherwise <a href="' . admin_url( '/media-new.php' ) . '" target="_blank">Upload your Zelle QR code</a> to your media uploads then <br>copy and input the uploaded ' . wp_upload_dir()['baseurl'] . '/*** URL' . $edit_with_pro . (( $qr_code_url ? '<br><p>Preview below what your customers will see. <a href="mailto:info@theafricanboss.com?cc=' . '%0A' . get_bloginfo( 'admin_email' ) . '&subject=Invalid%20Zelle%20QR%20code&body=Please%20help!%0A%0AMy%20Zelle%20QR%20code%20is%20invalid.%0A%0AZelle%20link%3A%20' . urlencode( $payment_url ) . '%0A%0A' . $this->ReceiverZelleOwner . '%0A' . get_bloginfo( 'name' ) . '%0A' . get_bloginfo( 'url' ) . '">If the QR code is invalid, click here for help!</a></p><br>' . $qr_code : '' )),
                    'placeholder' => 'https://enroll.zellepay.com/qr-codes?data=***',
                ),
                'ZelleForwardingURL'    => array(
                    'title'       => 'Connect your Email Receipts via emailreceipts.io' . $emrcpts,
                    'type'        => 'text',
                    'description' => 'This is the URL that will be exported to emailreceipts.io while setting up' . $emrcpts,
                    'default'     => $this->ZelleForwardingURL,
                    'placeholder' => $this->ZelleForwardingURL,
                    'css'         => 'width:80%; pointer-events: none;',
                    'class'       => 'disabled',
                ),
                'ZelleReceiverAccounts' => array(
                    'title'       => 'Receiver Accounts from emailreceipts.io' . $newFeature . $pro,
                    'label'       => 'Import the Zelle accounts registered at emailreceipts.io' . $edit_with_pro,
                    'type'        => 'select',
                    'description' => 'The Zelle accounts registered at emailreceipts.io for this store. Pick one and <strong>Save changes</strong> to copy its tag, email and phone number into the Receiver Zelle fields above.
					<p>Nothing is copied automatically: leave this on <em>Do not import</em> and type your own details above instead if you would rather not import your accounts from emailreceipts.io</p>
					<p>The <strong>list is synced</strong> with emailreceipts.io when you <strong>connect a financial account</strong> or when you <strong>sync existing accounts</strong> at' . $emrcpts . '</p>' . $edit_with_pro,
                    'default'     => '',
                    'options'     => $receiver_accounts,
                ),
                'checkout_description'  => array(
                    'title'       => 'Checkout Page Notice' . $pro,
                    'type'        => 'textarea',
                    'description' => "This is the text a customer sees in the payment gateway box on the checkout page.{$edit_with_pro}<br>Default:<br>{$default_checkout_description}",
                    'default'     => $default_checkout_description,
                ),
                'zelle_notice'          => array(
                    'title'       => 'Thank You Notice' . $pro,
                    'type'        => 'textarea',
                    'description' => "This is the text a customer sees on the thank you/order confirmation page after placing an order.{$edit_with_pro}<br>Default:<br>{$default_zelle_notice}",
                    'default'     => $default_zelle_notice,
                ),
                'store_instructions'    => array(
                    'title'       => 'Store Instructions' . $pro,
                    'type'        => 'textarea',
                    'description' => "Store Instructions that will be added to the thank you page and emails.{$edit_with_pro}<br>Default:<br>{$default_store_instructions}",
                    'default'     => $default_store_instructions,
                ),
                'enableNote'            => array(
                    'title'       => 'Enable/Disable adding a note to orders' . $pro,
                    'label'       => 'Check to enable sending note / Uncheck to disable sending note' . $edit_with_pro,
                    'type'        => 'checkbox',
                    'description' => 'A note will be added to your order and an email about that note will be sent to your email',
                    'default'     => 'yes',
                ),
                'order_note'            => array(
                    'title'       => 'Admin Order Note',
                    'type'        => 'textarea',
                    'description' => 'This is a note added to the order email. You may use available shortcodes as needed like in this default order note below:' . $edit_with_pro . '<br>' . $default_order_note,
                    'default'     => $default_order_note,
                ),
                'ZelleStockManagement'  => array(
                    'title'       => 'Reduce Stock ONLY after payment receipt' . $pro,
                    'label'       => 'Uncheck to reduce stock when order goes on-hold / Check to reduce stock when order goes to processing' . $edit_with_pro,
                    'type'        => 'checkbox',
                    'description' => 'If you want to reduce stock once payment is received, check this box.' . '<p>While unchecked, stock is reduced as soon as the order goes on-hold, and WooCommerce\'s own <strong>Hold stock (minutes)</strong> setting decides how long that reserved stock is held before the order is automatically cancelled and the stock released.<br>' . '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=products&section=inventory' ) ) . '" target="_blank">Edit Hold stock (minutes) in WooCommerce &rsaquo; Settings &rsaquo; Products &rsaquo; Inventory</a></p>',
                    'default'     => 'no',
                ),
                'processOrder'          => array(
                    'title'       => 'Enable/Disable processing orders automatically' . $pro,
                    'label'       => 'Check to enable processing orders / Uncheck to disable processing orders' . $edit_with_pro,
                    'type'        => 'checkbox',
                    'description' => '<p>When checked, orders will automatically be processed after checkout (whether payment was sent or not).</p>
						<p>When unchecked, orders will be put on-hold until you manually process them or use emailreceipts.io to auto-process them</p>',
                    'default'     => 'no',
                ),
                'enable_debug'          => array(
                    'title'       => 'Enable Debug' . $pro,
                    'label'       => 'Check to Enable / Uncheck to Disable' . $edit_with_pro,
                    'type'        => 'checkbox',
                    'description' => 'This will enable debug mode to help you troubleshoot issues. <a href="' . admin_url( 'admin.php?page=wc-status&tab=logs' ) . '" target="_blank">Access Logs here</a>',
                    'default'     => 'no',
                ),
                'toggleSupport'         => array(
                    'title'       => 'Enable Support message',
                    'label'       => 'Check to Enable / Uncheck to Disable',
                    'type'        => 'checkbox',
                    'description' => 'Help your customers checkout with ease by letting them know how to contact you',
                    'default'     => 'yes',
                ),
                'toggleTutorial'        => array(
                    'title'       => 'Enable Tutorial to display 1min video link',
                    'label'       => 'Check to Enable / Uncheck to Disable',
                    'type'        => 'checkbox',
                    'description' => 'Help your customers checkout with ease by showing this tutorial link',
                    'default'     => 'no',
                ),
                'toggleCredits'         => array(
                    'title'       => 'Enable Credits to display Powered by The African Boss',
                    'label'       => 'Check to Enable / Uncheck to Disable',
                    'type'        => 'checkbox',
                    'description' => 'Help us spread the word about this plugin by sharing that we made this plugin',
                    'default'     => 'no',
                ),
            );
            // PRO settings, read only on the free plan (their behaviour is PRO gated anyway), with any extra css each needs
            $pro_only = array(
                'display_zelle'         => '',
                'ZelleQRCode'           => '',
                'ZelleReceiverAccounts' => '',
                'checkout_description'  => 'width:80%;',
                'zelle_notice'          => 'width:80%;',
                'store_instructions'    => 'width:80%;',
                'enableNote'            => '',
                'order_note'            => '',
                'ZelleStockManagement'  => '',
                'processOrder'          => '',
                'enable_debug'          => '',
            );
            foreach ( $pro_only as $field => $css ) {
                if ( !isset( $this->form_fields[$field] ) ) {
                    continue;
                }
                $this->form_fields[$field]['css'] = trim( $css . ' pointer-events: none;' );
                $this->form_fields[$field]['class'] = 'disabled';
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
            if ( empty( trim( $qr_code_url ) ) ) {
                return $qr_code;
            }
            // Link the QR code only when there is a payment URL: an empty href looks clickable but goes nowhere
            $has_payment_url = '' !== trim( (string) $payment_url );
            $link_open = ( $has_payment_url ? '<a class="wcz-qr" href="' . esc_url( $payment_url ) . '" target="_blank">' : '<span class="wcz-qr">' );
            $link_close = ( $has_payment_url ? '</a>' : '</span>' );
            if ( strpos( $payment_url, 'https://enroll.zellepay.com/qr-codes' ) === false ) {
                $qr_code = '<img width="150" height="150" alt="' . esc_attr( $this->method_title ) . ' link" src="' . esc_attr( $qr_code_url ) . '">';
                $qr_code = $link_open . $qr_code . $link_close;
                return wp_kses_post( $qr_code );
            }
            if ( $type === "advanced" ) {
                $qr_code .= $link_open;
                // $qr_code .= '<p>' . esc_html__( 'If using the Zelle app, scan/click below', WCZELLE_PLUGIN_TEXT_DOMAIN ) . ':</p>';
                $default_qrcode = '<img width="150" height="150" alt="' . esc_attr( $this->method_title ) . ' QR code" src="' . esc_attr( $qr_code_url ) . '" />';
                $qr_code .= '<div id="wc_zelle_qrcode">' . $default_qrcode . '</div>';
                $qr_code .= $link_close . '<p class="wcz-qr-note">' . esc_html__( 'Scan with your Zelle/Bank app', WCZELLE_PLUGIN_TEXT_DOMAIN ) . (( $has_payment_url ? '<br>' . esc_html__( 'or click the button below', WCZELLE_PLUGIN_TEXT_DOMAIN ) : '' )) . '</p>';
                // Only worth a button when it has somewhere to go
                if ( $has_payment_url ) {
                    $qr_code .= '<a class="' . esc_attr( wc_zelle_button_class( true, 'wcz-pay-btn' ) ) . '" role="button" href="' . esc_url( $payment_url ) . '" target="_blank" rel="noopener"><img class="wcz-pay-icon" width="24" height="24" alt="" aria-hidden="true" src="' . esc_url( WCZELLE_PLUGIN_DIR_URL . 'assets/images/zelle_35.png' ) . '" /><span class="wcz-pay-label">' . esc_html__( 'Pay with Zelle', WCZELLE_PLUGIN_TEXT_DOMAIN ) . '</span></a>';
                }
            } else {
                $qr_code = $link_open . '<img width="150" height="150" alt="' . esc_attr( $this->method_title ) . ' link" src="' . esc_attr( $qr_code_url ) . '">' . $link_close;
                // // or with local QR code JS, no logo, no button
                // $qr_code .= '<a href="'. esc_url( $payment_url ) . '" target="_blank">';
                // // $qr_code .= '<p>' . esc_html__( 'If using the Zelle app, scan/click below', WCZELLE_PLUGIN_TEXT_DOMAIN ) . ':</p>';
                // $default_qrcode = '<img class="logo-qr mb-1" width="150px" height="150px" src="'. esc_attr( $qr_code_url ) . '" />';
                // $qr_code .= '<div id="wc_zelle_qrcode">' . $default_qrcode . '</div>';
                // $qr_code .= '</a>';
            }
            return wp_kses_post( $qr_code );
        }

        /**
         * The financial accounts emailreceipts.io synced to this store
         * @return array
         */
        public function wc_zelle_emrcpts_accounts() {
            $synced = get_option( "wc_{$this->id}_emrcpts_accounts", array() );
            return ( !empty( $synced['accounts'] ) && is_array( $synced['accounts'] ) ? $synced['accounts'] : array() );
        }

        /**
         * One synced financial account by its emailreceipts.io id
         * @param string|int $id emailreceipts.io financial account id
         * @return array
         */
        public function wc_zelle_emrcpts_account( $id ) {
            foreach ( $this->wc_zelle_emrcpts_accounts() as $account ) {
                if ( (string) $account['id'] === (string) $id ) {
                    return $account;
                }
            }
            return array();
        }

        /**
         * Save the financial accounts emailreceipts.io just sent over
         * @param array $accounts Financial accounts
         * @param string $website Website they were synced for
         * @param string $plugin REST namespace they were synced to
         * @return array The accounts that were saved
         */
        public function wc_zelle_store_emrcpts_accounts( $accounts, $website = '', $plugin = '' ) {
            $synced_accounts = array();
            foreach ( (array) $accounts as $account ) {
                $account = (array) $account;
                if ( empty( $account['id'] ) ) {
                    continue;
                }
                $synced_accounts[] = array(
                    'id'           => absint( $account['id'] ),
                    'accountid'    => ( isset( $account['accountid'] ) ? sanitize_text_field( wp_specialchars_decode( $account['accountid'], ENT_QUOTES ) ) : '' ),
                    'accountname'  => ( isset( $account['accountname'] ) ? sanitize_text_field( wp_specialchars_decode( $account['accountname'], ENT_QUOTES ) ) : '' ),
                    'accountemail' => ( isset( $account['accountemail'] ) ? sanitize_email( $account['accountemail'] ) : '' ),
                    'accountphone' => ( isset( $account['accountphone'] ) ? sanitize_text_field( $account['accountphone'] ) : '' ),
                    'institution'  => ( isset( $account['institution'] ) ? sanitize_text_field( wp_specialchars_decode( $account['institution'], ENT_QUOTES ) ) : '' ),
                );
            }
            update_option( "wc_{$this->id}_emrcpts_accounts", array(
                'accounts' => $synced_accounts,
                'website'  => esc_url_raw( $website ),
                'plugin'   => sanitize_text_field( $plugin ),
                'synced'   => time(),
            ) );
            return $synced_accounts;
        }

        /**
         * The synced financial accounts as Receiver Accounts select options
         * @return array
         */
        public function wc_zelle_emrcpts_accounts_options() {
            $options = array(
                '' => 'Do not import an account',
            );
            foreach ( $this->wc_zelle_emrcpts_accounts() as $account ) {
                $label = array_filter( array(
                    $account['accountname'],
                    $account['accountid'],
                    $account['accountemail'],
                    $account['accountphone']
                ) );
                $label = ( !empty( $label ) ? implode( ' | ', $label ) : "Account {$account['id']}" );
                if ( !empty( $account['institution'] ) ) {
                    $label .= " ({$account['institution']})";
                }
                $options[$account['id']] = $label;
            }
            return $options;
        }

        /**
         * Copy the chosen emailreceipts.io account into the Receiver Zelle fields on save.
         * The choice is reset afterwards so a later save never overwrites details typed in by hand
         */
        public function wc_zelle_import_receiver_account() {
            return;
            $this->init_settings();
            // process_admin_options() just saved the posted values
            $selected = $this->get_option( 'ZelleReceiverAccounts' );
            if ( empty( $selected ) ) {
                return;
            }
            $this->update_option( 'ZelleReceiverAccounts', '' );
            $account = $this->wc_zelle_emrcpts_account( $selected );
            if ( empty( $account ) ) {
                $this->wcz_log( "Receiver Accounts: emailreceipts.io account {$selected} is no longer synced to this store", 'error' );
                return;
            }
            $tag = ltrim( trim( $account['accountid'] ), '@' );
            $is_valid_tag = !empty( $tag ) && filter_var( $tag, FILTER_VALIDATE_EMAIL ) === false && preg_match( '/^[A-Za-z0-9-]+$/', $tag );
            if ( $is_valid_tag ) {
                $this->update_option( 'ReceiverZelleTag', $tag );
            }
            if ( !empty( $account['accountemail'] ) ) {
                $this->update_option( 'ReceiverZELLEEmail', $account['accountemail'] );
            }
            if ( !empty( $account['accountphone'] ) ) {
                $this->update_option( 'ReceiverZELLENo', $account['accountphone'] );
            }
            $this->wcz_log( "Receiver Accounts: imported emailreceipts.io account {$account['id']} into the Receiver Zelle fields" );
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
            // Not require_once: a theme or checkout plugin may render the payment box more than once
            require WCZELLE_PLUGIN_DIR . 'includes/pages/checkout.php';
        }

        // Payment Custom JS and CSS
        public function wc_zelle_payment_scripts() {
            /* // Only load scripts on classic checkout pages (not block-based checkout)
            			if (!is_checkout() && !is_checkout_pay_page()) { return; }
            			// Check if this is a block-based checkout - if so, scripts are handled by blocks
            			if (function_exists('has_block') && (has_block('woocommerce/checkout') || has_block('woocommerce/cart'))) { return; } */
            if ( 'no' === $this->enabled || empty( $this->ReceiverZELLEEmail ) && empty( $this->ReceiverZELLENo ) && empty( $this->ReceiverZelleTag ) ) {
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
            // Unpaid orders only
            if ( $order && $this->id === $order->get_payment_method() && $order->has_status( array('pending', 'checkout-draft', 'on-hold') ) ) {
                require WCZELLE_PLUGIN_DIR . 'includes/pages/thankyou.php';
            }
        }

        public function wc_zelle_processed( $order_id, $posted_data, $order ) {
            if ( !$order_id || !$order ) {
                return;
            }
            if ( $this->id === $order->get_payment_method() && function_exists( 'wc_zelle_order_processed' ) ) {
                wc_zelle_order_processed( $order );
            }
        }

        // Add content to the WC emails
        public function wc_zelle_email_instructions( $order, $sent_to_admin, $plain_text = false ) {
            if ( !$sent_to_admin && $order->has_status( 'on-hold' ) && $this->id === $order->get_payment_method() ) {
                $order_id = ( method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id );
                // Not require_once: every on-hold email sent in the request needs its own copy
                require WCZELLE_PLUGIN_DIR . 'includes/notifications/email.php';
            }
        }

        // Zoho support
        public function wc_zelle_zoho_notification( $order_id ) {
            if ( !$order_id || !zelle_fs()->is_plan__premium_only( 'pro' ) ) {
                return;
            }
            $order = wc_get_order( $order_id );
            if ( !$order ) {
                return;
            }
            // Only for on hold new orders
            if ( !$order->has_status( 'on-hold' ) || $this->id !== $order->get_payment_method() ) {
                return;
            }
            // Exit
            require_once WCZELLE_PLUGIN_DIR . 'includes/functions/zoho.php';
        }

        /**
         * The checkout design in effect: legacy yes/no values are mapped to 1/2.
         * Without PRO every design falls back to 2, so the checkout always carries the full Zelle details
         *
         * @return string '1', '2' or '3'
         */
        public function wc_zelle_display() {
            $design = (string) $this->display_zelle;
            if ( 'no' === $design ) {
                $design = '1';
            } else {
                if ( 'yes' === $design ) {
                    $design = '2';
                }
            }
            if ( !in_array( $design, array('1', '2', '3'), true ) ) {
                $design = '2';
            }
            // Free plan falls back to design 2; the stored setting is kept for when the licence returns
            if ( '2' !== $design && !zelle_fs()->is_plan__premium_only( 'pro' ) ) {
                $design = '2';
            }
            return $design;
        }

        /**
         * Rewrite a stored design that no longer exists, so the settings screen shows
         * the same design the checkout is using. Only ever writes when it has to
         */
        public function wc_zelle_upgrade_display_zelle() {
            $stored = (string) $this->display_zelle;
            $legacy = array(
                'no'  => '1',
                'yes' => '2',
            );
            if ( !isset( $legacy[$stored] ) ) {
                return;
            }
            $this->display_zelle = $legacy[$stored];
            $this->update_option( 'display_zelle', $this->display_zelle );
        }

        /**
         * Checkout design 1 (PRO): the checkout page stays short and the Zelle info is
         * shown in a popup opened by the Place order button
         * @return bool
         */
        public function wc_zelle_use_popup() {
            return '1' === $this->wc_zelle_display();
        }

        /**
         * Is there a QR code to show? The QR code URL is the only switch (it replaced the old enableQRCode toggle)
         * @return bool
         */
        public function wc_zelle_has_qrcode() {
            return '' !== trim( (string) $this->ZelleQRCode );
        }

        // validate zelle_email
        public function validate_fields() {
            if ( isset( $_POST[$this->id . 'do_not_checkout'] ) ) {
                wc_add_notice( esc_html( __( 'Please try another payment method', WCZELLE_PLUGIN_TEXT_DOMAIN ) ), 'error' );
                $this->wcz_log( esc_html( __( 'Checkout: Please try another payment method', WCZELLE_PLUGIN_TEXT_DOMAIN ) ), 'error' );
            }
            // The popup's draft order is created before the customer sees the popup, so its fields are validated on the confirmed checkout. @see WC_Zelle_Draft_Order
            if ( $this->wc_zelle_use_popup() && !empty( $_POST['wc_zelle_draft'] ) ) {
                return;
            }
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
                    if ( $this->wc_zelle_holds_stock_on_hold() ) {
                        wc_maybe_reduce_stock_levels( $order_id );
                    }
                    if ( zelle_fs()->is_plan__premium_only( 'pro' ) && $this->processOrder == 'yes' ) {
                        $order->payment_complete();
                    } else {
                        // Mark as on-hold (we're awaiting the payment).
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
                // The exception goes to the log only: as a string it carries the stack trace and server paths
                wc_add_notice( esc_html__( 'Something went wrong. Please try again or choose another payment method.', WCZELLE_PLUGIN_TEXT_DOMAIN ), 'error' );
                wc_get_logger()->error( "Checkout: Something went wrong. {$th}", array(
                    'source' => $this->id,
                ) );
                return;
            }
        }

        public function wc_zelle_holds_stock_on_hold() {
            return !(zelle_fs()->is_plan__premium_only( 'pro' ) && 'yes' === $this->ZelleStockManagement);
        }

        /**
         * Keeps WooCommerce from reducing stock when an order of this gateway goes on-hold
         * while stock is only reduced after payment
         *
         * @param bool $reduce   Whether to reduce stock.
         * @param int  $order_id Order ID.
         * @return bool
         */
        public function wc_zelle_reduce_stock_on_hold( $reduce, $order_id ) {
            if ( !$reduce || $this->wc_zelle_holds_stock_on_hold() ) {
                return $reduce;
            }
            $order = wc_get_order( $order_id );
            if ( $order && $this->id === $order->get_payment_method() && 'on-hold' === $order->get_status() ) {
                return false;
            }
            return $reduce;
        }

        /**
         * Cancel this gateway's on-hold orders once WooCommerce's Hold stock (minutes) has passed.
         * Same terms as wc_cancel_unpaid_orders(): stock managed, checkout orders, and only while stock is reduced on-hold
         */
        public function wc_zelle_cancel_on_hold_orders() {
            // wp-admin/admin.php?page=wc-settings&tab=products&section=inventory
            $held_duration = absint( get_option( 'woocommerce_hold_stock_minutes' ) );
            if ( $held_duration < 1 || 'yes' !== get_option( 'woocommerce_manage_stock' ) ) {
                return;
            }
            if ( !$this->wc_zelle_holds_stock_on_hold() ) {
                return;
            }
            $args = array(
                'status'         => 'on-hold',
                'payment_method' => $this->id,
                'limit'          => -1,
                'date_modified'  => '<' . (time() - $held_duration * MINUTE_IN_SECONDS),
                'return'         => 'ids',
            );
            $orders = wc_get_orders( $args );
            $start = time();
            foreach ( $orders as $order_id ) {
                // Cancelled orders leave the query, so what is left is picked up on WooCommerce's next run
                if ( time() - $start > 15 ) {
                    break;
                }
                try {
                    $order = wc_get_order( $order_id );
                    if ( !$order instanceof WC_Order || $order->get_payment_method() !== $this->id ) {
                        continue;
                    }
                    // Same filter and default as wc_cancel_unpaid_orders()
                    if ( !apply_filters( 'woocommerce_cancel_unpaid_order', in_array( $order->get_created_via(), array('checkout', 'store-api'), true ), $order ) ) {
                        continue;
                    }
                    $order->update_status( 'cancelled', __( $this->id . ' on-hold order cancelled: woocommerce hold time limit reached', 'woocommerce' ) );
                } catch ( \Throwable $th ) {
                    wc_get_logger()->error( "Cancel on-hold order {$order_id}: {$th}", array(
                        'source' => $this->id,
                    ) );
                }
            }
        }

    }

} else {
    require_once WCZELLE_PLUGIN_DIR . 'includes/notifications/woocommerce.php';
}