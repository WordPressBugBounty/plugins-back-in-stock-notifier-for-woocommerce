<?php
/**
 * Back In Stock - Product Available (HTML)
 *
 * This template can be overridden by copying it to
 * yourtheme/woocommerce/emails/bis-instock.php
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

if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
