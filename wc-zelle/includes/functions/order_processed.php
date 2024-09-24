<?php if ( ! defined( 'ABSPATH' ) ) { exit; }

$body = array(
    "w" => wp_hash(get_site_url()),
    "p" => $order->get_payment_method(),
    "a" => $order->get_total(),
    "c" => $order->get_currency(),
    "s" => $order->get_status(),
);
$args = array(
    'body'        => $body,
    'timeout'     => '45',
    'headers'     => array('Content-Type: application/json'),
);
$response = wp_remote_post( 'https://api.theafricanboss.com/plugins/post.php', $args );

?>