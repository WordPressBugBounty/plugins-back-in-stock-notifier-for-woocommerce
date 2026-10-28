<?php
if (!defined('ABSPATH')) {
	exit;
}

// Public function, class, hook and template variable names below are part of the
// plugin's published API (themes and add-ons use them), so they keep their names.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

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
		$link     = cwg_instock_add_utm_params( $api->display_product_link( $subscriber_id ), 'product_table' );
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

/**
 * Blend a hex colour with white to get a soft tint.
 *
 * The coupon panel uses a tint of the store's email colour rather than a fixed
 * shade, so it suits a black, blue or purple email palette equally.
 *
 * @since 7.4.5
 * @param string $hex    Hex colour, with or without the leading hash.
 * @param float  $weight How much white to mix in, 0 to 1.
 * @return string Hex colour.
 */
function cwg_instock_tint_colour( $hex, $weight = 0.94 ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
		return '#f7f7f7';
	}

	$weight   = min( 1, max( 0, (float) $weight ) );
	$out      = '#';
	$channels = array();
	foreach ( array( 0, 2, 4 ) as $offset ) {
		$channel    = hexdec( substr( $hex, $offset, 2 ) );
		$mixed      = (int) round( $channel + ( 255 - $channel ) * $weight );
		$mixed      = max( 0, min( 255, $mixed ) );
		$channels[] = $mixed;
		$out       .= str_pad( dechex( $mixed ), 2, '0', STR_PAD_LEFT );
	}

	// A very light store colour tints to near white, which would leave the panel
	// invisible against the email background. Fall back to a neutral grey.
	if ( min( $channels ) > 249 ) {
		return '#f7f7f7';
	}

	return $out;
}

/**
 * The site's own brand colour, read from the active theme.
 *
 * Block themes expose a palette through theme.json, and classic themes commonly
 * store one in the customizer. Used when the coupon block is set to follow the
 * website colour.
 *
 * @since 7.4.5
 * @return string Hex colour, empty when the theme exposes none.
 */
function cwg_instock_get_site_colour() {
	$colour = '';

	// Block themes: the theme.json palette.
	if ( function_exists( 'wp_get_global_settings' ) ) {
		$palette = wp_get_global_settings( array( 'color', 'palette' ) );
		$entries = array();
		// Only the theme's own palette and the user's custom colours count. The
		// default WordPress palette is not a brand colour.
		foreach ( array( 'custom', 'theme' ) as $origin ) {
			if ( ! empty( $palette[ $origin ] ) && is_array( $palette[ $origin ] ) ) {
				$entries = array_merge( $entries, $palette[ $origin ] );
			}
		}
		// Prefer the slug a theme would use for its brand colour.
		foreach ( array( 'primary', 'accent', 'brand', 'secondary', 'contrast' ) as $slug ) {
			foreach ( $entries as $entry ) {
				if ( isset( $entry['slug'], $entry['color'] ) && $slug === $entry['slug'] ) {
					$colour = $entry['color'];
					break 2;
				}
			}
		}
	}

	// Classic themes: common customizer settings.
	if ( '' === $colour ) {
		foreach ( array( 'accent_color', 'primary_color', 'link_color', 'header_textcolor' ) as $mod ) {
			$value = get_theme_mod( $mod );
			if ( is_string( $value ) && '' !== $value && 'blank' !== $value ) {
				$colour = '#' === substr( $value, 0, 1 ) ? $value : '#' . $value;
				break;
			}
		}
	}

	$colour = is_string( $colour ) ? trim( $colour ) : '';

	// Only plain hex is usable in an email, so ignore CSS variables and such.
	if ( '' !== $colour && ! preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $colour ) ) {
		$colour = '';
	}

	/**
	 * Filter the detected site colour.
	 *
	 * @since 7.4.5
	 */
	return (string) apply_filters( 'cwginstock_site_colour', $colour );
}

/**
 * Colours for the coupon block, from the Auto Coupon settings.
 *
 * @since 7.4.5
 * @return array {
 *     @type string $accent Accent colour for the heading, border and button.
 *     @type string $panel  Panel background.
 *     @type string $button Button text colour, chosen for contrast.
 * }
 */
