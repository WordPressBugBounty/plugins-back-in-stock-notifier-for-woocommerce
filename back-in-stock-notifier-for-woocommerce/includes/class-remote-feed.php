<?php
/**
 * Remote Product Feed Consumer (Action Scheduler)
 *
 * Fetches product data from a remote CWG Feed Generator endpoint
 * using WooCommerce's Action Scheduler (as_schedule_recurring_action).
 * Data stored in wp_options — zero frontend impact.
 *
 * Flow:
 *  1. On plugin load (init), checks if recurring action is scheduled
 *  2. If not, schedules it via as_schedule_recurring_action (once per day)
 *  3. When action fires, fetches remote JSON feed
 *  4. Data is sanitized and stored in wp_options (permanent, autoload=false)
 *  5. get_products() reads only from wp_options — zero HTTP calls
 *  6. Manual "Refresh Feed" via AJAX triggers an immediate fetch
 *
 * Why Action Scheduler instead of WP Cron:
 *  - WP Cron is pseudo-cron and depends on site traffic
 *  - Action Scheduler (bundled with WooCommerce) provides reliable,
 *    background execution with built-in retry/failure handling
 *  - No admin_init overhead — schedule check uses a lightweight option flag
 *
 * @package BackInStockNotifier
 * @since   7.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CWG_Instock_Remote_Feed' ) ) {

	class CWG_Instock_Remote_Feed {

		const FEED_URL_OPTION      = 'cwg_bis_remote_feed_url';
		const PRODUCTS_OPTION      = 'cwg_bis_feed_products';
		const LAST_FETCH_OPTION    = 'cwg_bis_feed_last_fetch';
		const AS_HOOK              = 'cwg_bis_daily_feed_fetch';
		const SCHEDULE_FLAG_OPTION = 'cwg_bis_feed_scheduled';
		const AJAX_ACTION          = 'cwg_bis_refresh_feed';
		const NONCE_ACTION         = 'cwg_bis_feed_nonce';

		/** Recurrence interval: 1 day in seconds */
		const INTERVAL_SECONDS = DAY_IN_SECONDS;

		const CWG_PROMOTIONFEED_URL = 'https://propluginslab.com/wp-json/cwg-feed/v1/products';

		/** @var self|null */
		private static $instance = null;

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			// Register the Action Scheduler hook callback
			add_action( self::AS_HOOK, array( $this, 'fetch_remote_feed' ) );

			// Schedule recurring action (lightweight — uses option flag, not admin_init)
			add_action( 'init', array( $this, 'maybe_schedule_action' ), 20 );

			// AJAX refresh
			add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_refresh_feed' ) );

			// Register settings
			add_action( 'admin_init', array( $this, 'register_settings' ) );

			// Reschedule when feed URL changes
			add_action( 'update_option_' . self::FEED_URL_OPTION, array( $this, 'on_feed_url_change' ), 10, 2 );
		}

		/**
		 * Register the feed URL setting.
		 */
		public function register_settings() {
			register_setting(
				'cwginstocknotifier_settings',
				self::FEED_URL_OPTION,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'esc_url_raw',
					'default'           => '',
				)
			);
		}

		/**
		 * Schedule the recurring action if not already scheduled.
		 *
		 * Uses an option flag so we don't call as_next_scheduled_action()
		 * on every page load. The flag is set once and only cleared
		 * on deactivation or URL change.
		 */
		public function maybe_schedule_action() {
			// Action Scheduler must be available (bundled with WooCommerce)
			if ( ! function_exists( 'as_has_scheduled_action' ) ) {
				return;
			}

			$feed_url = self::get_feed_url();
			if ( empty( $feed_url ) ) {
				$this->unschedule_action();
				return;
			}

			// Check flag - if already scheduled, skip the expensive AS query
			$scheduled = get_option( self::SCHEDULE_FLAG_OPTION, false );
			if ( $scheduled ) {
				return;
			}

			// Double-check: is the action actually scheduled in Action Scheduler?
			if ( ! as_has_scheduled_action( self::AS_HOOK ) ) {
				as_schedule_recurring_action(
					time() + 60, // Start 1 minute from now (avoid immediate execution on first load)
					self::INTERVAL_SECONDS,
					self::AS_HOOK,
					array(),
					'cwg-bis-feed'
				);
			}

			// Set the flag so we don't check again
			update_option( self::SCHEDULE_FLAG_OPTION, true, true );
		}

		/**
		 * Unschedule all pending actions.
		 */
		private function unschedule_action() {
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( self::AS_HOOK, array(), 'cwg-bis-feed' );
			}
			delete_option( self::SCHEDULE_FLAG_OPTION );
		}

		/**
		 * When the feed URL option changes, reschedule and do an immediate fetch.
		 */
		public function on_feed_url_change( $old_value, $new_value ) {
			// Clear existing schedule
			$this->unschedule_action();

			if ( ! empty( $new_value ) ) {
				// Re-schedule (flag was cleared above, so maybe_schedule_action will pick it up)
				if ( function_exists( 'as_schedule_recurring_action' ) ) {
					as_schedule_recurring_action(
						time() + 60,
						self::INTERVAL_SECONDS,
						self::AS_HOOK,
						array(),
						'cwg-bis-feed'
					);
					update_option( self::SCHEDULE_FLAG_OPTION, true, true );
				}

				// Immediately fetch with new URL
				$this->fetch_remote_feed();
			}
		}

		/**
		 * Get the remote feed URL.
		 */
		public static function get_feed_url() {
			$url = self::CWG_PROMOTIONFEED_URL;
			return apply_filters( 'cwg_bis_remote_feed_url', $url );
		}

		/**
		 * Get products from stored data. Never makes HTTP calls.
		 */
		public static function get_products() {
			$products = get_option( self::PRODUCTS_OPTION, array() );
			return is_array( $products ) ? $products : array();
		}

		/**
		 * Get products grouped by category.
		 */
		public static function get_products_by_category() {
			$products = self::get_products();
			$grouped  = array();

			foreach ( $products as $product ) {
				$cat = ! empty( $product['category'] ) ? $product['category'] : 'general';
				if ( ! isset( $grouped[ $cat ] ) ) {
					$grouped[ $cat ] = array();
				}
				$grouped[ $cat ][] = $product;
			}

			return $grouped;
		}

		/**
		 * Get last fetch timestamp.
		 */
		public static function get_last_fetch_time() {
			return get_option( self::LAST_FETCH_OPTION, false );
		}

		/**
		 * Action Scheduler callback: Fetch from remote feed and store.
		 *
		 * Uses a transient lock to prevent concurrent fetches.
		 * Stores data with autoload=false (no frontend impact).
		 */
		public function fetch_remote_feed() {
			$feed_url = self::get_feed_url();
			if ( empty( $feed_url ) ) {
				return false;
			}

			// Prevent concurrent runs with a 5-minute lock
			$lock_key = 'cwg_bis_feed_fetch_lock';
			if ( get_transient( $lock_key ) ) {
				return false;
			}
			set_transient( $lock_key, 1, 5 * MINUTE_IN_SECONDS );

			$response = wp_remote_get(
				esc_url_raw( $feed_url ),
				array(
					'timeout'   => 30,
					'sslverify' => true,
					'headers'   => array(
						'Accept' => 'application/json',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				delete_transient( $lock_key );
				return false;
			}

			$code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $code ) {
				delete_transient( $lock_key );
				return false;
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( ! is_array( $data ) ) {
				delete_transient( $lock_key );
				return false;
			}

			// Sanitize each product
			$clean_products = array_map( array( __CLASS__, 'sanitize_product' ), $data );

			// Remove empty entries
			$clean_products = array_filter(
				$clean_products,
				function( $p ) {
					return ! empty( $p['slug'] );
				}
			);
			$clean_products = array_values( $clean_products );

			// Store permanently with autoload=false (never loaded on frontend)
			update_option( self::PRODUCTS_OPTION, $clean_products, false );
			update_option( self::LAST_FETCH_OPTION, time(), false );

			delete_transient( $lock_key );

			return true;
		}

		/**
		 * AJAX handler for manual feed refresh.
		 */
		public function ajax_refresh_feed() {
			check_ajax_referer( self::NONCE_ACTION, 'security' );

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'back-in-stock-notifier-for-woocommerce' ) ), 403 );
			}

			$result = $this->fetch_remote_feed();

			if ( ! $result ) {
				wp_send_json_error(
					array(
						'message' => __( 'Failed to fetch products from the remote feed. Please verify the Feed URL in Settings or try again later.', 'back-in-stock-notifier-for-woocommerce' ),
					)
				);
			}

			$products = self::get_products();
			wp_send_json_success(
				array(
					'message' => sprintf(
						__( 'Feed refreshed successfully! %d products loaded.', 'back-in-stock-notifier-for-woocommerce' ),
						count( $products )
					),
					'count'   => count( $products ),
				)
			);
		}

		/**
		 * Sanitize a single product from remote feed.
		 */
		private static function sanitize_product( $raw ) {
			if ( ! is_array( $raw ) ) {
				return array();
			}

			return array(
				'slug'            => isset( $raw['slug'] ) ? sanitize_key( $raw['slug'] ) : '',
				'name'            => isset( $raw['name'] ) ? sanitize_text_field( $raw['name'] ) : '',
				'description'     => isset( $raw['description'] ) ? sanitize_textarea_field( $raw['description'] ) : '',
				'price'           => isset( $raw['price'] ) ? sanitize_text_field( $raw['price'] ) : '0.00',
				'currency'        => isset( $raw['currency'] ) ? sanitize_text_field( $raw['currency'] ) : 'USD',
				'url'             => isset( $raw['url'] ) ? esc_url_raw( $raw['url'] ) : '',
				'category'        => isset( $raw['category'] ) ? sanitize_key( $raw['category'] ) : 'general',
				'type'            => isset( $raw['type'] ) ? sanitize_key( $raw['type'] ) : 'addon',
				'discount_active' => isset( $raw['discount_active'] ) ? (bool) $raw['discount_active'] : false,
				'discount_price'  => isset( $raw['discount_price'] ) && null !== $raw['discount_price'] ? sanitize_text_field( $raw['discount_price'] ) : null,
				'badge'           => isset( $raw['badge'] ) && null !== $raw['badge'] ? sanitize_text_field( $raw['badge'] ) : null,
				'icon_url'        => isset( $raw['icon_url'] ) ? esc_url_raw( $raw['icon_url'] ) : '',
				'sort_order'      => isset( $raw['sort_order'] ) ? absint( $raw['sort_order'] ) : 0,
				'plugin_file'     => isset( $raw['plugin_file'] ) ? sanitize_text_field( $raw['plugin_file'] ) : '',
			);
		}

		/**
		 * Cleanup on plugin deactivation.
		 */
		public static function deactivation_cleanup() {
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( self::AS_HOOK, array(), 'cwg-bis-feed' );
			}
			delete_option( self::SCHEDULE_FLAG_OPTION );
		}
	}

	CWG_Instock_Remote_Feed::instance();
}
