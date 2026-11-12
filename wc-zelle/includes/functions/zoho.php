<?php

if ( !defined( "ABSPATH" ) ) {
    exit;
}
// A function so require_once still sends for every on-hold order in the request
function wc_zelle_zoho_order_emails(  $order, $gateway  ) {
    return;
    // WooCommerce only fills these in trigger(), which is not called here
    foreach ( ["WC_Email_Customer_On_Hold_Order", "WC_Email_New_Order"] as $wc_zelle_email_id ) {
        $wc_zelle_email = WC()->mailer()->emails[$wc_zelle_email_id];
        $wc_zelle_email->object = $order;
        $wc_zelle_email->placeholders["{order_date}"] = wc_format_datetime( $order->get_date_created() );
        $wc_zelle_email->placeholders["{order_number}"] = $order->get_order_number();
    }
    $customer_email = WC()->mailer()->emails["WC_Email_Customer_On_Hold_Order"];
    $customer_email->recipient = $order->get_billing_email();
    $to = $customer_email->get_recipient();
    $subject = $customer_email->get_subject();
    $message = wc_get_template_html( $customer_email->template_html, [
        "order"              => $order,
        "email_heading"      => $customer_email->get_heading(),
        "additional_content" => $customer_email->get_additional_content(),
        "sent_to_admin"      => false,
        "plain_text"         => false,
        "email"              => $customer_email,
    ] );
    if ( !wc_zelle_zoho_mail(
        $to,
        $subject,
        $message,
        $customer_email->get_headers()
    ) ) {
        $gateway->wcz_log( "Zoho Mail: the customer on-hold email for order {$order->get_id()} was not sent", "error" );
    }
    $admin_email = WC()->mailer()->emails["WC_Email_New_Order"];
    $to = $admin_email->get_recipient();
    $subject = $admin_email->get_subject();
    $message = wc_get_template_html( $admin_email->template_html, [
        "order"              => $order,
        "email_heading"      => $admin_email->get_heading(),
        "additional_content" => $admin_email->get_additional_content(),
        "sent_to_admin"      => true,
        "plain_text"         => false,
        "email"              => $admin_email,
    ] );
    if ( !wc_zelle_zoho_mail(
        $to,
        $subject,
        $message,
        $admin_email->get_headers()
    ) ) {
        $gateway->wcz_log( "Zoho Mail: the admin new order email for order {$order->get_id()} was not sent", "error" );
    }
}

