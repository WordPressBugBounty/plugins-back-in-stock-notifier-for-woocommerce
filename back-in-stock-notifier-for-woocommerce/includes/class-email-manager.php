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
			add_action( 'admin_init', array( $this, 'maybe_migrate_settings' ), 5 );
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
			if ( get_option( self::MIGRATION_FLAG ) ) {
				return;
			}

			$old = get_option( 'cwginstocksettings', array() );
			if ( empty( $old ) || ! is_array( $old ) ) {
				update_option( self::MIGRATION_FLAG, '1' );
				return;
			}

			// Migrate Subscription Email
			$sub_settings = get_option( 'woocommerce_cwg_bis_subscription_settings', array() );
			if ( empty( $sub_settings ) ) {
				$sub_settings = array();

				// Enabled flag
				$sub_settings['enabled'] = ! empty( $old['enable_success_sub_mail'] ) ? 'yes' : 'no';

				// Subject - use old value if set
				if ( ! empty( $old['success_sub_subject'] ) ) {
					$sub_settings['subject'] = sanitize_text_field( $old['success_sub_subject'] );
					$sub_settings['heading'] = sanitize_text_field( $old['success_sub_subject'] ); // best effort to set heading same as subject if old subject exists
				}

				// Message → additional_content (best effort; the template itself now has structured HTML)
				if ( ! empty( $old['success_sub_message'] ) ) {
					$sub_settings['additional_content'] = wp_kses_post( $old['success_sub_message'] );
				}

				$sub_settings['email_type'] = 'html';
				update_option( 'woocommerce_cwg_bis_subscription_settings', $sub_settings );
			}

			// Migrate Instock Email
			$ins_settings = get_option( 'woocommerce_cwg_bis_instock_settings', array() );
			if ( empty( $ins_settings ) ) {
				$ins_settings = array();

				$ins_settings['enabled'] = ! empty( $old['enable_instock_mail'] ) ? 'yes' : 'no';

				if ( ! empty( $old['instock_mail_subject'] ) ) {
					$ins_settings['subject'] = sanitize_text_field( $old['instock_mail_subject'] );
					$ins_settings['heading'] = sanitize_text_field( $old['instock_mail_subject'] ); // best effort to set heading same as subject if old subject exists
				}

				if ( ! empty( $old['instock_mail_message'] ) ) {
					$ins_settings['additional_content'] = wp_kses_post( $old['instock_mail_message'] );
				}

				$ins_settings['email_type'] = 'html';
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
