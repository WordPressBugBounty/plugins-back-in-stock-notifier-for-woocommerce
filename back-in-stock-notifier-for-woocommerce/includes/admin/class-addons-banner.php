<?php
/**
 * Add-ons banner on the plugin's own admin screens.
 *
 * Shown only on Back In Stock Notifier screens (the subscriber list, the
 * settings and status pages and Estimate Stock Arrival), never on the
 * Plugins & Add-ons page itself, the dashboard or the Plugins screen. It is
 * hidden when the Bundle Add-ons plugin is active and can be dismissed per
 * user for 30 days.
 *
 * @package BackInStockNotifier
 * @since   7.4.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CWG_Instock_Addons_Banner' ) ) {

	class CWG_Instock_Addons_Banner {

		const DISMISS_ACTION = 'cwginstock_dismiss_addons_banner';
		const USER_META      = 'cwginstock_addons_banner_dismissed';
		const SNOOZE         = 30 * DAY_IN_SECONDS;
		const BUNDLE_FILE    = 'cwginstocknotifier-bundle/cwginstocknotifier-bundle.php';
		const SHOP_BASE      = 'https://propluginslab.io/shop/add-ons/back-in-stock-notifier/';

		public function __construct() {
			add_action( 'admin_notices', array( $this, 'render' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'admin_post_' . self::DISMISS_ACTION, array( $this, 'dismiss' ) );
		}

		/**
		 * Whether the current admin screen belongs to this plugin.
		 *
		 * @return bool
		 */
		private function is_plugin_screen() {
			if ( ! function_exists( 'get_current_screen' ) ) {
				return false;
			}
			$screen = get_current_screen();
			if ( ! $screen ) {
				return false;
			}

			// The Plugins & Add-ons page already promotes the add-ons.
			if ( false !== strpos( $screen->id, CWG_Instock_Promotions::PAGE_SLUG ) ) {
				return false;
			}

			if ( in_array( $screen->id, array( 'edit-cwginstocknotifier', 'edit-cwginstock_arrival', 'cwginstock_arrival' ), true ) ) {
				return true;
			}

			return 0 === strpos( $screen->id, 'cwginstocknotifier_page_' );
		}

		/**
		 * Whether the banner should be shown to the current user.
		 *
		 * @return bool
		 */
		private function should_show() {
			if ( ! current_user_can( 'manage_woocommerce' ) || ! $this->is_plugin_screen() ) {
				return false;
			}

			if ( ! function_exists( 'is_plugin_active' ) ) {
				include_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			if ( is_plugin_active( self::BUNDLE_FILE ) ) {
				return false;
			}

			/**
			 * Whether to show the bundle promotion.
			 *
			 * Returned false by the Bundle Add-ons plugin, so a customer who has
			 * already bought the bundle is not advertised it.
			 *
			 * @since 7.4.2
			 *
			 * @param bool $show Whether to show the promotion.
			 */
			if ( ! apply_filters( 'cwginstock_show_bundle_promotion', true ) ) {
				return false;
			}

			$dismissed = (int) get_user_meta( get_current_user_id(), self::USER_META, true );
			if ( $dismissed && ( time() - $dismissed ) < self::SNOOZE ) {
				return false;
			}

			return true;
		}

		public function enqueue_assets() {
			if ( ! $this->should_show() ) {
				return;
			}
			$css_path = CWGINSTOCK_PLUGINDIR . 'assets/css/addons-banner.css';
			$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : CWGINSTOCK_VERSION;
			wp_enqueue_style( 'cwg-bis-addons-banner', CWGINSTOCK_PLUGINURL . 'assets/css/addons-banner.css', array( 'dashicons' ), $css_ver );
		}

		/**
		 * Link to a product page on propluginslab.io.
		 *
		 * @param string $slug    Add-on slug in the shop.
		 * @param string $content UTM content value.
		 * @return string
		 */
		private function shop_url( $slug, $content ) {
			return add_query_arg(
				array(
					'utm_source'   => 'wp-plugin',
					'utm_medium'   => 'banner',
					'utm_campaign' => 'back-in-stock-notifier-for-woocommerce',
					'utm_content'  => $content,
				),
				self::SHOP_BASE . $slug . '/'
			);
		}

		/**
		 * Add-ons highlighted in the banner.
		 *
		 * @return array
		 */
		private function featured_addons() {
			return array(
				'twilio-sms'  => array(
					'name' => __( 'Twilio SMS', 'back-in-stock-notifier-for-woocommerce' ),
					'desc' => __( 'Send restock alerts by SMS as well as email.', 'back-in-stock-notifier-for-woocommerce' ),
				),
				'klaviyo'     => array(
					'name' => __( 'Klaviyo', 'back-in-stock-notifier-for-woocommerce' ),
					'desc' => __( 'Sync subscribers and trigger Klaviyo Flows on restock.', 'back-in-stock-notifier-for-woocommerce' ),
				),
				'doubleoptin' => array(
					'name' => __( 'Double Opt-In', 'back-in-stock-notifier-for-woocommerce' ),
					'desc' => __( 'Subscribers confirm by email before joining the waitlist.', 'back-in-stock-notifier-for-woocommerce' ),
				),
				'wpml'        => array(
					'name' => __( 'WPML', 'back-in-stock-notifier-for-woocommerce' ),
					'desc' => __( 'Send every email in the subscriber\'s own language.', 'back-in-stock-notifier-for-woocommerce' ),
				),
			);
		}

		public function render() {
			if ( ! $this->should_show() ) {
				return;
			}

			$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS_ACTION ), self::DISMISS_ACTION );
			$addons_page = admin_url( 'edit.php?post_type=cwginstocknotifier&page=' . CWG_Instock_Promotions::PAGE_SLUG );
			?>
			<div class="notice cwg-addons-banner">
				<a class="cwg-addons-banner-dismiss" href="<?php echo esc_url( $dismiss_url ); ?>" title="<?php esc_attr_e( 'Dismiss for 30 days', 'back-in-stock-notifier-for-woocommerce' ); ?>">
					<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice for 30 days', 'back-in-stock-notifier-for-woocommerce' ); ?></span>
				</a>
				<div class="cwg-addons-banner-main">
					<span class="cwg-addons-banner-icon dashicons dashicons-products" aria-hidden="true"></span>
					<div class="cwg-addons-banner-text">
						<strong><?php esc_html_e( 'Do more with your waitlist', 'back-in-stock-notifier-for-woocommerce' ); ?></strong>
						<span><?php esc_html_e( 'Get every Back In Stock Notifier add-on in one plugin with one licence key, including future add-ons, for a one-time $49.', 'back-in-stock-notifier-for-woocommerce' ); ?></span>
						<ul class="cwg-addons-banner-list">
							<?php foreach ( $this->featured_addons() as $slug => $addon ) : ?>
								<li>
									<span class="dashicons dashicons-yes" aria-hidden="true"></span>
									<a href="<?php echo esc_url( $this->shop_url( $slug, $slug ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $addon['name'] ); ?></a>
									- <?php echo esc_html( $addon['desc'] ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
				<div class="cwg-addons-banner-actions">
					<a class="cwg-addons-banner-btn" href="<?php echo esc_url( $this->shop_url( 'bundle-add-ons', 'bundle' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get Bundle Add-ons - $49', 'back-in-stock-notifier-for-woocommerce' ); ?></a>
					<a class="cwg-addons-banner-link" href="<?php echo esc_url( $addons_page ); ?>"><?php esc_html_e( 'Browse all add-ons', 'back-in-stock-notifier-for-woocommerce' ); ?></a>
				</div>
			</div>
			<?php
		}

		/**
		 * Remember the dismissal for the current user.
		 */
		public function dismiss() {
			check_admin_referer( self::DISMISS_ACTION );
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'back-in-stock-notifier-for-woocommerce' ), 403 );
			}

			update_user_meta( get_current_user_id(), self::USER_META, time() );

			$redirect = wp_get_referer();
			wp_safe_redirect( $redirect ? $redirect : admin_url( 'edit.php?post_type=cwginstocknotifier' ) );
			exit;
		}
	}

	new CWG_Instock_Addons_Banner();
}
