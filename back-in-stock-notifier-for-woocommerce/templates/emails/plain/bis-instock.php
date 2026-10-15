<?php
/**
 * Back In Stock - Product Available (Plain text)
 *
 * @package BackInStockNotifier/Templates/Emails/Plain
 * @version 7.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plain text email: tags are stripped and HTML entities would show literally.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo '= ' . wp_strip_all_tags( $email_heading ) . " =\n\n";


if ( $additional_content ) {
	echo "---\n\n";
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text email, tags stripped; escaping would break links containing &.
	echo wp_strip_all_tags( wptexturize( $additional_content ) );
	echo "\n\n";
}

echo "\n---\n" . esc_html( $blogname ) . "\n";
