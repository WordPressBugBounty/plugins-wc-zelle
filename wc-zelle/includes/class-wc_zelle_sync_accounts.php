<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
if ( !class_exists( 'WC_Zelle_Sync_Accounts' ) && class_exists( 'WC_Zelle_Update_Order' ) ) {
    class WC_Zelle_Sync_Accounts {
        public $id = 'zelle';

        function register() {
            add_action( 'rest_api_init', array($this, 'wc_zelle_sync_accounts_route') );
        }

        // Create REST API route
        // --- Webhook Address --- https://website.com/wp-json/wc-zelle/v1/sync_financial_accounts
        function wc_zelle_sync_accounts_route() {
            register_rest_route( "wc-{$this->id}/v1", "/sync_financial_accounts", array(
                'methods'             => 'POST',
                "callback"            => array($this, "wc_{$this->id}_emrcpts_sync_financial_accounts"),
                "permission_callback" => "__return_true",
            ) );
        }

        // Store the accounts registered at emailreceipts.io (every plan) so one can be imported into the Receiver Zelle fields (PRO)
        function wc_zelle_emrcpts_sync_financial_accounts( $data ) {
            header( "Content-type: application/json" );
            $message_array = array();
            // emailreceipts.io posts JSON, keep reading the form encoded body as a fallback
            $body = $data->get_json_params();
            if ( empty( $body ) ) {
                $body = $data->get_body_params();
            }
            $signature = ( is_array( $data->get_headers() ) && !empty( $data->get_headers()['x_api_key'] ) ? wp_kses_post( $data->get_headers()['x_api_key'][0] ) : null );
            $origin = get_bloginfo( 'url' );
            $message = "Response by: {$origin}\n";
            $message_array['url'] = $origin;
            $gateway = new WC_Zelle_Update_Order();
            // emailreceipts.io helpers live on the gateway
            $verify = $gateway->wc_zelle_emrcpts_verify_signature( $signature, true );
            if ( is_array( $verify ) && $verify['status'] === true ) {
                $accounts = ( !empty( $body['accounts'] ) && is_array( $body['accounts'] ) ? $body['accounts'] : array() );
                $website = ( !empty( $body['website'] ) ? esc_url_raw( $body['website'] ) : $origin );
                $plugin = ( !empty( $body['plugin'] ) ? sanitize_text_field( $body['plugin'] ) : "wc-{$this->id}" );
                $synced_accounts = $gateway->wc_zelle_store_emrcpts_accounts( $accounts, $website, $plugin );
                $message .= "Financial Accounts: " . count( $synced_accounts ) . "\n";
                $message_array['accounts'] = count( $synced_accounts );
                // Stored either way so they are ready the moment this store upgrades
                $message .= "Importing Receiver Accounts is a PRO feature. Upgrade to import them into your Zelle settings\n";
                $message_array['pro'] = zelle_fs()->is_plan__premium_only( 'pro' );
                $status_code = 200;
                http_response_code( $status_code );
            } else {
                $message .= ( is_array( $verify ) ? "Invalid Signature: " . $verify['message'] . "\n" : "Invalid Request Signature was not verified.\n" );
                $message_array['signature'] = ( is_array( $verify ) ? $verify['message'] : "Invalid Request Signature was not verified." );
                $status_code = 401;
                http_response_code( $status_code );
            }
            $message .= "Status: " . $status_code;
            $emrcpts_response = array(
                'status'  => $status_code,
                'message' => wp_kses_post( $message ),
                'data'    => $message_array,
            );
            // WordPress resets the header set above when it serves the response, so the
            // status goes on the response itself for emailreceipts.io to read it
            $response = rest_ensure_response( $emrcpts_response );
            $response->set_status( $status_code );
            return $response;
        }

    }

    add_action( 'init', function () {
        global $WC_Zelle_Sync_Accounts;
        $WC_Zelle_Sync_Accounts = new WC_Zelle_Sync_Accounts();
        $WC_Zelle_Sync_Accounts->register();
    }, 5 );
}