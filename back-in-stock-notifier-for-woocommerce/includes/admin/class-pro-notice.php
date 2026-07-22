<?php
/**
 * Pro Plugins promotional banner.
 *
 * A dismissible banner shown only on this plugin's own admin screens
 * (never globally, never on the Marketplace page itself). Pro plugin names
 * are pulled live from the same remote feed the Marketplace page uses, so
 * the banner stays in sync automatically. Dismissal is stored per user.
 *
 * @package BackInStockNotifier
 * @since   7.3.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CWG_Instock_Pro_Notice' ) ) {

	class CWG_Instock_Pro_Notice {

		const DISMISS_META  = 'cwg_bis_pro_notice_dismissed';
		const AJAX_ACTION   = 'cwg_bis_dismiss_pro_notice';
		const NONCE_ACTION  = 'cwg_bis_pro_notice_nonce';

		// Days a dismissal is remembered before the banner shows again.
		const DISMISS_DAYS  = 30;

		public function __construct() {
			add_action( 'admin_notices', array( $this, 'render' ) );
			add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_dismiss' ) );
		}

		/**
		 * Screens this plugin owns, minus the Marketplace page (redundant there).
		 *
		 * @return array
		 */
		private function allowed_screens() {
			return array(
				'edit-cwginstocknotifier',
				'cwginstocknotifier_page_cwg-instock-mailer',
				'cwginstocknotifier_page_cwg-instock-status',
			);
		}

		/**
		 * Should the banner show on the current request?
		 *
		 * @return bool
		 */
		private function should_show() {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				return false;
			}

			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! $screen || ! in_array( $screen->id, $this->allowed_screens(), true ) ) {
				return false;
			}

			// Dismissal is remembered for DISMISS_DAYS, then the banner returns.
			$dismissed_at = (int) get_user_meta( get_current_user_id(), self::DISMISS_META, true );
			if ( $dismissed_at > 0 && ( time() - $dismissed_at ) < ( self::DISMISS_DAYS * DAY_IN_SECONDS ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Up to four pro plugins from the cached feed.
		 *
		 * @return array
		 */
		private function get_pro_plugins() {
			if ( ! class_exists( 'CWG_Instock_Remote_Feed' ) ) {
				return array();
			}

			$products = CWG_Instock_Remote_Feed::get_products();
			$pro      = array();

			foreach ( $products as $product ) {
				$type = isset( $product['type'] ) ? $product['type'] : '';
				if ( 'pro' === $type && ! empty( $product['name'] ) ) {
					$pro[] = $product;
				}
			}

			return array_slice( $pro, 0, 6 );
		}

		/**
		 * Append UTM tracking params to a URL.
		 *
		 * @param string $url     Base URL.
		 * @param string $content utm_content value (product slug/name).
		 * @return string
		 */
		private function add_utm( $url, $content = '' ) {
			if ( empty( $url ) ) {
				return '';
			}

			$separator  = strpos( $url, '?' ) !== false ? '&' : '?';
			$utm_params = array(
				'utm_source=wordpress',
				'utm_medium=admin',
				'utm_campaign=pro-plugins-banner',
			);

			if ( '' !== $content ) {
				$utm_params[] = 'utm_content=' . rawurlencode( $content );
			}

			return $url . $separator . implode( '&', $utm_params );
		}

		/**
		 * External Pro Plugins category URL with UTM tracking.
		 *
		 * @return string
		 */
		private function pro_plugins_url() {
			return $this->add_utm( 'https://propluginslab.com/product-category/plugins/pro-plugins/', 'back-in-stock-notifier' );
		}

		/**
		 * Individual product URL with UTM tracking, falling back to the category.
		 *
		 * @param array $plugin Feed product entry.
		 * @return string
		 */
		private function product_url( $plugin ) {
			$base    = ! empty( $plugin['url'] ) ? $plugin['url'] : 'https://propluginslab.com/product-category/plugins/pro-plugins/';
			$content = ! empty( $plugin['slug'] )
				? $plugin['slug']
				: ( ! empty( $plugin['name'] ) ? sanitize_title( $plugin['name'] ) : 'pro-plugin' );

			return $this->add_utm( $base, $content );
		}

		public function render() {
			if ( ! $this->should_show() ) {
				return;
			}

			$pro_plugins      = $this->get_pro_plugins();
			$pro_plugins_url  = $this->pro_plugins_url();
			$nonce            = wp_create_nonce( self::NONCE_ACTION );
			?>
			<?php
			/* translators: %d: number of days the notice stays hidden */
			$dismiss_hint = sprintf( _n( 'Hide this for %d day', 'Hide this for %d days', self::DISMISS_DAYS, 'back-in-stock-notifier-for-woocommerce' ), self::DISMISS_DAYS );
			/* translators: %d: number of days the notice stays hidden */
			$dismiss_confirm = sprintf( _n( 'Got it. This notice will stay hidden for %d day, then show again.', 'Got it. This notice will stay hidden for %d days, then show again.', self::DISMISS_DAYS, 'back-in-stock-notifier-for-woocommerce' ), self::DISMISS_DAYS );
			?>
			<div class="notice notice-info cwg-pro-notice" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-confirm="<?php echo esc_attr( $dismiss_confirm ); ?>">
				<button type="button" class="notice-dismiss cwg-pro-notice-dismiss cwg-pro-dismiss" title="<?php echo esc_attr( $dismiss_hint ); ?>" aria-label="<?php echo esc_attr( $dismiss_hint ); ?>">
					<span class="screen-reader-text"><?php echo esc_html( $dismiss_hint ); ?></span>
				</button>

				<div class="cwg-pro-notice-body">
					<div class="cwg-pro-notice-icon" aria-hidden="true">
						<span class="dashicons dashicons-star-filled"></span>
					</div>

					<div class="cwg-pro-notice-text">
						<p class="cwg-pro-notice-title">
							<?php esc_html_e( 'Power up your store with our growing suite of Pro WooCommerce plugins by ProPluginsLab', 'back-in-stock-notifier-for-woocommerce' ); ?>
						</p>

						<?php if ( ! empty( $pro_plugins ) ) : ?>
						<p class="cwg-pro-notice-list">
							<?php foreach ( $pro_plugins as $plugin ) : ?>
								<a class="cwg-pro-chip" href="<?php echo esc_url( $this->product_url( $plugin ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $plugin['name'] ); ?></a>
							<?php endforeach; ?>
							<a class="cwg-pro-more" href="<?php echo esc_url( $pro_plugins_url ); ?>" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'View all pro plugins', 'back-in-stock-notifier-for-woocommerce' ); ?> &rarr;
							</a>
						</p>
						<?php else : ?>
						<p class="cwg-pro-notice-list">
							<?php esc_html_e( 'Multivendor, Advanced Shipment Tracking, Composite Products, Gift Cards, Fees, Name Your Price', 'back-in-stock-notifier-for-woocommerce' ); ?>
							<a class="cwg-pro-more" href="<?php echo esc_url( $pro_plugins_url ); ?>" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'View all pro plugins', 'back-in-stock-notifier-for-woocommerce' ); ?> &rarr;
							</a>
						</p>
						<?php endif; ?>

						<p class="cwg-pro-notice-actions">
							<a href="<?php echo esc_url( $pro_plugins_url ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Explore Pro Plugins', 'back-in-stock-notifier-for-woocommerce' ); ?>
							</a>
							<a href="#" class="cwg-pro-notice-dismiss-link cwg-pro-dismiss" title="<?php echo esc_attr( $dismiss_hint ); ?>">
								<?php echo esc_html( $dismiss_hint ); ?>
							</a>
							<span class="cwg-pro-notice-meta">
								<?php esc_html_e( 'One-time payment · Lifetime updates · No subscriptions', 'back-in-stock-notifier-for-woocommerce' ); ?>
							</span>
						</p>
					</div>
				</div>
			</div>

			<style>
				.cwg-pro-notice { border-left-color: #7f54b3; padding: 0; position: relative; }
				.cwg-pro-notice-body { display: flex; align-items: flex-start; gap: 14px; padding: 14px 32px 14px 14px; }
				.cwg-pro-notice-icon { flex: 0 0 auto; width: 40px; height: 40px; border-radius: 8px; background: linear-gradient(135deg, #7f54b3, #9b6fd4); display: flex; align-items: center; justify-content: center; }
				.cwg-pro-notice-icon .dashicons { color: #fff; font-size: 22px; width: 22px; height: 22px; }
				.cwg-pro-notice-text { flex: 1 1 auto; }
				.cwg-pro-notice-title { margin: 2px 0 8px; font-size: 14px; font-weight: 600; color: #1d2327; }
				.cwg-pro-notice-list { margin: 0 0 12px; line-height: 2; }
				.cwg-pro-chip { display: inline-block; background: #f3eefa; color: #5b3d8a; border: 1px solid #e2d5f2; border-radius: 13px; padding: 3px 12px; margin: 0 5px 5px 0; font-size: 12px; text-decoration: none; transition: background .15s ease, border-color .15s ease, color .15s ease; }
				a.cwg-pro-chip:hover { background: #e7d8f7; border-color: #c9b3ec; color: #4a2f78; }
				.cwg-pro-more { display: inline-block; margin: 0 0 5px 3px; font-size: 12px; font-weight: 600; color: #7f54b3; text-decoration: none; white-space: nowrap; }
				.cwg-pro-more:hover { color: #6a4599; text-decoration: underline; }
				.cwg-pro-notice-actions { margin: 4px 0 2px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
				.cwg-pro-notice-meta { color: #646970; font-size: 12px; }
				.cwg-pro-notice-dismiss-link { color: #646970; font-size: 12px; text-decoration: underline; cursor: pointer; }
				.cwg-pro-notice-dismiss-link:hover { color: #d63638; }
				.cwg-pro-notice-confirm { display: flex; align-items: center; gap: 8px; padding: 16px 32px 16px 14px; color: #1d2327; font-size: 13px; }
				.cwg-pro-notice-confirm .dashicons { color: #7f54b3; }
				@media screen and (max-width: 782px) { .cwg-pro-notice-body { flex-direction: column; } }
			</style>

			<script>
				( function () {
					var notice = document.querySelector( '.cwg-pro-notice' );
					if ( ! notice ) { return; }
					var triggers = notice.querySelectorAll( '.cwg-pro-dismiss' );
					if ( ! triggers.length ) { return; }
					function onDismiss( e ) {
						if ( e ) { e.preventDefault(); }
						// Persist the dismissal.
						var body = new URLSearchParams();
						body.append( 'action', '<?php echo esc_js( self::AJAX_ACTION ); ?>' );
						body.append( 'security', notice.getAttribute( 'data-nonce' ) );
						fetch( ajaxurl, {
							method: 'POST',
							credentials: 'same-origin',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: body.toString()
						} );

						// Clearly confirm how long it stays hidden, then fade out.
						var msg = notice.getAttribute( 'data-confirm' ) || '';
						var confirm = document.createElement( 'div' );
						confirm.className = 'cwg-pro-notice-confirm';
						confirm.setAttribute( 'role', 'status' );
						confirm.setAttribute( 'aria-live', 'polite' );
						confirm.innerHTML = '<span class="dashicons dashicons-yes"></span>';
						confirm.appendChild( document.createTextNode( ' ' + msg ) );
						notice.innerHTML = '';
						notice.appendChild( confirm );

						setTimeout( function () {
							notice.style.transition = 'opacity .4s ease';
							notice.style.opacity = '0';
							setTimeout( function () { notice.style.display = 'none'; }, 400 );
						}, 3000 );
					}
					for ( var i = 0; i < triggers.length; i++ ) {
						triggers[ i ].addEventListener( 'click', onDismiss );
					}
				} )();
			</script>
			<?php
		}

		/**
		 * Store the per-user dismissal.
		 */
		public function ajax_dismiss() {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( '', 403 );
			}

			if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), self::NONCE_ACTION ) ) {
				wp_send_json_error( '', 403 );
			}

			// Store the dismissal time so the banner can reappear after DISMISS_DAYS.
			update_user_meta( get_current_user_id(), self::DISMISS_META, time() );
			wp_send_json_success();
		}
	}

	new CWG_Instock_Pro_Notice();
}
