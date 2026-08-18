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

/**
 * Available inline subscribe form designs.
 *
 * @since 7.4.0
 * @return array design key => label
 */
function cwg_instock_get_form_designs() {
	return apply_filters(
		'cwginstock_form_designs',
		array(
			'default' => __( 'Default (classic)', 'back-in-stock-notifier-for-woocommerce' ),
			'card'    => __( 'Card', 'back-in-stock-notifier-for-woocommerce' ),
			'minimal' => __( 'Minimal', 'back-in-stock-notifier-for-woocommerce' ),
			'compact' => __( 'Compact (single row)', 'back-in-stock-notifier-for-woocommerce' ),
			'bold'    => __( 'Bold', 'back-in-stock-notifier-for-woocommerce' ),
			'outline' => __( 'Outline', 'back-in-stock-notifier-for-woocommerce' ),
		)
	);
}

/**
 * Currently selected inline form design key.
 *
 * @since 7.4.0
 * @return string
 */
function cwg_instock_get_form_design() {
	$options = get_option( 'cwginstocksettings', array() );
	$design  = isset( $options['form_design'] ) && '' !== $options['form_design'] ? $options['form_design'] : 'default';
	$designs = cwg_instock_get_form_designs();
	if ( ! isset( $designs[ $design ] ) ) {
		$design = 'default';
	}
	return apply_filters( 'cwginstock_form_design', $design );
}

/**
 * Wrapper class for the selected inline design. Default returns an empty
 * string so existing installs render exactly as before.
 *
 * @since 7.4.0
 * @return string
 */
function cwg_instock_get_form_design_class() {
	$design = cwg_instock_get_form_design();
	return 'default' === $design ? '' : 'cwg-design cwg-design-' . sanitize_html_class( $design );
}

/**
 * Available popup designs.
 *
 * @since 7.4.0
 * @return array design key => label
 */
function cwg_instock_get_popup_designs() {
	return apply_filters(
		'cwginstock_popup_designs',
		array(
			'sweetalert'    => __( 'SweetAlert (default)', 'back-in-stock-notifier-for-woocommerce' ),
			'modal_center'  => __( 'Centered Modal', 'back-in-stock-notifier-for-woocommerce' ),
			'modal_slide'   => __( 'Slide Up (mobile friendly)', 'back-in-stock-notifier-for-woocommerce' ),
			'modal_side'    => __( 'Side Drawer', 'back-in-stock-notifier-for-woocommerce' ),
			'modal_minimal' => __( 'Minimal Modal', 'back-in-stock-notifier-for-woocommerce' ),
			'modal_card'    => __( 'Card Modal', 'back-in-stock-notifier-for-woocommerce' ),
		)
	);
}

/**
 * Currently selected popup design key.
 *
 * @since 7.4.0
 * @return string
 */
function cwg_instock_get_popup_design() {
	$options = get_option( 'cwginstocksettings', array() );
	$design  = isset( $options['popup_design'] ) && '' !== $options['popup_design'] ? $options['popup_design'] : 'sweetalert';
	$designs = cwg_instock_get_popup_designs();
	if ( ! isset( $designs[ $design ] ) ) {
		$design = 'sweetalert';
	}
	return apply_filters( 'cwginstock_popup_design', $design );
}

/**
 * Is the current request a WooCommerce email preview?
 *
 * @since 7.4.0
 * @return bool
 */
function cwg_instock_is_email_preview() {
	return (bool) apply_filters( 'woocommerce_is_email_preview', false );
}

/**
 * Build a premium looking product card table for the emails.
 *
 * Uses nested tables and inline styles only, so it renders consistently in
 * Outlook, Gmail and Apple Mail. Progressive touches such as rounded corners
 * degrade gracefully in clients that ignore them.
 *
 * @since 7.4.0
 * @param int  $subscriber_id Subscriber post id.
 * @param bool $preview       Render sample data for the email preview.
 * @return string
 */