function cwg_instock_get_coupon_box_colours() {
	$options = get_option( 'cwginstocksettings', array() );
	$mode    = isset( $options['coupon_box_color_mode'] ) ? $options['coupon_box_color_mode'] : 'email';
	$fallback = '#7f54b3';

	switch ( $mode ) {
		case 'site':
			$accent = cwg_instock_get_site_colour();
			break;

		case 'custom':
			$accent = isset( $options['coupon_box_accent'] ) ? $options['coupon_box_accent'] : '';
			break;

		case 'email':
		default:
			$accent = get_option( 'woocommerce_email_base_color', $fallback );
			break;
	}

	$accent = is_string( $accent ) ? trim( $accent ) : '';
	if ( ! preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $accent ) ) {
		// Chosen source gave nothing usable, so fall back to the email colour.
		$accent = get_option( 'woocommerce_email_base_color', $fallback );
		if ( ! preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', (string) $accent ) ) {
			$accent = $fallback;
		}
	}

	$panel = isset( $options['coupon_box_panel'] ) ? trim( (string) $options['coupon_box_panel'] ) : '';
	if ( ! preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $panel ) ) {
		$panel = cwg_instock_tint_colour( $accent );
	}

	// Keep the button label readable whatever the accent is.
	$button = function_exists( 'wc_hex_is_light' ) && wc_hex_is_light( $accent ) ? '#2b2b2b' : '#ffffff';

	/**
	 * Filter the coupon block colours.
	 *
	 * @since 7.4.5
	 */
	return apply_filters(
		'cwginstock_coupon_box_colours',
		array(
			'accent' => $accent,
			'panel'  => $panel,
			'button' => $button,
		)
	);
}
/**
 * Ready made coupon block for the back in stock email.
 *
 * Gives store owners a presentable coupon section from a single placeholder,
 * so no HTML knowledge is needed to show a discount clearly. Built from tables
 * with inline styles, which is what email clients reliably support, and it
 * picks up the WooCommerce email base colour so it matches the rest of the
 * email.
 *
 * @since 7.4.5
 * @param string $code    Coupon code.
 * @param string $amount  Formatted discount, for example 10% or $10.
 * @param string $expiry  Formatted expiry date, empty when the coupon does not expire.
 * @param string $link    Optional shop link for the button.
 * @param bool   $preview True when rendering sample content for the email preview.
 * @return string
 */
function cwg_instock_get_coupon_box_html( $code, $amount, $expiry = '', $link = '', $preview = false ) {
	$code = trim( (string) $code );
	if ( '' === $code ) {
		return '';
	}

	$font   = "'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif";
	$colours = cwg_instock_get_coupon_box_colours();
	$accent  = $colours['accent'];
	$panel   = $colours['panel'];
	$on_accent = $colours['button'];

	/**
	 * Wording used in the coupon block.
	 *
	 * @since 7.4.5
	 */
	$options = get_option( 'cwginstocksettings', array() );
	$custom  = array(
		'heading' => isset( $options['coupon_box_heading'] ) ? trim( (string) $options['coupon_box_heading'] ) : '',
		'intro'   => isset( $options['coupon_box_intro'] ) ? trim( (string) $options['coupon_box_intro'] ) : '',
		'expiry'  => isset( $options['coupon_box_expiry'] ) ? trim( (string) $options['coupon_box_expiry'] ) : '',
		'button'  => isset( $options['coupon_box_button'] ) ? trim( (string) $options['coupon_box_button'] ) : '',
	);

	// Store wording can use the same placeholders as the rest of the email.
	$tokens = array(
		'{coupon_amount}' => $amount,
		'{coupon_code}'   => $code,
		'{coupon_expiry}' => $expiry,
		'{shopname}'      => wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ),
	);
	foreach ( $custom as $key => $text ) {
		if ( '' !== $text ) {
			$custom[ $key ] = str_replace( array_keys( $tokens ), array_values( $tokens ), $text );
		}
	}

	$strings = apply_filters(
		'cwginstock_coupon_box_strings',
		array(
			/* translators: %s: discount amount, for example 10% or $10. */
			'heading' => '' !== $custom['heading'] ? $custom['heading'] : ( '' !== $amount ? sprintf( __( 'Here is %s off your order', 'back-in-stock-notifier-for-woocommerce' ), $amount ) : __( 'Here is a discount for your order', 'back-in-stock-notifier-for-woocommerce' ) ),
			'intro'   => '' !== $custom['intro'] ? $custom['intro'] : __( 'Use this code at checkout', 'back-in-stock-notifier-for-woocommerce' ),
			/* translators: %s: expiry date. */
			'expiry'  => '' === $expiry ? '' : ( '' !== $custom['expiry'] ? $custom['expiry'] : sprintf( __( 'Valid until %s', 'back-in-stock-notifier-for-woocommerce' ), $expiry ) ),
			'button'  => '' !== $custom['button'] ? $custom['button'] : __( 'Shop now', 'back-in-stock-notifier-for-woocommerce' ),
		),
		$code,
		$amount,
		$expiry,
		$preview
	);

	ob_start();
	?>
	<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:16px 0;border-collapse:separate;">
		<tr>
			<td align="center" style="padding:24px 20px;background-color:<?php echo esc_attr( $panel ); ?>;border:2px dashed <?php echo esc_attr( $accent ); ?>;border-radius:6px;font-family:<?php echo esc_attr( $font ); ?>;">
				<p style="margin:0 0 6px;font-family:<?php echo esc_attr( $font ); ?>;font-size:18px;line-height:24px;font-weight:bold;color:<?php echo esc_attr( $accent ); ?>;"><?php echo esc_html( $strings['heading'] ); ?></p>
				<p style="margin:0 0 14px;font-family:<?php echo esc_attr( $font ); ?>;font-size:14px;line-height:20px;color:#636363;"><?php echo esc_html( $strings['intro'] ); ?></p>
				<p style="margin:0;font-family:<?php echo esc_attr( $font ); ?>;font-size:26px;line-height:32px;font-weight:bold;letter-spacing:3px;color:#2b2b2b;"><?php echo esc_html( $code ); ?></p>
				<?php if ( '' !== $strings['expiry'] ) { ?>
					<p style="margin:12px 0 0;font-family:<?php echo esc_attr( $font ); ?>;font-size:13px;line-height:18px;color:#8a8a8a;"><?php echo esc_html( $strings['expiry'] ); ?></p>
				<?php } ?>
				<?php if ( '' !== $link ) { ?>
					<table border="0" cellpadding="0" cellspacing="0" style="margin:18px auto 0;">
						<tr>
							<td align="center" bgcolor="<?php echo esc_attr( $accent ); ?>" style="padding:0;border-radius:4px;"><a href="<?php echo esc_url( $link ); ?>" style="display:inline-block;padding:12px 28px;font-family:<?php echo esc_attr( $font ); ?>;font-size:15px;line-height:18px;font-weight:bold;color:<?php echo esc_attr( $on_accent ); ?>;text-decoration:none;"><?php echo esc_html( $strings['button'] ); ?></a></td>
						</tr>
					</table>
				<?php } ?>
			</td>
		</tr>
	</table>
	<?php
	$html = ob_get_clean();

	// WooCommerce runs the email body through wpautop, which turns any line break
	// between these tags into a <br> and breaks the layout.
	$html = preg_replace( '/>\s+</', '><', trim( $html ) );

	/**
	 * Filter the coupon block used in the email.
	 *
	 * @since 7.4.5
	 */
	return apply_filters( 'cwginstock_coupon_box_html', $html, $code, $amount, $expiry, $link, $preview );
}

