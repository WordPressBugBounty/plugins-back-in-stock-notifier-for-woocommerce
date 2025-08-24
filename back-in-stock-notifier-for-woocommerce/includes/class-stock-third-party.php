<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( ' CWG_Instock_Third_Party_Support' ) ) {

	class CWG_Instock_Third_Party_Support {

		public function __construct() {
			add_action( 'cwginstock_third_party', array( $this, 'retrive_product_ids' ) );
			add_action( 'cwg_backward_stock_check', array( $this, 'backward_stock_check' ) );
			register_activation_hook( CWGINSTOCK_FILE, array( $this, 'register_schedule' ) );
		}

		public function retrive_product_ids() {
			$options = get_option( 'cwginstocksettings' );
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
						$array = array_unique( $select_Query );
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

		public function backward_stock_check( $ids ) {
			if ( is_array( $ids ) && ! empty( $ids ) ) {
				foreach ( $ids as $key => $value ) {
					$product = wc_get_product( $value );
					if ( $product ) {
						$stock_status = $product->get_stock_status();
						if ( 'instock' == $stock_status ) {
							$this->action_based_on_stock_status( $value, $stock_status, $product );
						}
					}
				}
			}
		}

		public function register_schedule() {
			as_unschedule_all_actions( 'cwginstock_third_party' );
			if ( ! as_next_scheduled_action( 'cwginstock_third_party' ) ) {
				as_schedule_recurring_action( time(), DAY_IN_SECONDS, 'cwginstock_third_party' );
			}
		}

	}

	new CWG_Instock_Third_Party_Support();
}
