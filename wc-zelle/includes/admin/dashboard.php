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
        null,
        'ZELLE',
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
function wc_zelle_email_receipts_menu_page() {
    require_once WCZELLE_PLUGIN_DIR . 'includes/admin/email-receipts.php';
}

function wc_zelle_recommended_menu_page() {
    require_once WCZELLE_PLUGIN_DIR . 'includes/admin/recommended.php';
}

function wc_zelle_help_menu_page() {
    require_once WCZELLE_PLUGIN_DIR . 'includes/admin/tutorials.php';
}