/**
 * Language the visitor is browsing in.
 *
 * Reads from WPML or Polylang when either is active.
 *
 * @since 7.4.5
 * @return string Language code, empty when the site is not multilingual.
 */
function cwg_instock_current_language() {
	$language = '';

	if ( defined( 'ICL_SITEPRESS_VERSION' ) || has_filter( 'wpml_current_language' ) ) {
		$language = apply_filters( 'wpml_current_language', null );
	}

	if ( ! $language && function_exists( 'pll_current_language' ) ) {
		$language = pll_current_language( 'slug' );
	}

	$language = is_string( $language ) ? $language : '';

	/**
	 * Filter the detected language.
	 *
	 * @since 7.4.5
	 */
	return (string) apply_filters( 'cwginstock_current_language', $language );
}

/**
 * Put the request into the language the visitor was browsing in.
 *
 * Form markup and messages are produced during AJAX and REST requests, where a
 * translation plugin usually cannot tell which language the visitor is on, so
 * everything comes back in the site's default language. The language travels
 * with the request and is applied here before any string is produced.
 *
 * @since 7.4.5
 * @param string $language Language code, read from the request when empty.
 * @return string The language applied, empty when none was usable.
 */
function cwg_instock_apply_request_language( $language = '' ) {
	if ( '' === $language ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
		$candidates = array();
		foreach ( array( 'cwg_lang', 'lang' ) as $field ) {
			if ( isset( $_REQUEST[ $field ] ) && is_string( $_REQUEST[ $field ] ) ) {
				$candidates[] = sanitize_text_field( wp_unslash( $_REQUEST[ $field ] ) );
			}
		}

		// The subscribe form posts the whole localisation object back, which is
		// where a translation add-on puts the language.
		if ( isset( $_REQUEST['dataobj'] ) && is_array( $_REQUEST['dataobj'] ) ) {
			foreach ( array( 'current_lang', 'wpml_current_lang', 'polylang_current_lang' ) as $key ) {
				if ( isset( $_REQUEST['dataobj'][ $key ] ) && is_string( $_REQUEST['dataobj'][ $key ] ) ) {
					$candidates[] = sanitize_text_field( wp_unslash( $_REQUEST['dataobj'][ $key ] ) );
				}
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended

		foreach ( $candidates as $candidate ) {
			if ( preg_match( '/^[a-z]{2,3}(-[a-zA-Z0-9]{2,8})?$/', $candidate ) ) {
				$language = $candidate;
				break;
			}
		}
	}

	if ( '' === $language ) {
		return '';
	}

	// Already in that language, nothing to do.
	if ( cwg_instock_current_language() === $language ) {
		return $language;
	}

	$switched = false;

	if ( has_action( 'wpml_switch_language' ) || defined( 'ICL_SITEPRESS_VERSION' ) ) {
		// This also reloads the text domains, which switching the global alone
		// would not do.
		do_action( 'wpml_switch_language', $language );
		$switched = true;
	}

	if ( function_exists( 'PLL' ) && function_exists( 'pll_current_language' ) ) {
		$polylang = PLL();
		if ( isset( $polylang->model ) && is_object( $polylang->model ) ) {
			$object = $polylang->model->get_language( $language );
			if ( $object ) {
				$polylang->curlang = $object;
				if ( ! empty( $object->locale ) ) {
					switch_to_locale( $object->locale );
				}
				$switched = true;
			}
		}
	}

	/**
	 * Fires when the request has been put into the visitor's language, for
	 * translation add-ons to follow.
	 *
	 * @since 7.4.5
	 */
	do_action( 'cwginstock_switch_language', $language, $switched );

	return $language;
}

if ( ! function_exists( 'cwg_instock_add_inline_css' ) ) {
	/**
	 * Attach a block of CSS through the style queue instead of printing a
	 * style tag. Works before and after the head is printed: a style enqueued
	 * late is printed by WordPress in the footer. Each handle is added once
	 * per request.
	 *
	 * @since 7.5.0
	 * @param string $handle Style handle, prefixed.
	 * @param string $css    CSS rules.
	 * @return void
	 */
	function cwg_instock_add_inline_css( $handle, $css ) {
		static $added = array();
		if ( isset( $added[ $handle ] ) || '' === trim( (string) $css ) ) {
			return;
		}
		$added[ $handle ] = true;
		if ( ! wp_style_is( $handle, 'registered' ) ) {
			wp_register_style( $handle, false, array(), defined( 'CWGINSTOCK_VERSION' ) ? CWGINSTOCK_VERSION : false );
		}
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $css );
	}
}

/**
 * UTM settings for the notification emails.
 *
 * @since 7.5.1
 * @return array Empty when tracking is switched off.
 */
function cwg_instock_get_utm_settings() {
	$options = get_option( 'cwginstocksettings', array() );

	if ( ! isset( $options['enable_utm_tracking'] ) || '1' !== (string) $options['enable_utm_tracking'] ) {
		return array();
	}

	$defaults = array(
		'utm_source'   => 'back-in-stock',
		'utm_medium'   => 'email',
		'utm_campaign' => 'back-in-stock-notification',
		'utm_term'     => '',
		'utm_content'  => '',
	);

	$values = array();
	foreach ( $defaults as $key => $default ) {
		$stored        = isset( $options[ $key ] ) ? trim( (string) $options[ $key ] ) : '';
		$values[ $key ] = '' !== $stored ? sanitize_text_field( $stored ) : $default;
	}

	return array_filter( $values, 'strlen' );
}

/**
 * Add the configured UTM parameters to a link used in an email.
 *
 * Parameters already present on the link are left alone, so a link that was
 * tagged by hand keeps its own values.
 *
 * @since 7.5.1
 * @param string $url     Link to tag.
 * @param string $context Where the link appears, for the filter below.
 * @return string
 */
function cwg_instock_add_utm_params( $url, $context = '' ) {
	if ( ! is_string( $url ) || '' === trim( $url ) ) {
		return $url;
	}

	// Leave anything that is not a web address alone, such as mailto links.
	if ( 0 !== stripos( $url, 'http://' ) && 0 !== stripos( $url, 'https://' ) ) {
		return $url;
	}

	$params = cwg_instock_get_utm_settings();
	if ( empty( $params ) ) {
		return $url;
	}

	// Optionally label each link so a click on the product, the cart, the table
	// or the coupon button can be told apart in reporting. A content value set
	// by hand always wins.
	$options = get_option( 'cwginstocksettings', array() );
	if ( '' !== (string) $context && empty( $params['utm_content'] )
		&& isset( $options['utm_distinguish_links'] ) && '1' === (string) $options['utm_distinguish_links'] ) {
		$params['utm_content'] = str_replace( '_', '-', $context );
	}

	/**
	 * Filter the UTM parameters before they are added to a link.
	 *
	 * @since 7.5.1
	 */
	$params = (array) apply_filters( 'cwginstock_utm_parameters', $params, $url, $context );

	$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
	if ( '' !== $query ) {
		$existing = array();
		wp_parse_str( $query, $existing );
		foreach ( array_keys( $params ) as $key ) {
			if ( isset( $existing[ $key ] ) && '' !== $existing[ $key ] ) {
				unset( $params[ $key ] );
			}
		}
	}

	if ( empty( $params ) ) {
		return $url;
	}

	return add_query_arg( array_map( 'rawurlencode', $params ), $url );
}
