<?php
/**
 * Registers WooCommerce email classes and migrates legacy mail settings.
 *
 * Migration strategy (backward-compatible):
 *  1. On first load, detect that old 'cwginstocksettings' has mail fields
 *     but WC email options have not yet been saved.
 *  2. Copy subject/message/enabled into the matching WC_Email option keys.
 *  3. Mark migration as done so it runs only once.
 *  4. Old settings remain in DB so any third-party code still reading them
 *     will not break; the old fields are simply no longer shown in UI.
 *
 * @package BackInStockNotifier
 * @since   7.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CWG_Instock_Email_Manager' ) ) {

	class CWG_Instock_Email_Manager {

		const MIGRATION_FLAG = 'cwg_bis_email_migrated_v7';

		public function __construct() {
			add_filter( 'woocommerce_email_classes', array( $this, 'register_email_classes' ) );
			// Run after the legacy/default settings are initialized so fresh installs
			// also get the default email subject/message values mapped into the
			// WooCommerce email options.
			add_action( 'admin_init', array( $this, 'maybe_migrate_settings' ), 15 );
			add_filter( 'woocommerce_email_from_name', array( __CLASS__, 'filter_from_name' ), 10, 2 );
			add_filter( 'woocommerce_email_from_address', array( __CLASS__, 'filter_from_email' ), 10, 2 );
			add_filter( 'woocommerce_email_headers', array( __CLASS__, 'filter_headers' ), 10, 3 );
		}

		/**
		 * Register our custom email classes with WooCommerce.
		 *
		 * @param array $emails Existing WC email classes.
		 * @return array
		 */
		public function register_email_classes( $emails ) {
			require_once CWGINSTOCK_PLUGINDIR . 'includes/emails/class-wc-email-bis-subscription.php';
			require_once CWGINSTOCK_PLUGINDIR . 'includes/emails/class-wc-email-bis-instock.php';

			$emails['WC_Email_BIS_Subscription'] = new WC_Email_BIS_Subscription();
			$emails['WC_Email_BIS_Instock']      = new WC_Email_BIS_Instock();

			return $emails;
		}
		public function from_email() {
			$options    = get_option( 'cwginstocksettings' );
			$from_email = ! empty( $options['mail_from_email'] ) ? $options['mail_from_email'] : get_option( 'woocommerce_email_from_address', get_bloginfo( 'admin_email' ) );
			/**
			 *  Modify the "From" email address
			 *
			 * @since 1.0.0
			 */
			return apply_filters( 'cwginstock_from_email', $from_email );
		}

		public function from_name() {
			$options   = get_option( 'cwginstocksettings' );
			$from_name = ! empty( $options['mail_from_name'] ) ? $options['mail_from_name'] : get_option( 'woocommerce_email_from_name', get_bloginfo( 'name' ) );
			/**
			 *  Modify the "From" name
			 *
			 * @since 1.0.0
			 */
			return apply_filters( 'cwginstock_from_name', $from_name );
		}

		public static function is_cwg_email( $email_id ) {
			return in_array(
				$email_id,
				array( 'cwg_bis_subscription', 'cwg_bis_instock' ),
				true
			);
		}

		public function reply_to_email() {
			$options  = get_option( 'cwginstocksettings' );
			$reply_to = ! empty( $options['mail_reply_to'] ) ? $options['mail_reply_to'] : '';
			/**
			 *  Modify the "Reply To" email address
			 *
			 * @since 1.0.0
			 */
			return apply_filters( 'cwginstock_reply_to_email', $reply_to );
		}


		/**
		 * From Email override
		 */
		public static function filter_from_email( $address, $email ) {

			if ( empty( $email->id ) || ! self::is_cwg_email( $email->id ) ) {
				return $address;
			}

			$instance = new self(); // ← your class name
			return $instance->from_email();
		}

		/**
		 * From Name override
		 */
		public static function filter_from_name( $name, $email ) {

			if ( empty( $email->id ) || ! self::is_cwg_email( $email->id ) ) {
				return $name;
			}

			$instance = new self();
			return $instance->from_name();
		}

		/**
		 * Reply-To header
		 */
		public static function filter_headers( $headers, $email_id, $object ) {

			if ( ! self::is_cwg_email( $email_id ) ) {
				return $headers;
			}
			
			$instance = new self();
			$reply_to = $instance->reply_to_email();

			if ( empty( $reply_to ) ) {
				return $headers; // fallback to WC
			}

			$headers = preg_replace( '/^Reply-To:.*\r?\n?/mi', '', $headers );

			$headers .= "Reply-To: {$reply_to}\r\n";

			return $headers;

		}


		/**
		 * One-time migration of legacy plugin settings → WooCommerce email options.
		 */
		public function maybe_migrate_settings() {
			$old = get_option( 'cwginstocksettings', array() );
			if ( ! is_array( $old ) ) {
				$old = array();
			}

			$sub_settings = get_option( 'woocommerce_cwg_bis_subscription_settings', array() );
			if ( ! is_array( $sub_settings ) ) {
				$sub_settings = array();
			}

			$ins_settings = get_option( 'woocommerce_cwg_bis_instock_settings', array() );
			if ( ! is_array( $ins_settings ) ) {
				$ins_settings = array();
			}

			$needs_sub_migration = empty( $sub_settings )
				|| empty( $sub_settings['subject'] )
				|| empty( $sub_settings['heading'] )
				|| empty( $sub_settings['additional_content'] );
			$needs_in_migration  = empty( $ins_settings )
				|| empty( $ins_settings['subject'] )
				|| empty( $ins_settings['heading'] )
				|| empty( $ins_settings['additional_content'] );

			if ( get_option( self::MIGRATION_FLAG ) && ! $needs_sub_migration && ! $needs_in_migration ) {
				return;
			}

			// Migrate Subscription Email
			if ( $needs_sub_migration ) {
				$sub_settings = wp_parse_args(
					$sub_settings,
					array(
						'enabled'            => ! empty( $old['enable_success_sub_mail'] ) ? 'yes' : 'no',
						'subject'            => ! empty( $old['success_sub_subject'] )
							? sanitize_text_field( $old['success_sub_subject'] )
							: __( 'You subscribed to {product_name} at {shopname}', 'back-in-stock-notifier-for-woocommerce' ),
						'heading'            => ! empty( $old['success_sub_subject'] )
							? sanitize_text_field( $old['success_sub_subject'] )
							: __( 'Thanks for subscribing to {product_name}', 'back-in-stock-notifier-for-woocommerce' ),
						'additional_content' => ! empty( $old['success_sub_message'] )
							? wp_kses_post( $old['success_sub_message'] )
							: __( 'Hello {subscriber_name},<br/><br/>Thank you for subscribing to {product_name} (#{product_id}). We will notify {subscriber_email} as soon as this product is back in stock. You can review the product here: {product_link}.<br/><br/>Thanks for shopping with {shopname}.', 'back-in-stock-notifier-for-woocommerce' ),
						'email_type'         => 'html',
					)
				);

				update_option( 'woocommerce_cwg_bis_subscription_settings', $sub_settings );
			}

			// Migrate Instock Email
			if ( $needs_in_migration ) {
				$ins_settings = wp_parse_args(
					$ins_settings,
					array(
						'enabled'            => ! empty( $old['enable_instock_mail'] ) ? 'yes' : 'no',
						'subject'            => ! empty( $old['instock_mail_subject'] )
							? sanitize_text_field( $old['instock_mail_subject'] )
							: __( 'Good news — {product_name} is back in stock', 'back-in-stock-notifier-for-woocommerce' ),
						'heading'            => ! empty( $old['instock_mail_subject'] )
							? sanitize_text_field( $old['instock_mail_subject'] )
							: __( 'Your subscribed item {product_name} is now available', 'back-in-stock-notifier-for-woocommerce' ),
						'additional_content' => ! empty( $old['instock_mail_message'] )
							? wp_kses_post( $old['instock_mail_message'] )
							: __( 'Hello {subscriber_name},<br/><br/>Good news — {product_name} is now back in stock. You can view it here: {product_link} or add it directly to your cart: {cart_link}. We only have limited stock available, so please act quickly. Thanks for subscribing with {shopname}.', 'back-in-stock-notifier-for-woocommerce' ),
						'email_type'         => 'html',
					)
				);

				update_option( 'woocommerce_cwg_bis_instock_settings', $ins_settings );
			}

			update_option( self::MIGRATION_FLAG, '1' );
		}

		/**
		 * Get WooCommerce email settings URL for a specific email class.
		 *
		 * WooCommerce uses sanitize_title( get_class( $email ) ) for the section
		 * parameter, which converts underscores to hyphens.
		 *
		 * @param string $class_name The WC email class name (e.g. 'WC_Email_BIS_Subscription').
		 * @return string
		 */
		public static function get_email_settings_url( $class_name ) {
			return admin_url( 'admin.php?page=wc-settings&tab=email&section=' . sanitize_title( $class_name ) );
		}
	}

	new CWG_Instock_Email_Manager();
}
