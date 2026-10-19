<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
add_action( 'admin_init', function () {
    if ( empty( $_GET['wc_zelle_snooze_orders_notice'] ) || !current_user_can( 'manage_woocommerce' ) ) {
        return;
    }
    check_admin_referer( 'wc_zelle_snooze_orders_notice' );
    // Dismiss on the orders notice hides it for two weeks for that admin
    update_user_meta( get_current_user_id(), 'wc_zelle_orders_notice_snoozed', time() + 2 * WEEK_IN_SECONDS );
    wp_safe_redirect( remove_query_arg( array('wc_zelle_snooze_orders_notice', '_wpnonce') ) );
    exit;
} );
add_action( 'admin_enqueue_scripts', function () {
    $screen = get_current_screen();
    if ( $screen && in_array( $screen->id, array('edit-shop_order', 'woocommerce_page_wc-orders'), true ) && function_exists( 'fs_enqueue_local_style' ) ) {
        fs_enqueue_local_style( 'fs_common', '/admin/common.css' );
    }
} );
add_action( 'admin_notices', function () {
    if ( !current_user_can( 'manage_woocommerce' ) ) {
        return;
    }
    global $zelle_fs;
    $screen = get_current_screen();
    if ( !$screen || !in_array( $screen->id, array('edit-shop_order', 'woocommerce_page_wc-orders'), true ) ) {
        return;
    }
    if ( isset( $_GET['action'] ) && in_array( $_GET['action'], array('edit', 'new'), true ) ) {
        return;
    }
    if ( (int) get_user_meta( get_current_user_id(), 'wc_zelle_orders_notice_snoozed', true ) > time() ) {
        return;
    }
    $paid_orders = (int) wc_zelle_count_orders()['count_all'];
    if ( $paid_orders < 1 ) {
        return;
    }
    $on_hold = get_transient( 'wc_zelle_onhold_orders' );
    if ( !is_array( $on_hold ) || !isset( $on_hold['count'], $on_hold['amount'] ) ) {
        $orders = wc_get_orders( array(
            'type'           => 'shop_order',
            'payment_method' => 'zelle',
            'status'         => 'on-hold',
            'limit'          => 250,
        ) );
        $on_hold = array(
            'count'  => count( $orders ),
            'amount' => 0,
        );
        foreach ( $orders as $order ) {
            $on_hold['amount'] += (float) $order->get_total();
        }
        set_transient( 'wc_zelle_onhold_orders', $on_hold, 3 * DAY_IN_SECONDS );
    }
    $onhold_count = (int) $on_hold['count'];
    $onhold_amount = (float) $on_hold['amount'];
    // Same estimates as the automation page: 8 min per manual match, 1 in 3 customers following up
    $hours = (int) floor( $paid_orders * 8 / 60 );
    $time_back = ( $hours < 1 ? $paid_orders * 8 . ' min' : $hours . '+ ' . (( 1 === $hours ? 'hr' : 'hrs' )) );
    $followups = (int) ceil( $paid_orders / 3 );
    $headline = ( $onhold_count > 0 ? sprintf(
        '%d Zelle %s on hold right now: %s waiting for you to find the payment.',
        $onhold_count,
        ( 1 === $onhold_count ? 'order is' : 'orders are' ),
        wp_strip_all_tags( wc_price( $onhold_amount, array(
            'decimals' => 0,
        ) ) )
    ) : sprintf(
        'In the last 30 days, %d %s paying with Zelle waited ~%d+ %s for manual confirmation.',
        $paid_orders,
        ( 1 === $paid_orders ? 'customer' : 'customers' ),
        $paid_orders,
        ( 1 === $paid_orders ? 'hr' : 'hrs' )
    ) );
    $pitch = sprintf( 'Upgrading to PRO + emailreceipts.io confirms Zelle payments in under a minute, 24/7. That is %s of your time back per month and %d+ avoided “Did you get my payment?” tickets/emails/calls/messages.', $time_back, $followups );
    $snooze_url = wp_nonce_url( add_query_arg( 'wc_zelle_snooze_orders_notice', 1 ), 'wc_zelle_snooze_orders_notice' );
    ?>
	<div class="fs-notice notice notice-warning fs-has-title">
		<label class="fs-plugin-title"><?php 
    echo esc_html( zelle_fs()->get_plugin_name() );
    ?></label>
		<a class="fs-close" href="<?php 
    echo esc_url( $snooze_url );
    ?>" style="text-decoration: none;">
			<i class="dashicons dashicons-no" title="Dismiss"></i>
			<span>Dismiss</span>
		</a>
		<div class="fs-notice-body">
			<strong><?php 
    echo esc_html( $headline );
    ?></strong><br><?php 
    echo esc_html( $pitch );
    ?>
			<div style="margin-top: 8px;">
				<a class="button button-primary" href="<?php 
    echo esc_url( zelle_fs()->get_upgrade_url() );
    ?>">Upgrade to PRO + Automate my Zelle order confirmations</a>
				<a class="button" href="<?php 
    echo esc_url( admin_url( 'admin.php?page=wc_zelle_automated_status' ) );
    ?>">Connect to emailreceipts.io</a>
			</div>
		</div>
	</div>
	<?php 
} );