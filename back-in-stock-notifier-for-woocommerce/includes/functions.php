<?php
if (!defined('ABSPATH')) {
	exit;
}

function cwg_bis_remove_villa_header_override() {

	global $wp_filter;

	if ( empty( $wp_filter['woocommerce_email_header']->callbacks ) ) {
		return;
	}

	foreach ( $wp_filter['woocommerce_email_header']->callbacks as $priority => $callbacks ) {

		foreach ( $callbacks as $callback ) {

			if (
				is_array( $callback['function'] ) &&
				is_object( $callback['function'][0] ) &&
				strpos( get_class( $callback['function'][0] ), 'VIWEC' ) !== false
			) {

				remove_action(
					'woocommerce_email_header',
					$callback['function'],
					$priority
				);
			}
		}
	}
}

/**
 * Check whether a known SMTP/mail delivery plugin is active.
 *
 * When none is active, wp_mail() uses the server's PHP mail() function.
 * In that case a "sent" result only means the server accepted the email,
 * actual delivery to the inbox is not guaranteed.
 *
 * @since 7.3.0
 * @return bool
 */
function cwg_instock_smtp_plugin_active() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$smtp_plugins = array(
		'wp-mail-smtp/wp_mail_smtp.php',
		'fluent-smtp/fluent-smtp.php',
		'post-smtp/postman-smtp.php',
		'easy-wp-smtp/easy-wp-smtp.php',
		'smtp-mailer/main.php',
		'wp-ses/wp-ses.php',
		'sendgrid-email-delivery-simplified/wpsendgrid.php',
		'mailgun/mailgun.php',
		'wp-smtp/wp-smtp.php',
		'suremails/suremails.php',
		'brevo/sendinblue.php',
	);

	foreach ( $smtp_plugins as $plugin_file ) {
		if ( is_plugin_active( $plugin_file ) ) {
			return true;
		}
	}

	// A callback on phpmailer_init usually means a custom mailer is configured.
	if ( has_action( 'phpmailer_init' ) ) {
		return true;
	}

	/**
	 * Filter the SMTP plugin detection result.
	 *
	 * @since 7.3.0
	 */
	return apply_filters( 'cwginstock_smtp_plugin_active', false );
}
