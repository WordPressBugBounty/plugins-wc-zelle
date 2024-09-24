<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
$update_order = null;
$post_title = null;
$post_content = null;
$response_code = null;
global $zelle_fs;
$update_order .= "UPGRADE TO UNLOCK AUTOMATED ORDER PROCESSING\n";
$post_title .= wp_kses_post( " - UPGRADE TO AUTOMATE FURTHER" );
$post_content .= wp_kses_post( " - Upgrade to automated further." );
$response_code = 426;
$message .= $update_order;
$message_array['update_order'] = $update_order;
if ( $receipt_post_id ) {
    $post_dump = print_r( $body, true );
    $receipt_post = get_post( $receipt_post_id );
    if ( $receipt_post ) {
        $receipt_post->post_title .= wp_kses_post( $post_title );
        $receipt_post->post_content .= wp_kses_post( "<br>{$post_content}<br><br>{$email_subject}<br><br>{$post_dump}" );
        wp_update_post( $receipt_post );
    }
}
http_response_code( $response_code );