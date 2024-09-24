=== Checkout with Zelle on Woocommerce ===
Contributors: theafricanboss, freemius
Donate Link: https://gurastores.com/get-cash
Tags: zelle,bank transfer,woocommerce,payments,money transfer
Stable tag: 4.0
Requires PHP: 5.0
Requires at least: 5.0
Tested up to: 6.5.4
WC requires at least: 6.0.0
WC tested up to: 9.0.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

The top finance app in the App Store now available on WordPress. Receive Zelle payments on your website with WooCommerce + Zelle

== Description ==

Checkout with Zelle on Woocommerce is a plugin that allows you to receive Zelle payments on your website with WooCommerce.
**Unlock more great features for you and your customers and priority support with a PRO license. [Upgrade](https://theafricanboss.com/zelle)**

= More information =

For more details about this woocommerce extension, **please visit [The African Boss](https://theafricanboss.com/zelle)**
See available screenshots or the store example of [Gura Stores](https://gurastores.com/test/) for visual details

== Screenshots ==

1. Checkout page view for customers using the Zelle payment method enabled.
2. Plugin settings for the Zelle payment method and this information will be displayed to your customers
3. Thank you page after placing the order via the Zelle payment method
4. This is where the Zelle link/QR code brings the customer with a prefilled order information

== Installation ==

= From Dashboard ( WordPress admin ) =

- Go to the "Plugins" screen, click "Add New", and search for "Checkout with Zelle on Woocommerce" in the WordPress Plugin Directory.
- Click "Install Now" and after that's complete, click "Activate".

= Getting a downloaded zip =

- Download ‘Checkout with Zelle on Woocommerce’ from either:
+ [The African Boss for the PRO version](https://theafricanboss.com/zelle)
+ [Wordpress for the free version](https://downloads.wordpress.org/plugin/wc-zelle.zip)

= Uploading a downloaded zip in the Dashboard ( WordPress admin ) =

- Go to the "Plugins" screen and click "Add New".
- Click "Upload Plugin" and select the downloaded zip file.
- Click "Install Now" and after that's complete, click "Activate".

= Using cPanel or FTP =

- Unzip ‘wc-zelle.zip’ file and
- Upload wc-zelle folder to the “/wp-content/plugins/” directory.
- Activate the plugin through the “Plugins” menu in WordPress.

= After Plugin Activation =

Find and click Zelle in your admin dashboard left sidebar to access Zelle settings

**or**

Go to ‘Woocommerce > Settings > Payments’ screen to configure the plugin

Also _you can visit_ the [plugin page](https://theafricanboss.com/zelle) for further setup instructions.

== Frequently Asked Questions ==

= Does this Zelle plugin integrate with the Zelle payment APIs? =

This plugin is a quick and easy way to display to your customers your Zelle information.
Unfortunately, this plugin doesn't integrate with your bank or the Zelle app as an end-to-end payment.
It only displays your Zelle information to the customer so that the customer can know how to proceed in their Zelle/bank app.

= What is emailreceipts.io and do I need to integrate it? =

emailreceipts.io is a service that allows you to track Zelle payments and update order statuses automatically.
The integration also helps send automated emails to your customers when their order status changes.
If you do not want to use emailreceipts.io, you will have to manually update the order status in your Woocommerce admin dashboard.

= Support =

**Premium Support**

Users with a valid Checkout with Zelle on Woocommerce PRO license receive Priority Support, directly from the plugin developer! [Find out more!](https://theafricanboss.com/zelle)

**Community Support for users of the Free version**

For support questions, bug reports, or feature requests, please use the [WordPress Support Forums](https://wordpress.org/support/plugin/wc-zelle). Please search through the forums first, and only [create a new topic](https://wordpress.org/support/plugin/wc-zelle#new-post) if you don't find an existing answer. Thank you!

= Languages and Localization =

Also compatible with Translation plugins (like Loco, WPML, etc) meaning you can translate the Checkout, Thank you page and Email notices

= SMS for Woocommerce compatibility =

Also using our SMS for Woocommerce plugin, you can send personalized bulk email and SMS notifications for orders still on-hold with order information and more

== Usage ==

After activating the plugin, add your Zelle information such as your Zelle name, Zelle email, Zelle phone number in the plugin settings to start receiving payments instantly.

**Unlock more great features for you and your customers and priority support with a PRO license. [Upgrade](https://theafricanboss.com/zelle)**

== Upgrade Notice ==

= 4.0 =
This update is a major upgrade, enhancement, compatibility release. Updating is highly recommended.

= 3.1.2 =
This update is a feature, enhancement, compatibility, maintenance and security release. Updating is highly recommended.

== Changelog ==

= 4.0 Jun 15, 2024 =
- Fixed enroll.zellepay.com QR code matching issue: wc_zelle_qrcode_url
- HPOS compatibility: before_woocommerce_init, fixed functions, and variables
- WooCommerce Blocks support: woocommerce_blocks_loaded with zelle_woocommerce_blocks_support
- Replaced the deprecated googleapis QR code
- checkout_html, thankyou_html, email_html variables
- Introduced qrcode_styling to the free version
- Updated Freemius, Woocommerce and Wordpress compatibility

= 3.1.2 Nov 15, 2023 =
- Refactored code across plugin for better performance
- Better description for the Zelle QR code settings
- Refactored zelle_url,qr_code_url,qr_code
- $zelle_sender to $accountid_meta
- reduce_order_stock to wc_reduce_stock_levels
- Better woocommerce_zelle_process_payment_order_status
- Updated Freemius, Woocommerce and Wordpress compatibility

= 3.1-3.1.1 Jun 15 - Jul 1, 2023 =
- Added Zelle QR Code settings, design and styling options
- Implemented zelle_url,qr_code_url,qr_code
- Implemented qr code decoder vs enroll.zelle vs original qr code
- Refactored code across plugin for better performance
- Updated the emailreceipts.io integration to process orders after verification of the request
- Updated the find order function to include an object with order, post_content, receipt_post_id
- Updated the update order file to include update_order, post_title, post_content, response_code, message_array
- Replaced reduce_order_stock to wc_reduce_stock_levels
- Moved pluggable to plugins_loaded: replaced current_user_can with is_admin before plugins_loaded
- Added to the thank you page and email note: order_id,zelle_notice
- Using wp_kses_post wpautop wptexturize functions accordingly
- Tutorial on how to update order statuses automatically for PRO users
- New plugin logo with Zelle logo and QR code in one
- Integrated debug logs and added them to validate_fields,process_payment,...
- Better variables and functions
- Freemius SDK update to 2.5.10
- Updated Woocommerce and Wordpress compatibility

= 3.0 May 1, 2023 =
- New redesigned and enhanced checkout and thank you page designs for PRO users
- Automated order status processing with emailreceipts.io
- Integrated emailreceipts.io to track Zelle receipts and update order statuses automatically
- emailreceipts.io onboarding for free users
- When no order id is submitted, search through the 5 most recent Zelle orders on-hold and match by amount or customer Zelle information
- Added capturing Zelle information as order meta data for PRO users
- Reduce order stock inventory settings option
- Added copy.js and qrcode.js
- Better thank you page details with CSS and the order number
- Updated assets and screenshots
- Improved and added Zelle menu buttons in the admin dashboard
- Improved customer email
- Edit order note in PRO
- display default plugin settings values
- Better enqueued scripts
- Updated Freemius, Woocommerce and Wordpress compatibility

= 2.1 Oct 15, 2022 =
- Do not show empty Zelle info
- Enable/Disable note
- Better order note & note signature
- SMS admin notice removed from PRO
- function wc_zelle_ prefix
- Updated assets
- Updated Woocommerce, WordPress, and Freemius compatibility

= 2.0 Mar 15, 2022 =
- SMS for Woocommerce compatible
- Internalization of the plugin checkout, thankyou and email
- Freemius update to 2.4.3

= 1.1 Jan 15, 2022 =
- Email note updated to include Phone number, Email address and Zelle Name
- Zoho Mail Integration | includes/functions/mail.php
- FAQ section updated
- Screenshots updated

= 1.0 Dec 15, 2021 =
- Initial Release

<?php code();?>