function wc_zelle_zoho_mail(
    $to,
    $subject,
    $message,
    $headers = "",
    $attachments = []
) {
    $atts = apply_filters( "wp_mail", compact(
        "to",
        "subject",
        "message",
        "headers",
        "attachments"
    ) );
    if ( isset( $atts["to"] ) ) {
        $to = $atts["to"];
    }
    if ( !is_array( $to ) ) {
        $to = explode( ",", $to );
    }
    if ( isset( $atts["subject"] ) ) {
        $subject = $atts["subject"];
    }
    if ( isset( $atts["message"] ) ) {
        $message = $atts["message"];
    }
    if ( isset( $atts["headers"] ) ) {
        $headers = $atts["headers"];
    }
    if ( isset( $atts["attachments"] ) ) {
        $attachments = $atts["attachments"];
    }
    if ( !is_array( $attachments ) ) {
        $attach[] = str_replace( "\r\n", "\n", $attachments );
        $attachments = implode( "\n", $attach );
    }
    $content_type = null;
    // Headers
    $cc = $bcc = $reply_to = [];
    if ( empty( $headers ) ) {
        $headers = [];
    } else {
        if ( !is_array( $headers ) ) {
            // Explode the headers out, so this function can take both
            // string headers and an array of headers.
            $tempheaders = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
        } else {
            $tempheaders = $headers;
        }
        $headers = [];
        // If it's actually got contents
        if ( !empty( $tempheaders ) ) {
            // Iterate through the raw headers
            foreach ( (array) $tempheaders as $header ) {
                if ( strpos( $header, ":" ) === false ) {
                    if ( false !== stripos( $header, "boundary=" ) ) {
                        $parts = preg_split( "/boundary=/i", trim( $header ) );
                        $boundary = trim( str_replace( ["'", '"'], "", $parts[1] ) );
                    }
                    continue;
                }
                // Explode them out
                list( $name, $content ) = explode( ":", trim( $header ), 2 );
                // Cleanup crew
                $name = trim( $name );
                $content = trim( $content );
                $content_type = null;
                switch ( strtolower( $name ) ) {
                    case "content-type":
                        if ( strpos( $content, ";" ) !== false ) {
                            list( $type, $charset_content ) = explode( ";", $content );
                            $content_type = trim( $type );
                            if ( false !== stripos( $charset_content, "charset=" ) ) {
                                $charset = trim( str_replace( ["charset=", '"'], "", $charset_content ) );
                            } elseif ( false !== stripos( $charset_content, "boundary=" ) ) {
                                $boundary = trim( str_replace( ["BOUNDARY=", "boundary=", '"'], "", $charset_content ) );
                                $charset = "";
                            }
                            // Avoid setting an empty $content_type.
                        } elseif ( "" !== trim( $content ) ) {
                            $content_type = trim( $content );
                        }
                        break;
                    case "cc":
                        $cc = array_merge( (array) $cc, explode( ",", $content ) );
                        break;
                    case "bcc":
                        $bcc = array_merge( (array) $bcc, explode( ",", $content ) );
                        break;
                    case "reply-to":
                        $reply_to = array_merge( (array) $reply_to, explode( ",", $content ) );
                        break;
                    default:
                        $headers[trim( $name )] = trim( $content );
                        break;
                }
            }
        }
    }
    $content_type = apply_filters( "wp_mail_content_type", $content_type );
    $data = [];
    if ( !empty( $from_name ) ) {
        $data["fromAddress"] = $from_name . "<" . get_option( "zmail_integ_from_email_id" ) . ">";
    } else {
        $data["fromAddress"] = get_option( "zmail_integ_from_name" ) . "<" . get_option( "zmail_integ_from_email_id" ) . ">";
    }
    $zmbcc = "";
    if ( sizeof( $bcc ) > 0 ) {
        $zmbcc = implode( ",", $bcc );
    }
    if ( $zmbcc != "" ) {
        $data["bccAddress"] = $zmbcc;
    }
    $zmcc = "";
    if ( sizeof( $cc ) > 0 ) {
        $zmcc = implode( ",", $cc );
    }
    if ( $zmcc != "" ) {
        $data["ccAddress"] = $zmcc;
    }
    if ( !empty( $reply_to ) ) {
        if ( get_option( "zmail_integ_from_email_id" ) == $to[0] && sizeof( $to ) == 1 ) {
            $start = stripos( $reply_to[0], "<" );
            $length = strlen( $reply_to[0] ) - 1 - $start;
            if ( $start > 1 ) {
                $shortString = substr( $reply_to[0], $start + 1, $length - 1 );
            } else {
                $shortString = $reply_to[0];
            }
            $data["replyTo"] = $shortString;
        }
    }
    if ( !base64_decode( get_option( "zmail_refresh_token" ), true ) ) {
        update_option( "zmail_refresh_token", base64_encode( get_option( "zmail_refresh_token" ) ), false );
    }
    if ( !empty( get_option( "zmail_auth_code" ) ) ) {
        delete_option( "zmail_auth_code" );
    }
    $data["subject"] = $subject;
    $data["content"] = $message;
    $toAddresses = implode( ",", $to );
    $data["toAddress"] = $toAddresses;
    if ( empty( get_option( "zmail_integ_timestamp" ) ) || time() - get_option( "zmail_integ_timestamp" ) > 3000 ) {
        update_option( "zmail_integ_timestamp", time(), false );
        $urlUsingRefreshToken = "https://accounts.zoho." . get_option( "zmail_integ_domain_name" ) . "/oauth/v2/token?refresh_token=" . base64_decode( get_option( "zmail_refresh_token" ) ) . "&grant_type=refresh_token&client_id=" . get_option( "zmail_integ_client_id" ) . "&client_secret=" . get_option( "zmail_integ_client_secret" ) . "&redirect_uri=" . admin_url() . "admin.php?page=zmail-integ-settings&scope=VirtualOffice.messages.CREATE,VirtualOffice.accounts.READ";
        $bodyAccessTok = wp_remote_retrieve_body( wp_remote_post( $urlUsingRefreshToken ) );
        $respoJs = json_decode( $bodyAccessTok );
        update_option( "zmail_access_token", $respoJs->access_token, false );
    }
    if ( !empty( $attachments ) ) {
        $attachmentJSONArr = [];
        $data["attachments"] = $attachments;
        $headers1 = [
            "Authorization" => "Zoho-oauthtoken " . get_option( "zmail_access_token" ),
            "Content-Type"  => "application/octet-stream",
        ];
        $count = 0;
        $flag = "true";
        foreach ( $attachments as $attfile ) {
            $fileName = basename( $attfile );
            $attachurl = "https://mail.zoho." . get_option( "zmail_integ_domain_name" ) . "/api/accounts/" . get_option( "zmail_account_id" ) . "/messages/attachments" . "?fileName=" . $fileName;
            $args = [
                "body"    => file_get_contents( $attfile ),
                "headers" => $headers1,
                "method"  => "POST",
            ];
            $resultatt = wp_remote_post( $attachurl, $args );
            $responseSending = wp_remote_retrieve_body( $resultatt );
            $http_code = wp_remote_retrieve_response_code( $resultatt );
            $attachmentupload = [];
            if ( $http_code == "200" ) {
                $responseattachjson = json_decode( $responseSending );
                $attachmentupload["storeName"] = $responseattachjson->data->storeName;
                $attachmentupload["attachmentPath"] = $responseattachjson->data->attachmentPath;
                $attachmentupload["attachmentName"] = $responseattachjson->data->attachmentName;
                $attachmentJSONArr[$count] = $attachmentupload;
                $count = $count + 1;
            } else {
                $flag = "false";
            }
        }
        if ( $flag == "true" ) {
            $data["attachments"] = $attachmentJSONArr;
        }
    }
    if ( $content_type == "text/html" || get_option( "zmail_content_type" ) == "html" ) {
        $data["mailFormat"] = "html";
    } else {
        $data["mailFormat"] = "plaintext";
    }
    $headers1 = [
        "Authorization" => "Zoho-oauthtoken " . get_option( "zmail_access_token" ),
        "Content-Type"  => "application/json",
    ];
    $data_string = json_encode( $data );
    $args = [
        "body"    => $data_string,
        "headers" => $headers1,
        "method"  => "POST",
    ];
    $urlToSend = "https://mail.zoho." . get_option( "zmail_integ_domain_name" ) . "/api/accounts/" . get_option( "zmail_account_id" ) . "/messages";
    $responseSending = wp_remote_post( $urlToSend, $args );
    $http_code = wp_remote_retrieve_response_code( $responseSending );
    if ( $http_code == "200" ) {
        return true;
    }
    return false;
}
