<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Public function, class, hook and template variable names below are part of the
// plugin's published API (themes and add-ons use them), so they keep their names.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

if ( ! class_exists( 'CWG_Instock_Marketing_Consent' ) ) {

	/**
	 * Optional, unchecked marketing-consent checkbox on the subscribe form.
	 *
	 * A stock-alert request and marketing consent are different things. When this
	 * checkbox is enabled and a visitor ticks it, the choice is stored as the
	 * generic ppl_bis_marketing_consent subscriber meta. Every marketing-platform
	 * add-on (MailerLite, Mailchimp, Klaviyo and so on) reads that one meta before
	 * adding the person to an audience, so a stock-alert request on its own never
	 * enrols anybody in marketing. The checkbox never blocks the stock alert.
	 */
	class CWG_Instock_Marketing_Consent {

		/**
		 * Subscriber meta key read by the marketing-platform add-ons.
		 */
		const META_KEY = 'ppl_bis_marketing_consent';

		/**
		 * API helper.
		 *
		 * @var CWG_Instock_API
		 */
		private $api;

		public function __construct() {
			$settings  = get_option( 'cwginstock_marketing_consent_settings' );
			$is_enable = isset( $settings['enable_consent'] ) ? $settings['enable_consent'] : false;

			add_action( 'cwginstock_register_settings', array( $this, 'register_settings' ), 215 );
			add_action( 'cwginstock_settings_default', array( $this, 'default_values' ) );

			// Record the choice on every insert. Guarded by the setting inside,
			// so nothing is written while the feature is off.
			add_action( 'cwginstock_after_insert_subscriber', array( $this, 'store_consent' ), 10, 2 );

			if ( $is_enable ) {
				add_action( 'cwg_instock_after_email_field', array( $this, 'show_consent_frontend' ), 25, 2 );
			}

			$this->api = new CWG_Instock_API();
		}

		public function register_settings() {
			// phpcs:ignore PluginCheck.CodeAnalysis.SettingSanitization.register_settingDynamic
			register_setting( 'cwginstocknotifier_settings', 'cwginstock_marketing_consent_settings', array( $this, 'sanitize_data' ) );
			add_settings_section( 'cwginstock_section_marketing_consent', __( 'Marketing Consent Checkbox in Subscribe Form', 'back-in-stock-notifier-for-woocommerce' ), array( $this, 'section_heading' ), 'cwginstocknotifier_settings' );
			add_settings_field( 'cwg_instock_enable_consent', __( 'Enable Marketing Consent in Subscribe Form', 'back-in-stock-notifier-for-woocommerce' ), array( $this, 'enable_consent' ), 'cwginstocknotifier_settings', 'cwginstock_section_marketing_consent' );
			add_settings_field( 'cwg_instock_consent_text', __( 'Text for Marketing Consent - this will appear next to the checkbox frontend', 'back-in-stock-notifier-for-woocommerce' ), array( $this, 'consent_text' ), 'cwginstocknotifier_settings', 'cwginstock_section_marketing_consent' );
		}

		public function section_heading() {
			esc_html_e( 'An optional, unchecked marketing-consent checkbox, separate from the I Agree checkbox. When a visitor ticks it the subscriber is recorded as having given marketing consent, which the marketing-platform add-ons require before adding them to an audience. It never blocks the stock alert.', 'back-in-stock-notifier-for-woocommerce' );
		}

		public function enable_consent() {
			$options = get_option( 'cwginstock_marketing_consent_settings' );
			?>
			<input type='checkbox' name='cwginstock_marketing_consent_settings[enable_consent]' <?php isset( $options['enable_consent'] ) ? checked( $options['enable_consent'], 1 ) : ''; ?> value="1" />
			<p><i><?php esc_html_e( 'Select this option to show an unchecked marketing-consent checkbox in the subscribe form.', 'back-in-stock-notifier-for-woocommerce' ); ?></i></p>
			<?php
		}

		public function consent_text() {
			$options = get_option( 'cwginstock_marketing_consent_settings' );
			$text    = isset( $options['consent_text'] ) ? $options['consent_text'] : '';
			?>
			<textarea rows="5" cols="50" name="cwginstock_marketing_consent_settings[consent_text]"><?php echo do_shortcode( $this->api->sanitize_textarea_field( $text ) ); ?></textarea>
			<?php
		}

		/**
		 * Render the checkbox in the subscribe form.
		 *
		 * @param int $product_id   Product ID.
		 * @param int $variation_id Variation ID.
		 * @return void
		 */
		public function show_consent_frontend( $product_id, $variation_id ) {
			$options   = get_option( 'cwginstock_marketing_consent_settings' );
			$is_enable = isset( $options['enable_consent'] ) ? $options['enable_consent'] : false;
			$text      = isset( $options['consent_text'] ) && '' != $options['consent_text'] ? $options['consent_text'] : false;

			if ( $is_enable && $text ) {
				?>
				<div class="cwg_marketing_consent_checkbox"> <label for="cwg_marketing_consent_input"> <input type="checkbox"
							id="cwg_marketing_consent_input" class="cwg_marketing_consent_input" value="1" name="cwg_marketing_consent_input">
						<?php echo do_shortcode( $text ); ?>
					</label> </div>
				<?php
			}
		}

		/**
		 * Store the choice as the shared consent meta, when the feature is on.
		 *
		 * @param int   $id        Subscriber post ID.
		 * @param array $post_data Submitted subscribe data.
		 * @return void
		 */
		public function store_consent( $id, $post_data ) {
			$options   = get_option( 'cwginstock_marketing_consent_settings' );
			$is_enable = isset( $options['enable_consent'] ) ? $options['enable_consent'] : false;

			if ( ! $is_enable ) {
				return;
			}

			$raw     = isset( $post_data['marketing_consent'] ) ? $post_data['marketing_consent'] : '';
			$granted = in_array( (string) $raw, array( '1', 'true', 'yes', 'on' ), true ) ? '1' : '0';

			update_post_meta( (int) $id, self::META_KEY, $granted );
		}

		public function default_values() {
			$get_option = get_option( 'cwginstock_marketing_consent_settings', array() );

			if ( ! isset( $get_option['consent_text'] ) ) {
				$get_option['consent_text'] = 'Yes, I would also like to receive marketing emails. You can unsubscribe at any time.';
			}

			if ( ! isset( $get_option['enable_consent'] ) ) {
				$get_option['enable_consent'] = 0;
			}

			add_option( 'cwginstock_marketing_consent_settings', $get_option );
		}

		public function sanitize_data( $input ) {
			$textarea_field = array( 'consent_text' );

			if ( is_array( $input ) && ! empty( $input ) ) {
				foreach ( $input as $key => $value ) {
					if ( ! is_array( $value ) ) {
						if ( in_array( $key, $textarea_field, true ) ) {
							$input[ $key ] = $this->api->sanitize_textarea_field( $value );
						} else {
							$input[ $key ] = $this->api->sanitize_text_field( $value );
						}
					}
				}
			}

			return $input;
		}
	}

	$instock_marketing_consent = new CWG_Instock_Marketing_Consent();
}
