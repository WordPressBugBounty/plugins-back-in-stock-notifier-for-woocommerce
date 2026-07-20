<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CWG_Instock_Partial_Stock' ) ) {

	/**
	 * Fair Sending (First-Come-First-Served) for partial restocks.
	 *
	 * When a product is restocked with limited quantity, only the earliest
	 * subscribers are notified (one per unit in stock by default). The rest
	 * stay on the waitlist and are notified on the next restock. Subscribers
	 * who already received a notification move to the end of the line.
	 *
	 * Runs on the subscriber-collection filters only, so manual sends, bulk
	 * sends and add-ons are never affected. Disabled by default.
	 *
	 * @since 7.3.0
	 */
	class CWG_Instock_Partial_Stock {

		public function __construct() {
			// Priority 9999 so bundle/parent-variation subscriber merging (10)
			// and the variable-product reset filter (99) run first.
			add_filter( 'cwginstock_trigger_status_product', array( $this, 'limit_subscribers_to_stock' ), 9999, 2 );
			add_filter( 'cwginstock_trigger_status_variation', array( $this, 'limit_subscribers_to_stock' ), 9999, 2 );
		}

		public static function is_enabled() {
			$options = get_option( 'cwginstocksettings', array() );
			return is_array( $options ) && isset( $options['enable_fcfs_notification'] ) && '1' == $options['enable_fcfs_notification'];
		}

		/**
		 * How many subscribers to notify for each unit in stock.
		 *
		 * @return int
		 */
		public static function subscribers_per_unit() {
			$options  = get_option( 'cwginstocksettings', array() );
			$per_unit = isset( $options['fcfs_subscribers_per_unit'] ) ? absint( $options['fcfs_subscribers_per_unit'] ) : 1;
			if ( $per_unit < 1 ) {
				$per_unit = 1;
			}
			return min( $per_unit, 100 );
		}

		/**
		 * Limit the collected subscribers to the available stock quantity.
		 *
		 * @param array $subscribers Subscriber post ids collected for this restock.
		 * @param int   $product_id  Product or variation id that came back in stock.
		 * @return array
		 */
		public function limit_subscribers_to_stock( $subscribers, $product_id ) {
			if ( ! self::is_enabled() || ! is_array( $subscribers ) || empty( $subscribers ) ) {
				return $subscribers;
			}

			$product = wc_get_product( $product_id );
			// Only applies when stock is managed by quantity. managing_stock()
			// returns 'parent' for variations inheriting parent stock, which is
			// fine, get_stock_quantity() then reads the parent quantity.
			if ( ! $product || ! $product->managing_stock() ) {
				return $subscribers;
			}

			$quantity = $product->get_stock_quantity();
			if ( null === $quantity || (int) $quantity <= 0 ) {
				return $subscribers;
			}

			$limit = (int) $quantity * self::subscribers_per_unit();
			if ( count( $subscribers ) <= $limit ) {
				return $subscribers;
			}

			$selected = array_slice( $this->order_fairly( $subscribers ), 0, $limit );

			$total   = count( $subscribers );
			$waiting = $total - count( $selected );
			$logger  = new CWG_Instock_Logger( 'info', "Fair Sending: product #$product_id restocked with $quantity unit(s), notifying first " . count( $selected ) . " of $total subscribers, $waiting remain on the waitlist for the next restock" );
			$logger->record_log();

			/**
			 * Filter the subscribers selected by Fair Sending.
			 *
			 * @since 7.3.0
			 *
			 * @param array $selected    Selected subscriber ids.
			 * @param array $subscribers All collected subscriber ids.
			 * @param int   $product_id  Product or variation id.
			 * @param int   $quantity    Available stock quantity.
			 */
			return apply_filters( 'cwginstock_fcfs_selected_subscribers', $selected, $subscribers, $product_id, $quantity );
		}

		/**
		 * Order subscribers oldest-first, with never-notified subscribers ahead
		 * of ones that already received an instock email (least recent first).
		 *
		 * This keeps rotation fair when "Keep Subscription Entry to Subscribed
		 * Status" is enabled, the same people do not get every notification.
		 *
		 * @param array $subscribers Subscriber post ids.
		 * @return array
		 */
		private function order_fairly( $subscribers ) {
			$subscribers = array_values( array_unique( array_map( 'absint', $subscribers ) ) );

			$ordered = get_posts(
				array(
					'post_type'      => 'cwginstocknotifier',
					'post_status'    => array( 'cwg_subscribed', 'cwg_queued', 'cwg_doubleoptin' ),
					'post__in'       => $subscribers,
					'orderby'        => 'date',
					'order'          => 'ASC',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);

			if ( empty( $ordered ) ) {
				return $subscribers;
			}

			// Keep any id the query missed (unexpected status) at the end.
			$missing = array_diff( $subscribers, $ordered );
			if ( ! empty( $missing ) ) {
				$ordered = array_merge( $ordered, array_values( $missing ) );
			}

			update_meta_cache( 'post', $ordered );

			$never_notified   = array();
			$already_notified = array();
			foreach ( $ordered as $subscriber_id ) {
				$last_mail = get_post_meta( $subscriber_id, 'cwginstock_mail_on', true );
				if ( empty( $last_mail ) ) {
					$never_notified[] = $subscriber_id;
				} else {
					$already_notified[ $subscriber_id ] = (int) $last_mail;
				}
			}
			asort( $already_notified );

			return array_merge( $never_notified, array_keys( $already_notified ) );
		}
	}

	new CWG_Instock_Partial_Stock();
}
