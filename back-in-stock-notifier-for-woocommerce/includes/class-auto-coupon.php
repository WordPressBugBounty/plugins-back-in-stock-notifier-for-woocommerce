<?php
/**
 * Auto Coupon.
 *
 * Optionally generates a unique WooCommerce coupon for a subscriber when their
 * back in stock email goes out, to encourage them to complete the purchase.
 *
 * Design rules:
 *  - Completely disabled by default. Nothing is created unless the merchant
 *    switches Auto Coupon on.
 *  - A coupon is only created when the email actually uses one of the coupon
 *    placeholders, so no coupons are generated for stores that do not use them.
 *  - Each coupon is restricted to the exact product the subscriber signed up
 *    for, and to that subscriber's email address.
 *  - The generated code is stored on the subscriber, so retries and resends
 *    reuse the same coupon instead of creating duplicates.
 *
 * @package BackInStockNotifier
 * @since   7.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CWG_Instock_Auto_Coupon' ) ) {

	class CWG_Instock_Auto_Coupon {

		const CODE_META    = 'cwginstock_coupon_code';
		const EXPIRY_META  = 'cwginstock_coupon_expires';

		public function __construct() {
			add_filter( 'cwginstock_email_placeholders', array( $this, 'add_coupon_placeholders' ), 10, 3 );
		}

		/* ================================================================
		 * Settings helpers
		 * ================================================================ */

		private static function opt( $key, $default = '' ) {
			$options = get_option( 'cwginstocksettings', array() );
			return isset( $options[ $key ] ) && '' !== $options[ $key ] ? $options[ $key ] : $default;
		}

		/**
		 * Master switch. Off by default.
		 *
		 * @return bool
		 */
		public static function is_enabled() {
			$enabled = '1' === (string) self::opt( 'enable_auto_coupon', '' );
			/**
			 * Filter to force-disable auto coupon generation.
			 *
			 * @since 7.4.0
			 */
			return (bool) apply_filters( 'cwginstock_enable_auto_coupon', $enabled );
		}

		public static function get_discount_type() {
			$type    = self::opt( 'coupon_discount_type', 'percent' );
			$allowed = array( 'percent', 'fixed_cart', 'fixed_product' );
			return in_array( $type, $allowed, true ) ? $type : 'percent';
		}

		public static function get_amount() {
			$amount = (float) self::opt( 'coupon_amount', 10 );
			return $amount > 0 ? $amount : 0;
		}

		public static function get_expiry_days() {
			$days = (int) self::opt( 'coupon_expiry_days', 7 );
			return $days > 0 ? $days : 0;
		}

		public static function get_prefix() {
			$prefix = (string) self::opt( 'coupon_prefix', 'BIS' );
			$prefix = preg_replace( '/[^A-Za-z0-9\-_]/', '', $prefix );
			return '' !== $prefix ? strtoupper( $prefix ) : 'BIS';
		}

		public static function get_usage_limit() {
			$limit = (int) self::opt( 'coupon_usage_limit', 1 );
			return $limit > 0 ? $limit : 1;
		}

		public static function get_minimum_amount() {
			$min = (float) self::opt( 'coupon_minimum_amount', 0 );
			return $min > 0 ? $min : 0;
		}

		public static function is_individual_use() {
			return '1' === (string) self::opt( 'coupon_individual_use', '1' );
		}

		public static function exclude_sale_items() {
			return '1' === (string) self::opt( 'coupon_exclude_sale_items', '1' );
		}

		public static function skip_when_on_sale() {
			return '1' === (string) self::opt( 'coupon_skip_when_on_sale', '' );
		}

		public static function allow_free_shipping() {
			return '1' === (string) self::opt( 'coupon_free_shipping', '' );
		}

		public static function restrict_to_email() {
			return '1' === (string) self::opt( 'coupon_restrict_email', '1' );
		}

		/* ================================================================
		 * Coupon creation
		 * ================================================================ */

		/**
		 * Resolve the product id a subscriber signed up for.
		 *
		 * @param int $subscriber_id Subscriber post id.
		 * @return int
		 */
		private static function get_subscriber_product_id( $subscriber_id ) {
			$pid = get_post_meta( $subscriber_id, 'cwginstock_bypass_pid', true );
			if ( ! $pid ) {
				$pid = get_post_meta( $subscriber_id, 'cwginstock_pid', true );
			}
			return absint( $pid );
		}

		/**
		 * Get (or create) the coupon code for a subscriber.
		 *
		 * Returns an empty string when Auto Coupon is off, the product is gone,
		 * or the configured rules say no coupon should be created.
		 *
		 * @since 7.4.0
		 * @param int $subscriber_id Subscriber post id.
		 * @return string Coupon code, or empty string.
		 */
		public static function get_coupon_for_subscriber( $subscriber_id ) {
			$subscriber_id = absint( $subscriber_id );
			if ( ! $subscriber_id || ! self::is_enabled() ) {
				return '';
			}
			if ( ! function_exists( 'wc_get_product' ) || ! class_exists( 'WC_Coupon' ) ) {
				return '';
			}

			// Reuse an existing coupon so resends and retries do not create a
			// second coupon, but only while that coupon is still usable. If it
			// expired or was deleted, fall through and issue a fresh one.
			$existing = get_post_meta( $subscriber_id, self::CODE_META, true );
			if ( $existing ) {
				if ( self::is_coupon_still_usable( $existing ) ) {
					return $existing;
				}
				// Stale reference, clear it so a new coupon is created below.
				delete_post_meta( $subscriber_id, self::CODE_META );
				delete_post_meta( $subscriber_id, self::EXPIRY_META );
			}

			$product_id = self::get_subscriber_product_id( $subscriber_id );
			$product    = $product_id ? wc_get_product( $product_id ) : null;
			if ( ! $product instanceof WC_Product ) {
				return '';
			}

			// Merchant chose not to discount products that are already on sale.
			if ( self::skip_when_on_sale() && $product->is_on_sale() ) {
				return '';
			}

			if ( self::get_amount() <= 0 ) {
				return '';
			}

			$email = get_post_meta( $subscriber_id, 'cwginstock_subscriber_email', true );

			/**
			 * Last chance to prevent a coupon being generated for this subscriber.
			 *
			 * @since 7.4.0
			 */
			if ( ! apply_filters( 'cwginstock_create_auto_coupon', true, $subscriber_id, $product ) ) {
				return '';
			}

			$code = self::generate_code();

			try {
				$coupon = new WC_Coupon();
				$coupon->set_code( $code );
				$coupon->set_discount_type( self::get_discount_type() );
				$coupon->set_amount( self::get_amount() );
				$coupon->set_individual_use( self::is_individual_use() );
				$coupon->set_exclude_sale_items( self::exclude_sale_items() );
				$coupon->set_usage_limit( self::get_usage_limit() );
				$coupon->set_usage_limit_per_user( 1 );
				$coupon->set_free_shipping( self::allow_free_shipping() );

				// Restrict to the exact product or variation this person subscribed to.
				// WooCommerce matches a cart item when the coupon lists either the
				// item id or its parent id, so storing the parent would let every
				// sibling variation use the coupon. Always store the subscribed id.
				$restrict_id = $product_id;
				$coupon->set_product_ids( array( $restrict_id ) );

				// For a variation, also exclude the other variations of the same
				// parent so the coupon can never be applied to a sibling.
				if ( $product->is_type( 'variation' ) ) {
					$parent_id = $product->get_parent_id();
					$parent    = $parent_id ? wc_get_product( $parent_id ) : null;
					if ( $parent instanceof WC_Product ) {
						$siblings = array_diff( (array) $parent->get_children(), array( $product_id ) );
						if ( ! empty( $siblings ) ) {
							$coupon->set_excluded_product_ids( array_values( array_map( 'absint', $siblings ) ) );
						}
					}
				}

				if ( self::get_minimum_amount() > 0 ) {
					$coupon->set_minimum_amount( self::get_minimum_amount() );
				}
				if ( self::restrict_to_email() && $email && is_email( $email ) ) {
					$coupon->set_email_restrictions( array( $email ) );
				}

				$expiry_days = self::get_expiry_days();
				if ( $expiry_days > 0 ) {
					$expires = time() + ( $expiry_days * DAY_IN_SECONDS );
					$coupon->set_date_expires( $expires );
					update_post_meta( $subscriber_id, self::EXPIRY_META, $expires );
				}

				$coupon->set_description(
					sprintf(
						/* translators: 1: product name, 2: subscriber id */
						__( 'Auto generated by Back In Stock Notifier for %1$s (subscriber #%2$d)', 'back-in-stock-notifier-for-woocommerce' ),
						$product->get_name(),
						$subscriber_id
					)
				);

				$coupon_id = $coupon->save();
			} catch ( Exception $e ) {
				$logger = new CWG_Instock_Logger( 'error', 'Auto Coupon: failed to create coupon for subscriber #' . $subscriber_id . ' - ' . $e->getMessage() );
				$logger->record_log();
				return '';
			}

			if ( ! $coupon_id ) {
				return '';
			}

			update_post_meta( $subscriber_id, self::CODE_META, $code );
			update_post_meta( $coupon_id, 'cwginstock_subscriber_id', $subscriber_id );

			$logger = new CWG_Instock_Logger( 'info', "Auto Coupon: created coupon $code for subscriber #$subscriber_id (product #$restrict_id)" );
			$logger->record_log();

			/**
			 * Fires after an auto coupon is created.
			 *
			 * @since 7.4.0
			 */
			do_action( 'cwginstock_auto_coupon_created', $code, $subscriber_id, $coupon_id );

			return $code;
		}

		/**
		 * Is a previously issued coupon still usable?
		 *
		 * Returns false when the coupon was deleted or trashed, or when it has
		 * expired, so the subscriber is never sent a dead code on a resend.
		 *
		 * @since 7.4.0
		 * @param string $code Coupon code.
		 * @return bool
		 */
		public static function is_coupon_still_usable( $code ) {
			if ( ! $code || ! function_exists( 'wc_get_coupon_id_by_code' ) ) {
				return false;
			}

			// Coupon lookups are cached, so clear the cache before checking to
			// avoid trusting a stale id for a coupon that was just deleted.
			if ( class_exists( 'WC_Cache_Helper' ) ) {
				WC_Cache_Helper::invalidate_cache_group( 'coupons' );
			}

			$coupon_id = wc_get_coupon_id_by_code( $code );
			if ( ! $coupon_id ) {
				return false;
			}

			// The post must still exist and be a published coupon.
			$post = get_post( $coupon_id );
			if ( ! $post || 'shop_coupon' !== $post->post_type || 'publish' !== $post->post_status ) {
				return false;
			}

			$coupon = new WC_Coupon( $coupon_id );

			$expires = $coupon->get_date_expires();
			if ( $expires && $expires->getTimestamp() < time() ) {
				return false;
			}

			// Fully used up, issue a new one instead.
			$limit = $coupon->get_usage_limit();
			if ( $limit > 0 && $coupon->get_usage_count() >= $limit ) {
				return false;
			}

			return true;
		}

		/**
		 * Build a unique coupon code.
		 *
		 * @return string
		 */
		private static function generate_code() {
			$prefix = self::get_prefix();
			do {
				$code = $prefix . '-' . strtoupper( wp_generate_password( 8, false, false ) );
			} while ( wc_get_coupon_id_by_code( $code ) );
			return $code;
		}

		/* ================================================================
		 * Email placeholders
		 * ================================================================ */

		/**
		 * Placeholder tokens this feature provides.
		 *
		 * @return array
		 */
		public static function get_placeholder_tokens() {
			return array( '{coupon_code}', '{coupon_amount}', '{coupon_expiry}' );
		}

		/**
		 * Add coupon placeholders to the back in stock email.
		 *
		 * The coupon is created lazily, only when the email content actually
		 * contains a coupon placeholder, so no coupon is generated for stores
		 * that do not use them.
		 *
		 * @since 7.4.0
		 * @param array    $placeholders  Existing placeholders.
		 * @param int      $subscriber_id Subscriber post id.
		 * @param WC_Email $email         Email instance.
		 * @return array
		 */
		public function add_coupon_placeholders( $placeholders, $subscriber_id, $email = null ) {
			// Always register the tokens so an unused placeholder never leaks
			// into a customer email as raw text.
			foreach ( self::get_placeholder_tokens() as $token ) {
				if ( ! isset( $placeholders[ $token ] ) ) {
					$placeholders[ $token ] = '';
				}
			}

			if ( ! self::is_enabled() ) {
				return $placeholders;
			}

			// Coupons belong on the back in stock email only, not the
			// subscription confirmation.
			if ( is_object( $email ) && isset( $email->id ) && 'cwg_bis_instock' !== $email->id ) {
				return $placeholders;
			}

			if ( ! $this->email_uses_coupon( $email ) ) {
				return $placeholders;
			}

			$code = self::get_coupon_for_subscriber( $subscriber_id );
			if ( '' === $code ) {
				return $placeholders;
			}

			$placeholders['{coupon_code}']   = $code;
			$placeholders['{coupon_amount}'] = self::format_amount();

			$expires = (int) get_post_meta( $subscriber_id, self::EXPIRY_META, true );
			if ( $expires ) {
				$placeholders['{coupon_expiry}'] = wp_date( get_option( 'date_format' ), $expires );
			}

			return $placeholders;
		}

		/**
		 * Does the email content reference a coupon placeholder?
		 *
		 * @param WC_Email $email Email instance.
		 * @return bool
		 */
		private function email_uses_coupon( $email ) {
			$haystack = '';
			if ( is_object( $email ) ) {
				if ( method_exists( $email, 'get_option' ) ) {
					$haystack .= (string) $email->get_option( 'additional_content' );
					$haystack .= (string) $email->get_option( 'subject' );
					$haystack .= (string) $email->get_option( 'heading' );
				}
			}

			// Legacy settings are still honoured for stores that never migrated.
			$options   = get_option( 'cwginstocksettings', array() );
			$haystack .= isset( $options['instock_mail_message'] ) ? (string) $options['instock_mail_message'] : '';
			$haystack .= isset( $options['instock_mail_subject'] ) ? (string) $options['instock_mail_subject'] : '';

			foreach ( self::get_placeholder_tokens() as $token ) {
				if ( false !== strpos( $haystack, $token ) ) {
					return true;
				}
			}

			/**
			 * Force coupon generation even when no placeholder is detected,
			 * useful when the code is injected by a custom template.
			 *
			 * @since 7.4.0
			 */
			return (bool) apply_filters( 'cwginstock_force_auto_coupon', false, $email );
		}

		/**
		 * Human readable discount amount, for example "10%" or "$5.00".
		 *
		 * @return string
		 */
		public static function format_amount() {
			$amount = self::get_amount();
			if ( 'percent' === self::get_discount_type() ) {
				return wc_format_localized_decimal( $amount ) . '%';
			}
			return function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $amount ) ) : (string) $amount;
		}
	}

	new CWG_Instock_Auto_Coupon();
}
