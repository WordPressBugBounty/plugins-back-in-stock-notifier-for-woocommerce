<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CWG_Instock_Restock_Guard' ) ) {

	/**
	 * Makes sure a subscriber is notified once per restock.
	 *
	 * Each time a product comes back into stock it is given a restock marker.
	 * A subscriber records the marker they were notified for, so the same
	 * restock can never notify them twice, even when their entry is returned
	 * to the subscribed state by the "Keep Subscription Entry to Subscribed
	 * Status" setting or by queue recovery. A later restock creates a new
	 * marker, which makes them eligible again.
	 *
	 * @since 7.4.4
	 */
	class CWG_Instock_Restock_Guard {

		/**
		 * Marker stored on the product or variation that was restocked.
		 */
		const PRODUCT_META = '_cwg_restock_marker';

		/**
		 * Marker the subscriber was last notified for.
		 */
		const SUBSCRIBER_META = 'cwginstock_notified_restock';

		/**
		 * Set while a product is out of stock, so the next restock is treated as
		 * a new event rather than the one already notified.
		 */
		const OPEN_META = '_cwg_restock_open';

		/**
		 * Record a restock and return its marker.
		 *
		 * A new marker is raised only once the product has been seen out of
		 * stock since the last one. The stock hook and the third-party stock
		 * check can both report the same restock, and this keeps them on one
		 * marker so nobody is notified twice, while a genuine later restock
		 * still raises a new marker and notifies again.
		 *
		 * @param int $product_id Product or variation id that came back in stock.
		 * @return string Marker for this restock.
		 */
		public static function mark_restock( $product_id ) {
			$product_id = absint( $product_id );
			if ( ! $product_id ) {
				return '';
			}

			$current = (string) get_post_meta( $product_id, self::PRODUCT_META, true );
			$is_new  = get_post_meta( $product_id, self::OPEN_META, true );

			if ( $current && ! $is_new ) {
				// Same restock reported again by the other detector.
				return $current;
			}

			// Time alone is not enough. Two restocks within the same second would
			// share a marker and the second one would notify nobody.
			$marker = time() . '-' . uniqid();
			update_post_meta( $product_id, self::PRODUCT_META, $marker );
			delete_post_meta( $product_id, self::OPEN_META );

			return $marker;
		}

		/**
		 * Note that a product is out of stock, so its next return to stock counts
		 * as a new restock. For a variable product managing stock for its
		 * variations, the variations are noted too, since subscribers are held
		 * against those.
		 *
		 * @param int             $product_id Product or variation id.
		 * @param WC_Product|null $product    Product object when already loaded.
		 */
		public static function mark_out_of_stock( $product_id, $product = null ) {
			$product_id = absint( $product_id );
			if ( ! $product_id ) {
				return;
			}

			update_post_meta( $product_id, self::OPEN_META, 1 );

			if ( ! $product instanceof WC_Product ) {
				$product = wc_get_product( $product_id );
			}
			if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) || ! $product->get_manage_stock() ) {
				return;
			}

			foreach ( $product->get_children() as $child_id ) {
				$variation = wc_get_product( $child_id );
				if ( $variation instanceof WC_Product_Variation && 'parent' === $variation->get_manage_stock() ) {
					update_post_meta( $child_id, self::OPEN_META, 1 );
				}
			}
		}

		/**
		 * Current marker for a product, without recording a new restock.
		 *
		 * @param int $product_id Product or variation id.
		 * @return string Marker, empty when the product has no recorded restock.
		 */
		public static function get_marker( $product_id ) {
			$product_id = absint( $product_id );
			if ( ! $product_id ) {
				return '';
			}

			return (string) get_post_meta( $product_id, self::PRODUCT_META, true );
		}

		/**
		 * Whether this subscriber has already been notified for the product's
		 * current restock.
		 *
		 * @param int $subscriber_id Subscriber post id.
		 * @param int $product_id    Product or variation id.
		 * @return bool
		 */
		public static function already_notified( $subscriber_id, $product_id ) {
			$marker = self::get_marker( $product_id );
			if ( '' === $marker ) {
				return false;
			}

			return (string) get_post_meta( $subscriber_id, self::SUBSCRIBER_META, true ) === $marker;
		}

		/**
		 * Record that this subscriber has been notified for the product's
		 * current restock.
		 *
		 * @param int $subscriber_id Subscriber post id.
		 * @param int $product_id    Product or variation id.
		 */
		public static function record_notified( $subscriber_id, $product_id ) {
			$marker = self::get_marker( $product_id );
			if ( '' === $marker ) {
				return;
			}

			update_post_meta( $subscriber_id, self::SUBSCRIBER_META, $marker );
		}

		/**
		 * Remove subscribers already notified for this restock.
		 *
		 * @param array $subscriber_ids Subscriber post ids.
		 * @param int   $product_id     Product or variation id that restocked.
		 * @return array Subscribers still waiting for this restock.
		 */
		public static function filter_pending( $subscriber_ids, $product_id ) {
			$subscriber_ids = array_filter( array_map( 'absint', (array) $subscriber_ids ) );
			if ( empty( $subscriber_ids ) ) {
				return array();
			}

			$marker = self::get_marker( $product_id );
			if ( '' === $marker ) {
				return $subscriber_ids;
			}

			$pending = array();
			foreach ( $subscriber_ids as $subscriber_id ) {
				// A subscriber carrying a bypass id was matched through the parent
				// product, so their own marker is kept against that id.
				$bypass = absint( get_post_meta( $subscriber_id, 'cwginstock_bypass_pid', true ) );
				$target = $bypass ? $bypass : $product_id;

				if ( (string) get_post_meta( $subscriber_id, self::SUBSCRIBER_META, true ) !== self::get_marker( $target ) ) {
					$pending[] = $subscriber_id;
				}
			}

			$skipped = count( $subscriber_ids ) - count( $pending );
			if ( $skipped > 0 ) {
				$logger = new CWG_Instock_Logger( 'info', "Restock guard: skipped $skipped subscriber(s) already notified for the current restock of product #$product_id" );
				$logger->record_log();
			}

			return $pending;
		}
	}
}
