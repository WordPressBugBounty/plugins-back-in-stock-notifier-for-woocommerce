<?php
/**
 * Waitlist summary box on the product edit screen.
 *
 * Shows how many people are waiting for the product (and for each variation
 * of a variable product), how many have already been notified, and links to
 * the matching rows of the subscriber list. Read only, admin only, and off
 * until "Show Waitlist Summary on Product Edit Screen" is ticked in
 * Instock Notifier > Settings > Troubleshoot Settings.
 *
 * @package back-in-stock-notifier-for-woocommerce
 * @since   7.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CWG_Instock_Product_Waitlist_Box' ) ) {

	/**
	 * Product edit screen waitlist summary. Named like the plugin's other
	 * CWG_Instock_ classes.
	 */
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
	class CWG_Instock_Product_Waitlist_Box {

		/**
		 * Key inside the cwginstocksettings option.
		 */
		const SETTING = 'show_product_waitlist_box';

		/**
		 * Statuses counted in the box, in display order.
		 *
		 * @var string[]
		 */
		private $statuses = array( 'cwg_subscribed', 'cwg_queued', 'cwg_mailsent', 'cwg_converted', 'cwg_unsubscribed' );

		/**
		 * Hook the setting and the meta box.
		 */
		public function __construct() {
			add_action( 'cwginstock_register_settings', array( $this, 'add_settings_field' ), 1000 );
			add_action( 'add_meta_boxes_product', array( $this, 'add_meta_box' ) );
		}

		/**
		 * Is the box turned on?
		 *
		 * @return bool
		 */
		public static function is_enabled() {
			$options = get_option( 'cwginstocksettings' );
			return is_array( $options ) && isset( $options[ self::SETTING ] ) && '1' === (string) $options[ self::SETTING ];
		}

		/**
		 * Register the checkbox in the Troubleshoot Settings section.
		 */
		public function add_settings_field() {
			add_settings_field(
				'cwg_instock_show_product_waitlist_box',
				__( 'Show Waitlist Summary on Product Edit Screen', 'back-in-stock-notifier-for-woocommerce' ),
				array( $this, 'render_settings_field' ),
				'cwginstocknotifier_settings',
				'cwginstock_section_troubleshoot'
			);
		}

		/**
		 * Settings checkbox.
		 */
		public function render_settings_field() {
			?>
			<input type="checkbox" name="cwginstocksettings[<?php echo esc_attr( self::SETTING ); ?>]" value="1" <?php checked( self::is_enabled() ); ?> />
			<p><i>
					<?php esc_html_e( 'Select this option to show a "Back In Stock Waitlist" box on the product edit screen (Admin Dashboard > Products > Edit). It shows how many people are waiting for the product and each of its variations, how many were already notified, and links to those subscribers.', 'back-in-stock-notifier-for-woocommerce' ); ?>
				</i></p>
			<?php
		}

		/**
		 * Add the box to the product edit screen.
		 *
		 * @param WP_Post $post Product being edited.
		 */
		public function add_meta_box( $post ) {
			if ( ! self::is_enabled() || ! current_user_can( 'manage_woocommerce' ) ) {
				return;
			}
			if ( ! $post instanceof WP_Post || 'auto-draft' === $post->post_status ) {
				return;
			}

			add_meta_box(
				'cwginstock-product-waitlist',
				__( 'Back In Stock Waitlist', 'back-in-stock-notifier-for-woocommerce' ),
				array( $this, 'render_meta_box' ),
				'product',
				'side',
				'default'
			);
		}

		/**
		 * Count subscribers of one product, grouped by the product or variation
		 * they wait for and by status. One grouped query for the whole product.
		 *
		 * @param int $product_id Parent product ID.
		 * @return array<int, array<string, int>> Counts keyed by product or variation ID, then status.
		 */
		public function get_counts( $product_id ) {
			global $wpdb;

			$product_id = absint( $product_id );
			if ( ! $product_id ) {
				return array();
			}

			$cache_key = 'cwginstock_waitlist_box_' . $product_id;
			$rows      = wp_cache_get( $cache_key, 'cwginstock' );
			if ( false === $rows ) {
				// Read only summary of this plugin's own records for one product.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT pid.meta_value AS target_id, p.post_status AS status, COUNT( DISTINCT p.ID ) AS total
						FROM {$wpdb->posts} p
						INNER JOIN {$wpdb->postmeta} parent ON ( parent.post_id = p.ID AND parent.meta_key = 'cwginstock_product_id' )
						LEFT JOIN {$wpdb->postmeta} pid ON ( pid.post_id = p.ID AND pid.meta_key = 'cwginstock_pid' )
						WHERE p.post_type = 'cwginstocknotifier' AND parent.meta_value = %s
						GROUP BY pid.meta_value, p.post_status",
						(string) $product_id
					),
					ARRAY_A
				);
				wp_cache_set( $cache_key, $rows, 'cwginstock', MINUTE_IN_SECONDS );
			}

			$counts = array();
			foreach ( (array) $rows as $row ) {
				$target = absint( $row['target_id'] );
				if ( ! $target ) {
					$target = $product_id;
				}
				$status = sanitize_key( $row['status'] );
				if ( ! isset( $counts[ $target ][ $status ] ) ) {
					$counts[ $target ][ $status ] = 0;
				}
				$counts[ $target ][ $status ] += (int) $row['total'];
			}

			return $counts;
		}

		/**
		 * Link to the subscriber list filtered to one product or variation.
		 *
		 * @param int    $id     Product or variation ID.
		 * @param string $status Optional post status.
		 * @return string
		 */
		private function list_url( $id, $status = '' ) {
			$args = array(
				'post_type'                 => 'cwginstocknotifier',
				'cwg_filter_by_products[0]' => absint( $id ),
				'filter_action'             => 'Filter',
			);
			if ( '' !== $status ) {
				$args['post_status'] = $status;
			}
			return add_query_arg( $args, admin_url( 'edit.php' ) );
		}

		/**
		 * Label of a subscriber status.
		 *
		 * @param string $status Post status.
		 * @return string
		 */
		private function status_label( $status ) {
			$object = get_post_status_object( $status );
			if ( $object && ! empty( $object->label ) ) {
				return $object->label;
			}
			return $status;
		}

		/**
		 * Box content.
		 *
		 * @param WP_Post $post Product being edited.
		 */
		public function render_meta_box( $post ) {
			$product_id = absint( $post->ID );
			$counts     = $this->get_counts( $product_id );

			$totals = array_fill_keys( $this->statuses, 0 );
			foreach ( $counts as $by_status ) {
				foreach ( $by_status as $status => $total ) {
					if ( isset( $totals[ $status ] ) ) {
						$totals[ $status ] += $total;
					}
				}
			}

			if ( 0 === array_sum( $totals ) ) {
				echo '<p>' . esc_html__( 'Nobody has joined the waitlist for this product yet.', 'back-in-stock-notifier-for-woocommerce' ) . '</p>';
				return;
			}

			echo '<ul class="cwginstock-waitlist-totals">';
			foreach ( $totals as $status => $total ) {
				if ( 0 === $total && 'cwg_subscribed' !== $status ) {
					continue;
				}
				printf(
					'<li>%1$s: <a href="%2$s"><strong>%3$s</strong></a></li>',
					esc_html( $this->status_label( $status ) ),
					esc_url( $this->list_url( $product_id, $status ) ),
					esc_html( number_format_i18n( $total ) )
				);
			}
			echo '</ul>';

			// Variable products: who is waiting for which variation.
			$product = wc_get_product( $product_id );
			if ( $product && $product->is_type( 'variable' ) ) {
				$rows = array();
				foreach ( $counts as $target => $by_status ) {
					if ( $target === $product_id || empty( $by_status['cwg_subscribed'] ) ) {
						continue;
					}
					$variation = wc_get_product( $target );
					$rows[]    = array(
						'id'    => $target,
						'name'  => $variation ? wp_strip_all_tags( wc_get_formatted_variation( $variation, true, false, false ) ) : '#' . $target,
						'total' => (int) $by_status['cwg_subscribed'],
					);
				}

				if ( ! empty( $rows ) ) {
					usort(
						$rows,
						function ( $a, $b ) {
							return $b['total'] - $a['total'];
						}
					);
					echo '<p><strong>' . esc_html__( 'Waiting per variation', 'back-in-stock-notifier-for-woocommerce' ) . '</strong></p><ul>';
					foreach ( $rows as $row ) {
						printf(
							'<li>%1$s: <a href="%2$s">%3$s</a></li>',
							esc_html( '' !== $row['name'] ? $row['name'] : '#' . $row['id'] ),
							esc_url( $this->list_url( $row['id'], 'cwg_subscribed' ) ),
							esc_html( number_format_i18n( $row['total'] ) )
						);
					}
					echo '</ul>';
				}
			}

			printf(
				'<p><a class="button" href="%1$s">%2$s</a></p>',
				esc_url( $this->list_url( $product_id ) ),
				esc_html__( 'View all subscribers', 'back-in-stock-notifier-for-woocommerce' )
			);
		}
	}

	new CWG_Instock_Product_Waitlist_Box();
}
