<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'CWG_Back_In_Stock_Dashboard_Widget' ) ) {
	// Ensure the class is not already defined to avoid redeclaration errors
	class CWG_Back_In_Stock_Dashboard_Widget {

		public static function init() {
			wp_add_dashboard_widget(
				'cwg_back_in_stock_widget',
				__( '📊 Back In Stock Notifier for WooCommerce - Last 7 Days', 'back-in-stock-notifier-for-woocommerce' ),
				array( __CLASS__, 'render_widget' )
			);
		}

		public static function render_widget() {
			$count_all = self::count_by_status( 'cwg_subscribed' );
			$count_mail = self::count_by_status( 'cwg_mailsent' );
			$count_purchased = self::count_by_status( 'cwg_converted' );
			$top_products_list = self::get_top_products_list();
			?>
			<style>
				.cwg-dashboard-widget {
					display: grid;
					grid-template-columns: 1fr 1fr;
					gap: 12px;
				}

				.cwg-stat-box {
					background: #f8f9fa;
					border-left: 4px solid #2271b1;
					padding: 12px 16px;
					border-radius: 4px;
					font-size: 14px;
					line-height: 1.6;
				}

				.cwg-stat-box strong {
					display: block;
					font-size: 16px;
					margin-top: 4px;
				}

				.cwg-stat-icon {
					font-size: 18px;
					margin-right: 6px;
				}

				.cwg-stat-top {
					grid-column: span 2;
					background: #fff4e5;
					border-left-color: #ff9900;
				}
			</style>

			<div class="cwg-dashboard-widget">
				<div class="cwg-stat-box">
					<span class="cwg-stat-icon">🧍
					</span>
					<?php esc_html_e( 'New Subscribers', 'back-in-stock-notifier-for-woocommerce' ); ?><strong><?php echo esc_html( $count_all ); ?></strong>
				</div>
				<div class="cwg-stat-box"><span class="cwg-stat-icon">📩 </span>
					<?php esc_html_e( 'Emails Sent', 'back-in-stock-notifier-for-woocommerce' ); ?>
					<strong><?php echo esc_html( $count_mail ); ?></strong>
				</div>
				<div class="cwg-stat-box"><span class="cwg-stat-icon">🛒 </span>
					<?php esc_html_e( 'Converted', 'back-in-stock-notifier-for-woocommerce' ); ?>
					<strong><?php echo esc_html( $count_purchased ); ?></strong>
				</div>
				<div class="cwg-stat-box cwg-stat-top">
					<span class="cwg-stat-icon">🏆 </span>
					<?php esc_html_e( 'Most Wanted Products', 'back-in-stock-notifier-for-woocommerce' ); ?><br>
					<strong><?php echo do_shortcode( $top_products_list ); ?></strong>
				</div>
			</div>
			<?php
		}

		private static function count_by_status( $status ) {
			$args = array(
				'post_type' => 'cwginstocknotifier',
				'posts_per_page' => 1,
				'fields' => 'ids',
				'no_found_rows' => false,
				'date_query' => array(
					array(
						'after' => '7 days ago',
						'inclusive' => true,
						'column' => 'post_modified',
					)
				),
			);

			if ( 'any' !== $status ) {
				$args['post_status'] = $status;
			}

			$q = new WP_Query( $args );
			return $q->found_posts;
		}

		private static function get_top_products_list( $limit = 5 ) {
			global $wpdb;

			$seven_days_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

			$query = $wpdb->prepare(
				"
                SELECT pm.meta_value AS pid, COUNT(*) AS count
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = %s
                  AND p.post_type = %s
                  AND p.post_status != %s
                  AND p.post_modified >= %s
                GROUP BY pm.meta_value
                ORDER BY count DESC
                LIMIT %d
                ",
				'cwginstock_pid',
				'cwginstocknotifier',
				'trash',
				$seven_days_ago,
				$limit
			);

			$results = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

			if ( empty( $results ) ) {
				return '<em>' . esc_html__( '— No data in last 7 days —', 'back-in-stock-notifier-for-woocommerce' ) . '</em>';
			}

			$output = '<ol style="margin:0; padding-left:1em;">';

			foreach ( $results as $row ) {
				$pid = (int) $row->pid;
				$count = (int) $row->count;
				$title = '';
				$product_obj = wc_get_product( $pid );
				if ( $product_obj ) {
					if ( get_post_type( $pid ) === 'product_variation' ) {
						$product = wc_get_product( $pid );
						if ( $product ) {
							// Strip HTML tags from the formatted variation name
							$title = wp_strip_all_tags( $product->get_formatted_name() );
						} else {
							/* translators: %d is the variation product ID */
							$title = sprintf( __( 'Variation #%d', 'back-in-stock-notifier-for-woocommerce' ), $pid );
						}
					} else {
						$title = get_the_title( $pid );
					}
				} else {
					/* translators: %d is the product ID */
					$title = sprintf( __( 'Deleted Product #%d', 'back-in-stock-notifier-for-woocommerce' ), $pid );

				}

				$output .= sprintf(
					'<li>%s <span style="color:#666;">(%d)</span></li>',
					esc_html( $title ),
					esc_html( $count )
				);
			}
			$output .= '</ol>';

			return $output;
		}

	}
}
