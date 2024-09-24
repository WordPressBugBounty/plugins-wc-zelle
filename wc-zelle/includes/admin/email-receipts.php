<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
global $current_user;
$user_meta = get_user_meta( $current_user->ID );
$gateway = new WC_Zelle_Gateway();
global $zelle_fs;
$upgrade_url = zelle_fs()->get_upgrade_url();
$connect_button_text = 'Connect ' . get_bloginfo( 'name' ) . ' to emailreceipts.io';
$upgrade = null;
$connect_button_text .= ' - LIMITED FEATURE FOR FREE USERS';
$upgrade = 'As a free user, you are limited on how many orders get processed. <a href="' . $upgrade_url . '">Upgrade for more automation and so much more</a><br>Once upgraded, reconnect again to update emailreceipts.io systems';
?>

<section>
    <div class="container">
        <div class="text-white bg-success border rounded border-0 p-4 py-5">
            <div class="row">
                <div class="col-md-10 col-xl-8 text-center d-flex d-sm-flex d-md-flex justify-content-center align-items-center mx-auto justify-content-md-start align-items-md-center justify-content-xl-center">
                    <div>
                        <h2 class="fw-bold text-white mb-3">Easily extract <?php 
echo wp_kses_post( $gateway->title );
?> transaction information from your email receipts to automatically update the order status<br>from on-hold -> processing/complete<br>for physical or digital products accordingly</h2>
                        <p class="mb-4">Anytime you receive a Zelle payment, emailreceipts.io will extract the order number and payment information</p>
                        <p class="mb-4">Once the information has been extracted, it will be sent to <?php 
echo get_bloginfo( 'name' );
?></p>
                        <p class="mb-4"><?php 
echo get_bloginfo( 'name' );
?> will then search for the corresponding order, confirm the amount and if everything is accurate, the order will be updated from on-hold -> processing for physical orders and on-hold -> complete for digital orders</p>
                        <p class="mb-4">Also regular notifications to your customer will be sent accordingly</p>

                        <p class="mb-4">Say goodbye to the time spent manually updating inventory and doing status updates from now on.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="text-center p-4 py-5">
            <h2 class="mb-3">Zelle Institution Information</h2>
            <p class="text-danger"><strong>*** This is the information of the institution where you receive/send Zelle payments ***</strong></label>
            <p class="text-danger"><strong>Only Transactions that originate from this institution will be used to process your orders</strong></label>
            <form class="my-4" id="store-connect-form" name="store-connect-form" action="https://emailreceipts.io/store/connect?ref=<?php 
echo urlencode( WCZELLE_PLUGIN_SLUG );
?>"
                method="POST" target="_blank" class="main-content">

                <input type="hidden" name="accountid"
                    placeholder="Financial Account ID" id="accountid" value="<?php 
echo wp_kses_post( $gateway->ReceiverZELLEEmail );
?>" />

                <div class="mb-3">
                    <label for="accountname"><strong><?php 
echo wp_kses_post( $gateway->method_title );
?> Account Name <span class="text-danger">*</span></strong> (extracted from your plugin settings)</label>
                    <input type="text" class="form-control readonly" name="accountname" readonly required
                        placeholder="Set up the <?php 
echo wp_kses_post( $gateway->method_title );
?> Name in your settings" id="accountname" value="<?php 
echo wp_kses_post( $gateway->ReceiverZelleOwner );
?>" />
                </div>

                <div class="mb-3">
                    <label for="accountemail"><strong><?php 
echo wp_kses_post( $gateway->method_title );
?> Account Email <span class="text-danger">*</span></strong> (extracted from your plugin settings)</label>
                    <input type="text" class="form-control readonly" name="accountemail" readonly required
                        placeholder="Set up the <?php 
echo wp_kses_post( $gateway->method_title );
?> Email in your settings" id="accountemail" value="<?php 
echo wp_kses_post( $gateway->ReceiverZELLEEmail );
?>" />
                </div>

                <div class="mb-3">
                    <label for="institutionname"><strong>Financial Institution Name</strong> you use to send/receive Zelle payments (this is used to confirm that payments came from your institution)</label>
                    <input type="text" class="form-control" name="institutionname" id="institutionname" placeholder="America Bank" required>
                </div>
                <div class="mb-3">
                    <label for="institutionemail"><strong>Financial Institution Email</strong> that the institution above uses to send you Zelle payment notifications (this is used to confirm that payments came from your institution)</label>
                    <p>Check your most recent Zelle email notification you received and get the sender's email address which should be your Zelle financial institution email</p>
                    <input type="email" class="form-control" name="institutionemail" id="institutionemail" placeholder="payments@americabank.com" required>
                </div>

                <input type="hidden" name="fname" placeholder="First Name"
                    id="fname" value="<?php 
echo wp_kses_post( get_user_meta( $current_user->ID, 'first_name', true ) );
?>" />

                <input type="hidden" name="lname" placeholder="Last Name"
                    id="lname" value="<?php 
echo wp_kses_post( get_user_meta( $current_user->ID, 'last_name', true ) );
?>" />

                <input type="hidden" name="email" placeholder="Email"
                id="email" value="<?php 
echo wp_kses_post( get_bloginfo( "admin_email" ) );
?>" />

                <input type="hidden" name="phone" placeholder="Phone Number"
                    id="phone" value="<?php 
echo wp_kses_post( get_user_meta( $current_user->ID, 'billing_phone', true ) );
?>" />

                <input type="hidden" name="name"
                    placeholder="Store Name" id="name" value="<?php 
echo wp_kses_post( get_bloginfo( "name" ) );
?>" />

                <input type="hidden" name="domain"
                    placeholder="Store Domain" id="domain" value="<?php 
echo wp_kses_post( get_site_url() );
?>" />

                <input type="hidden" name="thumbnailUrl"
                    placeholder="Store Logo" id="thumbnailUrl" value="<?php 
echo wp_kses_post( get_site_icon_url() );
?>" />

                <input type="hidden" name="institution"
                    placeholder="Financial Institution" id="institution" value="<?php 
echo wp_kses_post( "Zelle" );
?>" />

                <input type="hidden" name="webhook"
                    placeholder="Webhook URL" id="webhook" value="<?php 
echo wp_kses_post( $gateway->ZelleForwardingURL );
?>" />

                <input type="hidden" name="extension" id="extension" value="<?php 
echo WCZELLE_PLUGIN_SLUG;
?>" />
                <input type="hidden" name="key" id="key" />

                <?php 
echo wp_nonce_field( 'connect_store_to_emailreceipts' );
?>

                <input type="submit" value="<?php 
echo $connect_button_text;
?>" data-wait="Please wait..." class="btn btn-primary btn-lg fs-5 py-2 px-4" />
            </form>

            <p class="mb-4"><?php 
echo $upgrade;
?></p>
        </div>
    </div>
</section>

<script>
    document.querySelector('form').addEventListener('submit', function(e) {
        var count = 0;
        var readonlys = document.getElementsByClassName('readonly');
        for (var i = 0; i < readonlys.length; i++) {
            var readonly = readonlys[i]?.value?.trim();
            // console.log(readonlys[i], readonly);
            if ( !readonly ) count++;
        }
        if (count > 0) {
            e.preventDefault();
            alert('Please set up the Zelle Name and Email in your plugin settings before you proceed');
        }
    });
</script>