<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
function wc_zelle_admin_menu() {
    $parent_slug = 'wc-settings&tab=checkout&section=zelle';
    $capability = 'manage_options';
    global $zelle_fs;
    $account_url = zelle_fs()->get_account_url();
    $contact_url = "mailto:info@theafricanboss.com?subject=WC%20Zelle%20Plugin%20Support&body=Hello%2C%0D%0A%0D%0A";
    $zelle_receipts = admin_url( 'edit.php?post_type=zelle-receipts' );
    $new = ' <sup style="color:#9f9">NEW</sup>';
    $improved = ' <sup style="color: #39b54a; font-weight: bold;">IMPROVED</sup>';
    add_submenu_page(
        'woocommerce',
        'Zelle for Woocommerce',
        'Zelle',
        'manage_woocommerce',
        $parent_slug,
        null
    );
    add_submenu_page(
        'woocommerce',
        'Setup Automated Order Updates',
        'Automated Zelle Order Updates',
        $capability,
        'wc_zelle_automated_status',
        'wc_zelle_email_receipts_menu_page',
        null
    );
    $upgrade_url = zelle_fs()->get_upgrade_url();
    add_menu_page(
        'Zelle',
        'Zelle',
        $capability,
        $parent_slug,
        'wc-zelle-settings',
        'dashicons-money-alt',
        56
    );
    add_submenu_page(
        $parent_slug,
        'Setup Automated Order Updates',
        '<span style="color:#aaffaa">Automated Order Updates</span>',
        $capability,
        'wc_zelle_automated_status',
        'wc_zelle_email_receipts_menu_page',
        null
    );
    add_submenu_page(
        $parent_slug,
        'Zelle Receipts',
        'Receipts',
        $capability,
        $zelle_receipts,
        null,
        null
    );
    add_submenu_page(
        $parent_slug,
        'Upgrade',
        'Upgrade',
        $capability,
        $upgrade_url,
        null,
        null
    );
    add_submenu_page(
        $parent_slug,
        'Account',
        'Account',
        $capability,
        $account_url,
        null,
        null
    );
    add_submenu_page(
        $parent_slug,
        'Feature my store',
        'Get Featured',
        $capability,
        'https://theafricanboss.com/featured',
        null,
        null
    );
    add_submenu_page(
        $parent_slug,
        'Review Checkout with Zelle on Woocommerce',
        'Review',
        $capability,
        'https://wordpress.org/support/plugin/wc-zelle/reviews/?filter=5',
        null,
        null
    );
    add_submenu_page(
        $parent_slug,
        'Our Plugins',
        '<span style="color:yellow">Free Recommended Plugins</span>',
        $capability,
        admin_url( "plugin-install.php?s=theafricanboss&tab=search&type=author" ),
        null,
        null
    );
    add_submenu_page(
        $parent_slug,
        'Recommended Plugins',
        'Recommended Plugins',
        $capability,
        'wc-zelle-recommended',
        'wc_zelle_recommended_menu_page',
        null
    );
    add_submenu_page(
        $parent_slug,
        'FAQ',
        'FAQ',
        $capability,
        'wc-zelle-help',
        'wc_zelle_help_menu_page',
        null
    );
    add_submenu_page(
        $parent_slug,
        'Contact',
        'Support',
        $capability,
        $contact_url,
        null,
        null
    );
}

add_action( 'admin_menu', 'wc_zelle_admin_menu' );
// Zelle orders of the last 30 days, shared by the automation page and the orders notice
function wc_zelle_count_orders() {
    $count_orders = get_transient( 'wc_zelle_count_orders' );
    if ( is_array( $count_orders ) && isset( $count_orders['wait_seconds'] ) ) {
        return $count_orders;
    }
    $count_orders = array(
        'count_all'       => 0,
        'amount'          => 0,
        'count_canceled'  => 0,
        'amount_canceled' => 0,
        'count_paid'      => 0,
        'wait_seconds'    => 0,
    );
    $page = 1;
    do {
        $orders = wc_get_orders( array(
            'type'           => 'shop_order',
            'payment_method' => 'zelle',
            'status'         => array_diff( array_keys( wc_get_order_statuses() ), array('wc-checkout-draft', 'wc-failed', 'wc-refunded') ),
            'date_created'   => '>=' . (time() - MONTH_IN_SECONDS),
            'limit'          => 200,
            'page'           => $page++,
        ) );
        foreach ( $orders as $order ) {
            $count_orders['count_all']++;
            if ( $order->has_status( 'cancelled' ) ) {
                $count_orders['count_canceled']++;
                $count_orders['amount_canceled'] += (float) $order->get_total();
            } else {
                $count_orders['amount'] += (float) $order->get_total();
            }
            if ( $order->is_paid() ) {
                $created = $order->get_date_created();
                $paid = $order->get_date_paid();
                // How long the customer waited between placing the order and it being marked paid
                if ( $created && $paid ) {
                    $count_orders['count_paid']++;
                    $count_orders['wait_seconds'] += max( 0, $paid->getTimestamp() - $created->getTimestamp() );
                }
            }
        }
    } while ( count( $orders ) === 200 );
    $count_orders['amount'] = wc_format_decimal( $count_orders['amount'], 2 );
    $count_orders['amount_canceled'] = wc_format_decimal( $count_orders['amount_canceled'], 2 );
    set_transient( 'wc_zelle_count_orders', $count_orders, 3 * DAY_IN_SECONDS );
    return $count_orders;
}

require_once WCZELLE_PLUGIN_DIR . 'includes/admin/orders-notice.php';
function wc_zelle_email_receipts_menu_page() {
    require_once WCZELLE_PLUGIN_DIR . 'includes/admin/email-receipts.php';
}

function wc_zelle_recommended_menu_page() {
    require_once WCZELLE_PLUGIN_DIR . 'includes/admin/recommended.php';
}

function wc_zelle_help_menu_page() {
    require_once WCZELLE_PLUGIN_DIR . 'includes/admin/tutorials.php';
}
