<?php
/**
 * Back In Stock - Subscription Confirmation (HTML)
 *
 * This template can be overridden by copying it to
 * yourtheme/woocommerce/emails/bis-subscription.php
 *
 * @package BackInStockNotifier/Templates/Emails
 * @version 7.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); 

/**
 * Show additional content defined via WooCommerce email settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