function cwg_instock_get_product_table_html( $subscriber_id, $preview = false ) {
	$rtl   = is_rtl();
	$align = $rtl ? 'right' : 'left';
	$opp   = $rtl ? 'left' : 'right';
	$font  = "'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif";

	// Match the WooCommerce order table look. The td class lets WooCommerce's
	// own email styles apply, and the inline styles are the same values so it
	// still looks right in clients that do not get the inlined CSS.
	$border   = '1px solid #e5e5e5';
	$cell     = 'border:' . $border . ';padding:12px;vertical-align:middle;font-family:' . $font . ';font-size:14px;color:#636363;';
	$head     = 'border:' . $border . ';padding:12px;vertical-align:middle;font-family:' . $font . ';font-size:14px;color:#636363;font-weight:bold;';

	if ( $preview ) {
		$name     = __( 'Sample Product', 'back-in-stock-notifier-for-woocommerce' );
		$link     = home_url( '/' );
		$image    = '';
		$price    = function_exists( 'wc_price' ) ? wc_price( 29 ) : '29';
		$sku      = 'SKU-1234';
		$quantity = 1;
	} else {
		$subscriber_id = absint( $subscriber_id );
		if ( ! $subscriber_id || ! class_exists( 'CWG_Instock_API' ) ) {
			return '';
		}
		$api = new CWG_Instock_API();

		$pid = get_post_meta( $subscriber_id, 'cwginstock_bypass_pid', true );
		if ( ! $pid ) {
			$pid = get_post_meta( $subscriber_id, 'cwginstock_pid', true );
		}
		$product = $pid ? wc_get_product( $pid ) : null;
		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		$name     = wp_strip_all_tags( $api->display_product_name( $subscriber_id ) );
		$link     = $api->display_product_link( $subscriber_id );
		$image    = $api->get_product_image( $subscriber_id, array( 48, 48 ) );
		$price    = $api->get_product_price( $subscriber_id );
		$sku      = $api->get_product_sku( $subscriber_id );
		$quantity = (int) get_post_meta( $subscriber_id, 'cwginstock_custom_quantity', true );
		$quantity = $quantity > 0 ? $quantity : 1;
	}

	ob_start();
	?>
	<table class="td" cellspacing="0" cellpadding="6" border="1" style="width:100%;border:<?php echo esc_attr( $border ); ?>;border-collapse:collapse;font-family:<?php echo esc_attr( $font ); ?>;margin-bottom:24px;">
		<thead>
			<tr>
				<th class="td" scope="col" style="<?php echo esc_attr( $head ); ?>text-align:<?php echo esc_attr( $align ); ?>;"><?php esc_html_e( 'Product', 'back-in-stock-notifier-for-woocommerce' ); ?></th>
				<th class="td" scope="col" style="<?php echo esc_attr( $head ); ?>text-align:<?php echo esc_attr( $align ); ?>;"><?php esc_html_e( 'Quantity', 'back-in-stock-notifier-for-woocommerce' ); ?></th>
				<th class="td" scope="col" style="<?php echo esc_attr( $head ); ?>text-align:<?php echo esc_attr( $align ); ?>;"><?php esc_html_e( 'Price', 'back-in-stock-notifier-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr class="order_item">
				<td class="td" style="<?php echo esc_attr( $cell ); ?>text-align:<?php echo esc_attr( $align ); ?>;word-wrap:break-word;">
					<?php if ( $image ) : ?>
						<span style="margin-<?php echo esc_attr( $opp ); ?>:10px;display:inline-block;vertical-align:middle;line-height:0;">
							<?php echo wp_kses_post( $image ); ?>
						</span>
					<?php endif; ?>
					<span style="display:inline-block;vertical-align:middle;">
						<?php if ( $link ) : ?>
							<a href="<?php echo esc_url( $link ); ?>" style="color:#636363;text-decoration:underline;font-family:<?php echo esc_attr( $font ); ?>;"><?php echo esc_html( $name ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $name ); ?>
						<?php endif; ?>
						<?php if ( $sku ) : ?>
							<br><small style="color:#767676;"><?php echo esc_html( sprintf( /* translators: %s: product sku */ __( 'SKU: %s', 'back-in-stock-notifier-for-woocommerce' ), $sku ) ); ?></small>
						<?php endif; ?>
					</span>
				</td>
				<td class="td" style="<?php echo esc_attr( $cell ); ?>text-align:<?php echo esc_attr( $align ); ?>;"><?php echo esc_html( $quantity ); ?></td>
				<td class="td" style="<?php echo esc_attr( $cell ); ?>text-align:<?php echo esc_attr( $align ); ?>;"><?php echo wp_kses_post( $price ); ?></td>
			</tr>
		</tbody>
	</table>
	<?php
	$html = ob_get_clean();

	/**
	 * Filter the product table used in the emails.
	 *
	 * @since 7.4.0
	 */
	return apply_filters( 'cwginstock_product_table_html', $html, $subscriber_id, $preview );
}
