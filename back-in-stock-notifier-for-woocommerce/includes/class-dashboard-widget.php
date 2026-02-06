<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CWG_Back_In_Stock_Dashboard_Widget' ) ) {

	class CWG_Back_In_Stock_Dashboard_Widget {

		const NONCE_ACTION = 'cwg_dashboard_range';

		/**
		 * Register widget
		 */
		public static function init() {

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				return;
			}

			wp_add_dashboard_widget(
				'cwg_back_in_stock_widget',
				__( '📊 Back In Stock Notifier for WooCommerce', 'back-in-stock-notifier-for-woocommerce' ),
				array( __CLASS__, 'render_widget' )
			);
		}

		/**
		 * Render widget
		 */
		public static function render_widget() {

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				return;
			}

			$range = self::get_selected_range();

			$count_all       = self::count_by_status( 'cwg_subscribed', $range );
			$count_mail      = self::count_by_status( 'cwg_mailsent', $range );
			$count_converted = self::count_by_status( 'cwg_converted', $range );
			$top_products    = self::get_top_products_list( 5, $range );
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
					font-size: 13px;
					line-height: 1.6;
				}
				.cwg-stat-box strong {
					display: block;
					font-size: 18px;
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
				.cwg-filter {
					margin-bottom: 10px;
				}
			</style>

			<form method="get" class="cwg-filter">
				<?php
				wp_nonce_field( self::NONCE_ACTION, '_cwg_nonce' );

				foreach ( $_GET as $key => $value ) {
					if ( in_array( $key, array( 'cwg_range', '_cwg_nonce' ), true ) ) {
						continue;
					}
					echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( wp_unslash( $value ) ) . '" />';
				}
				?>
				<select name="cwg_range" onchange="this.form.submit()">
					<option value="7" <?php selected( $range, '7' ); ?>><?php esc_html_e( 'Last 7 Days', 'back-in-stock-notifier-for-woocommerce' ); ?></option>
					<option value="30" <?php selected( $range, '30' ); ?>><?php esc_html_e( 'Last 30 Days', 'back-in-stock-notifier-for-woocommerce' ); ?></option>
					<option value="90" <?php selected( $range, '90' ); ?>><?php esc_html_e( 'Last 90 Days', 'back-in-stock-notifier-for-woocommerce' ); ?></option>
					<option value="all" <?php selected( $range, 'all' ); ?>><?php esc_html_e( 'All Time', 'back-in-stock-notifier-for-woocommerce' ); ?></option>
				</select>
			</form>

			<div class="cwg-dashboard-widget">
				<div class="cwg-stat-box">
					<span class="cwg-stat-icon">🧍</span>
					<?php esc_html_e( 'New Subscribers', 'back-in-stock-notifier-for-woocommerce' ); ?>
					<strong><?php echo esc_html( $count_all ); ?></strong>
				</div>

				<div class="cwg-stat-box">
					<span class="cwg-stat-icon">📩</span>
					<?php esc_html_e( 'Emails Sent', 'back-in-stock-notifier-for-woocommerce' ); ?>
					<strong><?php echo esc_html( $count_mail ); ?></strong>
				</div>

				<div class="cwg-stat-box">
					<span class="cwg-stat-icon">🛒</span>
					<?php esc_html_e( 'Converted', 'back-in-stock-notifier-for-woocommerce' ); ?>
					<strong><?php echo esc_html( $count_converted ); ?></strong>
				</div>

				<div class="cwg-stat-box cwg-stat-top">
					<span class="cwg-stat-icon">🏆</span>
					<?php esc_html_e( 'Most Wanted Products', 'back-in-stock-notifier-for-woocommerce' ); ?>
					<?php echo wp_kses_post( $top_products ); ?>
				</div>
			</div>
			<?php
		}

		/* ----------------------- SECURITY HELPERS ----------------------- */

		private static function get_selected_range() {

			$allowed = array( '7', '30', '90', 'all' );

			if ( empty( $_GET['cwg_range'] ) || empty( $_GET['_cwg_nonce'] ) ) {
				return '7';
			}

			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_cwg_nonce'] ) ), self::NONCE_ACTION ) ) {
				return '7';
			}

			$range = sanitize_text_field( wp_unslash( $_GET['cwg_range'] ) );

			return in_array( $range, $allowed, true ) ? $range : '7';
		}

		private static function get_date_query( $range ) {

			if ( 'all' === $range ) {
				return array();
			}

			return array(
				array(
					'after'     => absint( $range ) . ' days ago',
					'inclusive' => true,
					'column'    => 'post_modified',
				),
			);
		}

		private static function count_by_status( $status, $range ) {

			$args = array(
				'post_type'      => 'cwginstocknotifier',
				'post_status'    => sanitize_key( $status ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
			);

			$date_query = self::get_date_query( $range );
			if ( ! empty( $date_query ) ) {
				$args['date_query'] = $date_query;
			}

			$q = new WP_Query( $args );

			return absint( $q->found_posts );
		}

		private static function get_top_products_list( $limit = 5, $range = '7' ) {
			global $wpdb;

			$limit = absint( $limit );

			$where_date = '';
			$params     = array(
				'cwginstock_pid',
				'cwginstocknotifier',
				'trash',
			);

			if ( 'all' !== $range ) {
				$where_date = 'AND p.post_modified >= %s';
				$params[]   = gmdate( 'Y-m-d H:i:s', strtotime( '-' . absint( $range ) . ' days' ) );
			}

			$params[] = $limit;

			$sql = "
				SELECT pm.meta_value AS pid, COUNT(*) AS total
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s
				  AND p.post_type = %s
				  AND p.post_status != %s
				  {$where_date}
				GROUP BY pm.meta_value
				ORDER BY total DESC
				LIMIT %d
			";

			$results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore

			if ( empty( $results ) ) {
				return '<em>' . esc_html__( '— No data for selected period —', 'back-in-stock-notifier-for-woocommerce' ) . '</em>';
			}

			$out = '<ol style="margin:0;padding-left:1em;">';

			foreach ( $results as $row ) {

				$pid   = absint( $row->pid );
				$count = absint( $row->total );

				if ( 'product_variation' === get_post_type( $pid ) ) {
					$product = wc_get_product( $pid );
					$title   = $product ? wp_strip_all_tags( $product->get_formatted_name() ) : __( 'Variation', 'back-in-stock-notifier-for-woocommerce' );
				} else {
					$title = wp_strip_all_tags( get_the_title( $pid ) );
				}

				$out .= sprintf(
					'<li>%s <span style="color:#666;">(%d)</span></li>',
					esc_html( $title ),
					$count
				);
			}

			$out .= '</ol>';

			return $out;
		}
	}
}
