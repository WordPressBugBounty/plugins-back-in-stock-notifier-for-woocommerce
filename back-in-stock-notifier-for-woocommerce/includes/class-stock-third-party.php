<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'CWG_Instock_Third_Party_Support' ) ) {

	class CWG_Instock_Third_Party_Support {

		const AS_HOOK = 'cwginstock_third_party';

		const INTERVAL_OPTION = 'cwginstock_third_party_interval';

		const VERIFY_TRANSIENT = 'cwginstock_third_party_verified';

		/**
		 * Stock status this check last saw for a product.
		 */
		const LAST_SEEN_META = '_cwg_last_seen_stock_status';

		public function __construct() {
			add_action( self::AS_HOOK, array( $this, 'retrive_product_ids' ) );
			add_action( 'cwg_backward_stock_check', array( $this, 'backward_stock_check' ) );
			add_action( 'init', array( $this, 'maybe_schedule' ), 20 );
		}

		public static function is_enabled() {
			$options = get_option( 'cwginstocksettings' );
			return isset( $options['update_stock_third_party'] ) && '1' == $options['update_stock_third_party'];
		}

		/**
		 * Keep the stock check task in line with the settings. It only exists
		 * while the option is enabled, runs at the chosen recurrence, and is
		 * recreated if it goes missing.
		 *
		 * @since 7.4.3
		 */
		public function maybe_schedule() {
			if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
				return;
			}

			$scheduled = (int) get_option( self::INTERVAL_OPTION, 0 );

			if ( ! self::is_enabled() ) {
				if ( $scheduled ) {
					self::unschedule();
				}
				return;
			}

			$options  = get_option( 'cwginstocksettings' );
			$interval = CWG_Instock_Troubleshoot::recurrence_to_seconds( isset( $options['third_party_cron_recurrence'] ) ? $options['third_party_cron_recurrence'] : 'every_5_minutes' );

			if ( $scheduled === $interval ) {
				// Confirm the task still exists twice a day rather than on every request.
				if ( get_transient( self::VERIFY_TRANSIENT ) ) {
					return;
				}
				set_transient( self::VERIFY_TRANSIENT, 1, 12 * HOUR_IN_SECONDS );
				if ( as_next_scheduled_action( self::AS_HOOK ) ) {
					return;
				}
			}

			as_unschedule_all_actions( self::AS_HOOK );
			as_schedule_recurring_action( time() + $interval, $interval, self::AS_HOOK );
			update_option( self::INTERVAL_OPTION, $interval, false );
		}

		public static function unschedule() {
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( self::AS_HOOK );
			}
			delete_option( self::INTERVAL_OPTION );
			delete_transient( self::VERIFY_TRANSIENT );
		}

		public function retrive_product_ids() {
			$options                        = get_option( 'cwginstocksettings' );
			$check_stock_status_third_party = isset( $options['update_stock_third_party'] ) && '1' == $options['update_stock_third_party'] ? true : false;
			global $wpdb;
			if ( $check_stock_status_third_party ) {
				$args = array(
					'post_type' => 'cwginstocknotifier',
					'fields' => 'ids',
					'posts_per_page' => -1,
					'post_status' => 'cwg_subscribed',
				);

				$get_posts = get_posts( $args );
				if ( is_array( $get_posts ) && ! empty( $get_posts ) ) {
					// Generate placeholders dynamically
					$placeholders = implode( ',', array_fill( 0, count( $get_posts ), '%d' ) );

					// Construct the SQL query with placeholders
					$sql = "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id IN ($placeholders) AND meta_key = %s";

					// Merge all query parameters correctly
					$query_args = array_merge( $get_posts, array( 'cwginstock_pid' ) );

					// Prepare the query securely
					// phpcs:ignore
					$prepared_query = $wpdb->prepare( $sql, ...$query_args );

					// Execute the query in one step
					// phpcs:ignore
					$select_Query = $wpdb->get_col( $prepared_query );

					if ( is_array( $select_Query ) && ! empty( $select_Query ) ) {
						$array = array_values( array_unique( $select_Query ) );

						/**
						 * Most products one run of this check may process.
						 *
						 * @since 7.4.4
						 */
						$limit = max( 1, (int) apply_filters( 'cwginstock_third_party_max_products_per_run', 100 ) );
						if ( count( $array ) > $limit ) {
							$total  = count( $array );
							$array  = array_slice( $array, 0, $limit );
							$logger = new CWG_Instock_Logger( 'info', "Third Party Stock Check: $total products waiting, processing $limit this run and the rest on the next run" );
							$logger->record_log();
						}

						$chunk = array_chunk( $array, 5 );
						foreach ( $chunk as $each_array ) {
							as_schedule_single_action( time(), 'cwg_backward_stock_check', array( 'pid' => $each_array ) );
						}
					}
				}
			}
		}

		public function action_based_on_stock_status( $id, $stockstatus, $obj = '' ) {
			/**
			 * Action 'cwginstock_before_trigger_status' before processing stock status.
			 * 
			 * @since 6.0.8.1
			 */
			do_action( 'cwginstock_before_trigger_status', $id, $stockstatus, $obj );

			/**
			 * Filter 'cwg_before_process_instock_email' allows processing (returns true) and the stock status is 'instock', the action hook 'cwginstock_trigger_status' is triggered.
			 *
			 * @since 1.0.0
			 */
			if ( apply_filters( 'cwg_before_process_instock_email', true, $id, $stockstatus ) && 'instock' == $stockstatus ) {
				$logger = new CWG_Instock_Logger( 'info', 'Third Party Stock Inventory Check has been started for the Product ID #' . $id );
				$logger->record_log();
				/**
				 * Action based on stock status.
				 *
				 * @since 1.0.0
				 */
				do_action( 'cwginstock_trigger_status', $id, $stockstatus, $obj );
			}
		}

		/**
		 * Compare each product against the stock status this check last saw, and
		 * notify only when it has actually come back into stock. Checking the
		 * current status alone would notify every waiting subscriber on every
		 * run for as long as a product stays in stock.
		 *
		 * @param array $ids Product or variation ids.
		 */
		public function backward_stock_check( $ids ) {
			if ( ! is_array( $ids ) || empty( $ids ) ) {
				return;
			}

			foreach ( $ids as $value ) {
				$product = wc_get_product( $value );
				if ( ! $product ) {
					continue;
				}

				$stock_status = $product->get_stock_status();
				$last_seen    = get_post_meta( $value, self::LAST_SEEN_META, true );

				// Remember what we saw before deciding anything, so a run that
				// fails later still moves the baseline forward.
				update_post_meta( $value, self::LAST_SEEN_META, $stock_status );

				if ( '' === $last_seen ) {
					// First time this product is seen. Record the baseline only,
					// so enabling the option never notifies a backlog of
					// subscribers for stock that was already there.
					continue;
				}

				if ( 'instock' !== $stock_status ) {
					CWG_Instock_Restock_Guard::mark_out_of_stock( $value, $product );
					continue;
				}

				if ( 'instock' !== $last_seen ) {
					$this->action_based_on_stock_status( $value, $stock_status, $product );
				}
			}
		}

		/**
		 * Kept for backward compatibility with code that calls it directly.
		 */
		public function register_schedule() {
			delete_option( self::INTERVAL_OPTION );
			$this->maybe_schedule();
		}

	}

	new CWG_Instock_Third_Party_Support();
}
