<?php
/**
 * Back In Stock - Subscription Confirmation (Plain text)
 *
 * @package BackInStockNotifier/Templates/Emails/Plain
 * @version 7.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo '= ' . wp_strip_all_tags( $email_heading ) . " =\n\n";

if ( $additional_content ) {
	echo "---\n\n";
	echo wp_strip_all_tags( wptexturize( $additional_content ) );
	echo "\n\n";
}

echo "\n---\n" . esc_html( $blogname ) . "\n";
