<?php
if(!defined('ABSPATH')) {
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