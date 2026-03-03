<?php
/**
 * Promotions & Extensions Page
 *
 * Dynamic page powered by remote feed data (fetched via Action Scheduler daily).
 * Shows ALL products in a single unified grid with two-level filter buttons.
 *
 * Layout:
 *  - Top-level type buttons: "All" | "Add-ons" | "Pro Plugins"
 *  - Subcategory filter buttons (context-aware):
 *    → Add-ons: General / Utilities / Integrations / Design / etc.
 *    → Pro Plugins: Auto-generated categories from product names
 *  - Single unified card grid
 *  - Cards show/hide via JS based on active filters
 *
 * Pro Plugin auto-categorization:
 *  Generates categories automatically from the product name using keyword matching.
 *  e.g. "Composite Products" → Products, "Fees for WooCommerce" → Pricing,
 *  "Gift Cards" → Marketing, "Name Your Price" → Pricing
 *
 * @package BackInStockNotifier
 * @since   7.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CWG_Instock_Promotions' ) ) {

	class CWG_Instock_Promotions {

		const PAGE_SLUG = 'cwg-instock-extensions';

		public function __construct() {
			add_action( 'admin_menu', array( $this, 'add_menu' ), 999 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		}

		public function add_menu() {
			add_submenu_page(
				'edit.php?post_type=cwginstocknotifier',
				__( 'Extensions & Add-ons', 'back-in-stock-notifier-for-woocommerce' ),
				__( 'Extensions', 'back-in-stock-notifier-for-woocommerce' ),
				'manage_woocommerce',
				self::PAGE_SLUG,
				array( $this, 'render_page' )
			);
		}

		public function enqueue_assets( $hook ) {
			if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
				return;
			}

			wp_enqueue_style(
				'cwg-bis-promotions',
				CWGINSTOCK_PLUGINURL . 'assets/css/promotions.css',
				array(),
				CWGINSTOCK_VERSION
			);

			wp_enqueue_script(
				'cwg-bis-promotions',
				CWGINSTOCK_PLUGINURL . 'assets/js/promotions.js',
				array( 'jquery' ),
				CWGINSTOCK_VERSION,
				true
			);

			wp_localize_script(
				'cwg-bis-promotions',
				'cwgPromotions',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( CWG_Instock_Remote_Feed::NONCE_ACTION ),
					'action'   => CWG_Instock_Remote_Feed::AJAX_ACTION,
					'i18n'     => array(
						'refreshing'   => __( 'Refreshing...', 'back-in-stock-notifier-for-woocommerce' ),
						'refresh_feed' => __( 'Refresh Feed', 'back-in-stock-notifier-for-woocommerce' ),
						'error'        => __( 'Something went wrong. Please try again.', 'back-in-stock-notifier-for-woocommerce' ),
					),
				)
			);
		}

		/* ================================================================
		 * Helper: activation status detection
		 * ================================================================ */

		private function is_bundle_active() {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				include_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			return is_plugin_active( 'cwginstocknotifier-bundle/cwginstocknotifier-bundle.php' );
		}

		private function is_plugin_active_by_product( $product ) {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				include_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			if ( ! empty( $product['plugin_file'] ) ) {
				return is_plugin_active( $product['plugin_file'] );
			}
			$slug = $product['slug'];
			if ( empty( $slug ) ) {
				return false;
			}
			return is_plugin_active( $slug . '/' . $slug . '.php' );
		}

		private function get_activation_status( $product, $bundle_active ) {
			$slug = isset( $product['slug'] ) ? $product['slug'] : '';
			$type = isset( $product['type'] ) ? $product['type'] : 'addon';

			if ( $this->is_plugin_active_by_product( $product ) ) {
				return 'active';
			}
			if ( 'cwginstocknotifier-bundle' === $slug && $bundle_active ) {
				return 'active';
			}
			if ( 'addon' === $type && $bundle_active && 'cwginstocknotifier-bundle' !== $slug ) {
				if ( 0 === strpos( $slug, 'cwginstocknotifier-' ) || 0 === strpos( $slug, 'cwginstock' ) ) {
					return 'bundle_activated';
				}
			}
			return '';
		}

		/* ================================================================
		 * Category definitions for Add-ons (explicit)
		 * ================================================================ */

		private function addon_category_labels() {
			return array(
				'bundle'        => __( 'Bundle', 'back-in-stock-notifier-for-woocommerce' ),
				'integrations'  => __( 'Integrations', 'back-in-stock-notifier-for-woocommerce' ),
				'notifications' => __( 'Notifications', 'back-in-stock-notifier-for-woocommerce' ),
				'compliance'    => __( 'Compliance & Privacy', 'back-in-stock-notifier-for-woocommerce' ),
				'utilities'     => __( 'Utilities', 'back-in-stock-notifier-for-woocommerce' ),
				'analytics'     => __( 'Analytics', 'back-in-stock-notifier-for-woocommerce' ),
				'design'        => __( 'Design', 'back-in-stock-notifier-for-woocommerce' ),
				'multilingual'  => __( 'Multilingual', 'back-in-stock-notifier-for-woocommerce' ),
				'general'       => __( 'General', 'back-in-stock-notifier-for-woocommerce' ),
			);
		}

		private function addon_category_order() {
			return array( 'bundle', 'integrations', 'notifications', 'compliance', 'utilities', 'analytics', 'design', 'multilingual', 'general' );
		}

		private function category_icon( $cat ) {
			$map = array(
				'bundle'        => 'dashicons-products',
				'integrations'  => 'dashicons-networking',
				'notifications' => 'dashicons-bell',
				'compliance'    => 'dashicons-shield',
				'utilities'     => 'dashicons-admin-tools',
				'analytics'     => 'dashicons-chart-bar',
				'design'        => 'dashicons-art',
				'multilingual'  => 'dashicons-translation',
				'general'       => 'dashicons-admin-plugins',
				// Pro plugin auto-categories
				'products'      => 'dashicons-archive',
				'pricing'       => 'dashicons-money-alt',
				'marketing'     => 'dashicons-megaphone',
				'checkout'      => 'dashicons-cart',
				'shipping'      => 'dashicons-car',
				'inventory'     => 'dashicons-clipboard',
			);
			return isset( $map[ $cat ] ) ? $map[ $cat ] : 'dashicons-admin-plugins';
		}

		/* ================================================================
		 * Auto-categorize Pro Plugins from product name
		 * ================================================================ */

		/**
		 * Auto-assign a category to a pro plugin based on its product name.
		 *
		 * Uses keyword matching against the product name to determine
		 * the most relevant category. Falls back to 'general'.
		 *
		 * @param string $name Product name.
		 * @return string Category slug.
		 */
		private function auto_categorize_pro( $name ) {
			$name_lower = strtolower( $name );

			// Keyword → category mapping (first match wins)
			$rules = array(
				'products'  => array( 'composite', 'bundle', 'product kit', 'grouped', 'product box' ),
				'pricing'   => array( 'fee', 'fees', 'surcharge', 'name your price', 'pay what you want', 'dynamic pricing', 'discount', 'price' ),
				'marketing' => array( 'gift card', 'coupon', 'loyalty', 'reward', 'referral', 'points', 'voucher' ),
				'checkout'  => array( 'checkout', 'payment', 'gateway', 'cart', 'order' ),
				'shipping'  => array( 'shipping', 'delivery', 'freight' ),
				'inventory' => array( 'stock', 'inventory', 'warehouse', 'backorder' ),
			);

			foreach ( $rules as $category => $keywords ) {
				foreach ( $keywords as $keyword ) {
					if ( false !== strpos( $name_lower, $keyword ) ) {
						return $category;
					}
				}
			}

			return 'general';
		}

		/**
		 * Get human-readable label for an auto-generated pro category.
		 */
		private function pro_category_label( $slug ) {
			$labels = array(
				'products'  => __( 'Products', 'back-in-stock-notifier-for-woocommerce' ),
				'pricing'   => __( 'Pricing', 'back-in-stock-notifier-for-woocommerce' ),
				'marketing' => __( 'Marketing', 'back-in-stock-notifier-for-woocommerce' ),
				'checkout'  => __( 'Checkout', 'back-in-stock-notifier-for-woocommerce' ),
				'shipping'  => __( 'Shipping', 'back-in-stock-notifier-for-woocommerce' ),
				'inventory' => __( 'Inventory', 'back-in-stock-notifier-for-woocommerce' ),
				'general'   => __( 'General', 'back-in-stock-notifier-for-woocommerce' ),
			);
			return isset( $labels[ $slug ] ) ? $labels[ $slug ] : ucfirst( $slug );
		}

		/**
		 * Get category label for any slug (addon or pro).
		 */
		private function get_category_label( $slug, $type = 'addon' ) {
			if ( 'pro' === $type ) {
				return $this->pro_category_label( $slug );
			}
			$labels = $this->addon_category_labels();
			return isset( $labels[ $slug ] ) ? $labels[ $slug ] : ucfirst( $slug );
		}

		/* ================================================================
		 * Card rendering
		 * ================================================================ */

		private function render_card( $product, $status ) {
			$has_sale    = ! empty( $product['discount_active'] );
			$sale_price  = $has_sale && ! empty( $product['discount_price'] ) ? $product['discount_price'] : '';
			$badge_text  = ! empty( $product['badge'] ) ? $product['badge'] : '';
			$product_url = ! empty( $product['url'] ) ? $product['url'] : 'https://propluginslab.com/';
			$type        = isset( $product['type'] ) ? $product['type'] : 'addon';
			$category    = ! empty( $product['category'] ) ? $product['category'] : 'general';

			$btn_label = ( 'pro' === $type )
				? __( 'View Plugin', 'back-in-stock-notifier-for-woocommerce' )
				: __( 'Get Add-on', 'back-in-stock-notifier-for-woocommerce' );

			$classes = array( 'cwg-promo-card' );
			if ( 'pro' === $type ) {
				$classes[] = 'cwg-pro-card';
			}
			if ( $has_sale && ! $status ) {
				$classes[] = 'cwg-promo-card--sale';
			}
			if ( $status ) {
				$classes[] = 'cwg-promo-card--' . esc_attr( $status );
			}
			?>
			<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
				 data-type="<?php echo esc_attr( $type ); ?>"
				 data-category="<?php echo esc_attr( $category ); ?>">

				<?php if ( 'pro' === $type && ! $status && ! $badge_text ) : ?>
				<div class="cwg-promo-type-ribbon cwg-promo-type-ribbon--pro">
					<span class="dashicons dashicons-star-filled"></span>
					<?php esc_html_e( 'Pro Plugin', 'back-in-stock-notifier-for-woocommerce' ); ?>
				</div>
				<?php elseif ( $status ) : ?>
				<div class="cwg-promo-status cwg-promo-status--<?php echo esc_attr( $status ); ?>">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php
					if ( 'active' === $status ) {
						esc_html_e( 'Active', 'back-in-stock-notifier-for-woocommerce' );
					} elseif ( 'bundle_activated' === $status ) {
						esc_html_e( 'Bundle Activated', 'back-in-stock-notifier-for-woocommerce' );
					}
					?>
				</div>
				<?php elseif ( $badge_text ) : ?>
				<div class="cwg-promo-badge cwg-promo-badge--<?php echo esc_attr( sanitize_html_class( strtolower( str_replace( ' ', '-', $badge_text ) ) ) ); ?>">
					<?php echo esc_html( $badge_text ); ?>
				</div>
				<?php elseif ( $has_sale ) : ?>
				<div class="cwg-promo-badge cwg-promo-badge--sale">
					<?php esc_html_e( 'Sale', 'back-in-stock-notifier-for-woocommerce' ); ?>
				</div>
				<?php endif; ?>

				<div class="cwg-promo-card-body">
					<div class="cwg-promo-card-icon">
						<?php if ( ! empty( $product['icon_url'] ) ) : ?>
						<img src="<?php echo esc_url( $product['icon_url'] ); ?>" alt="<?php echo esc_attr( $product['name'] ); ?>" width="48" height="48" loading="lazy">
						<?php else : ?>
						<span class="dashicons <?php echo esc_attr( $this->category_icon( $category ) ); ?>"></span>
						<?php endif; ?>
					</div>

					<div class="cwg-promo-card-content">
						<h3 class="cwg-promo-card-title"><?php echo esc_html( $product['name'] ); ?></h3>

						<?php if ( ! empty( $product['description'] ) ) : ?>
						<p class="cwg-promo-card-desc"><?php echo esc_html( $product['description'] ); ?></p>
						<?php endif; ?>

						<span class="cwg-promo-card-cat-tag">
							<span class="dashicons <?php echo esc_attr( $this->category_icon( $category ) ); ?>"></span>
							<?php echo esc_html( $this->get_category_label( $category, $type ) ); ?>
						</span>
					</div>
				</div>

				<div class="cwg-promo-card-footer">
					<div class="cwg-promo-card-pricing">
						<?php if ( $has_sale && $sale_price && ! $status ) : ?>
							From:
						<span class="cwg-promo-price cwg-promo-price--old">
							<?php echo esc_html( '$' . $product['price'] ); ?>
						</span>
						<span class="cwg-promo-price cwg-promo-price--sale">
							<?php echo esc_html( '$' . $sale_price ); ?>
						</span>
						<?php elseif ( ! $status ) : ?>
							From:
						<span class="cwg-promo-price">
							<?php echo esc_html( '$' . $product['price'] ); ?>
						</span>
						<?php endif; ?>
					</div>

					<?php if ( $status ) : ?>
					<span class="cwg-promo-active-label cwg-promo-active-label--<?php echo esc_attr( $status ); ?>">
						<span class="dashicons dashicons-saved"></span>
						<?php
						if ( 'active' === $status ) {
							esc_html_e( 'Installed & Active', 'back-in-stock-notifier-for-woocommerce' );
						} elseif ( 'bundle_activated' === $status ) {
							esc_html_e( 'Included in Bundle', 'back-in-stock-notifier-for-woocommerce' );
						}
						?>
					</span>
					<?php else : ?>
					<a href="<?php echo esc_url( $product_url ); ?>" target="_blank" rel="noopener noreferrer" class="cwg-promo-card-btn">
						<?php echo esc_html( $btn_label ); ?>
						<span class="dashicons dashicons-external"></span>
					</a>
					<?php endif; ?>
				</div>
			</div>
			<?php
		}

		/* ================================================================
		 * Render the entire page
		 * ================================================================ */

		public function render_page() {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( esc_html__( 'You do not have permission to access this page.', 'back-in-stock-notifier-for-woocommerce' ) );
			}

			$bundle_active = $this->is_bundle_active();
			$all_products  = CWG_Instock_Remote_Feed::get_products();
			$feed_url      = CWG_Instock_Remote_Feed::get_feed_url();
			$last_fetch    = CWG_Instock_Remote_Feed::get_last_fetch_time();
			$has_products  = ! empty( $all_products );

			// ── Classify products and build category counts ──
			$addon_count = 0;
			$pro_count   = 0;
			$addon_cats  = array();  // slug => count
			$pro_cats    = array();  // slug => count

			foreach ( $all_products as $idx => $product ) {
				$type = isset( $product['type'] ) ? $product['type'] : 'addon';
				$cat  = ! empty( $product['category'] ) ? $product['category'] : 'general';

				if ( 'pro' === $type ) {
					// Auto-categorize pro plugins from product name
					$auto_cat = $this->auto_categorize_pro( $product['name'] );
					// Override the stored category with auto-generated one
					$all_products[ $idx ]['category'] = $auto_cat;
					$cat                              = $auto_cat;

					$pro_count++;
					if ( ! isset( $pro_cats[ $cat ] ) ) {
						$pro_cats[ $cat ] = 0;
					}
					$pro_cats[ $cat ]++;
				} else {
					$addon_count++;
					if ( ! isset( $addon_cats[ $cat ] ) ) {
						$addon_cats[ $cat ] = 0;
					}
					$addon_cats[ $cat ]++;
				}
			}

			// Order addon cats using defined order
			$ordered_addon_cats = array();
			foreach ( $this->addon_category_order() as $cat_slug ) {
				if ( isset( $addon_cats[ $cat_slug ] ) ) {
					$ordered_addon_cats[ $cat_slug ] = $addon_cats[ $cat_slug ];
				}
			}
			foreach ( $addon_cats as $cat_slug => $count ) {
				if ( ! isset( $ordered_addon_cats[ $cat_slug ] ) ) {
					$ordered_addon_cats[ $cat_slug ] = $count;
				}
			}

			// Sort pro cats alphabetically
			ksort( $pro_cats );

			$active_count = 0;
			foreach ( $all_products as $product ) {
				if ( $this->get_activation_status( $product, $bundle_active ) ) {
					$active_count++;
				}
			}

			$addon_labels = $this->addon_category_labels();
			?>
			<div class="wrap cwg-promo-wrap">

				<!-- Header -->
				<div class="cwg-promo-header">
					<div class="cwg-promo-header-left">
						<h1><?php esc_html_e( 'Extensions & Add-ons', 'back-in-stock-notifier-for-woocommerce' ); ?></h1>
						<p class="cwg-promo-subtitle">
							<strong><?php esc_html_e( 'Supercharge your Back In Stock Notifier with powerful add-ons and premium plugins.', 'back-in-stock-notifier-for-woocommerce' ); ?></strong>
						</p>
						<p class="cwg-promo-subtitle">
							<i><?php esc_html_e('Enjoy one-time pricing with zero recurring charges - buy once and use forever on your licensed sites.', 'back-in-stock-notifier-for-woocommerce'); ?></i>
						</p>
					</div>
					<div class="cwg-promo-header-right">
						<?php if ( $has_products ) : ?>
						<div class="cwg-promo-stats">
							<span class="cwg-stat">
								<strong><?php echo count( $all_products ); ?></strong> <?php esc_html_e( 'Total', 'back-in-stock-notifier-for-woocommerce' ); ?>
							</span>
							<?php if ( $active_count > 0 ) : ?>
							<span class="cwg-stat cwg-stat--active">
								<strong><?php echo absint( $active_count ); ?></strong> <?php esc_html_e( 'Active', 'back-in-stock-notifier-for-woocommerce' ); ?>
							</span>
							<?php endif; ?>
						</div>
						<?php endif; ?>
						<?php if ( $last_fetch ) : ?>
						<span class="cwg-last-fetched">
							<?php
							echo esc_html(
								sprintf(
									__( 'Updated %s ago', 'back-in-stock-notifier-for-woocommerce' ),
									human_time_diff( $last_fetch, time() )
								)
							);
							?>
						</span>
						<?php endif; ?>
						<?php if ( $has_products || ! empty( $feed_url ) ) : ?>
						<button type="button" id="cwg-refresh-feed" class="button button-secondary">
							<span class="dashicons dashicons-update"></span>
							<span class="cwg-btn-text"><?php esc_html_e( 'Refresh', 'back-in-stock-notifier-for-woocommerce' ); ?></span>
						</button>
						<?php endif; ?>
					</div>
				</div>

				<div id="cwg-promo-notice" class="notice" style="display:none;"><p></p></div>

				<?php if ( $bundle_active ) : ?>
				<div class="cwg-promo-bundle-banner">
					<div class="cwg-promo-bundle-icon">
						<span class="dashicons dashicons-yes-alt"></span>
					</div>
					<div class="cwg-promo-bundle-info">
						<strong><?php esc_html_e( 'Bundle Add-ons Active', 'back-in-stock-notifier-for-woocommerce' ); ?></strong>
						<p><?php esc_html_e( 'All individual add-ons are included in your bundle and ready to use.', 'back-in-stock-notifier-for-woocommerce' ); ?></p>
					</div>
				</div>
				<?php endif; ?>

				<?php if ( ! $has_products ) : ?>
				<div class="cwg-promo-empty">
					<div class="cwg-promo-empty-icon"><span class="dashicons dashicons-store"></span></div>
					<h2><?php esc_html_e( 'No extensions loaded yet', 'back-in-stock-notifier-for-woocommerce' ); ?></h2>
					<p>
						<?php if ( empty( $feed_url ) ) : ?>
							<?php esc_html_e( 'The remote feed URL is not configured. Please set it in Settings, or browse extensions on our website.', 'back-in-stock-notifier-for-woocommerce' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Extensions will be loaded automatically via daily sync. Click "Refresh" above to load now, or visit our website.', 'back-in-stock-notifier-for-woocommerce' ); ?>
						<?php endif; ?>
					</p>
					<a href="https://propluginslab.com/" target="_blank" class="button button-primary button-hero">
						<?php esc_html_e( 'Visit ProPluginsLab', 'back-in-stock-notifier-for-woocommerce' ); ?>
					</a>
				</div>
				<?php else : ?>

				<!-- ═══════ TYPE FILTER BUTTONS ═══════ -->
				<div class="cwg-promo-type-buttons">
					<button type="button" class="cwg-type-btn cwg-type-btn--all active" data-type="all">
						<span class="dashicons dashicons-screenoptions"></span>
						<?php esc_html_e( 'All', 'back-in-stock-notifier-for-woocommerce' ); ?>
						<span class="cwg-btn-count"><?php echo count( $all_products ); ?></span>
					</button>
					<button type="button" class="cwg-type-btn cwg-type-btn--addon" data-type="addon">
						<span class="dashicons dashicons-admin-plugins"></span>
						<?php esc_html_e( 'Add-ons', 'back-in-stock-notifier-for-woocommerce' ); ?>
						<span class="cwg-btn-count"><?php echo absint( $addon_count ); ?></span>
					</button>
					<button type="button" class="cwg-type-btn cwg-type-btn--pro" data-type="pro">
						<span class="dashicons dashicons-star-filled"></span>
						<?php esc_html_e( 'Pro Plugins', 'back-in-stock-notifier-for-woocommerce' ); ?>
						<span class="cwg-btn-count"><?php echo absint( $pro_count ); ?></span>
					</button>
				</div>

				<!-- ═══════ SUBCATEGORY FILTER BUTTONS (Add-ons) ═══════ -->
					<?php if ( ! empty( $ordered_addon_cats ) ) : ?>
				<div class="cwg-promo-subcats cwg-promo-subcats--addon" id="cwg-subcats-addon">
					<button type="button" class="cwg-subcat-btn active" data-category="all" data-for="addon">
						<?php esc_html_e( 'All', 'back-in-stock-notifier-for-woocommerce' ); ?>
						<span class="cwg-btn-count"><?php echo absint( $addon_count ); ?></span>
					</button>
						<?php foreach ( $ordered_addon_cats as $cat_slug => $count ) : ?>
					<button type="button" class="cwg-subcat-btn" data-category="<?php echo esc_attr( $cat_slug ); ?>" data-for="addon">
						<span class="dashicons <?php echo esc_attr( $this->category_icon( $cat_slug ) ); ?>"></span>
							<?php echo esc_html( isset( $addon_labels[ $cat_slug ] ) ? $addon_labels[ $cat_slug ] : ucfirst( $cat_slug ) ); ?>
						<span class="cwg-btn-count"><?php echo absint( $count ); ?></span>
					</button>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<!-- ═══════ SUBCATEGORY FILTER BUTTONS (Pro Plugins) ═══════ -->
					<?php if ( ! empty( $pro_cats ) ) : ?>
				<div class="cwg-promo-subcats cwg-promo-subcats--pro" id="cwg-subcats-pro" style="display:none;">
					<button type="button" class="cwg-subcat-btn active" data-category="all" data-for="pro">
						<?php esc_html_e( 'All', 'back-in-stock-notifier-for-woocommerce' ); ?>
						<span class="cwg-btn-count"><?php echo absint( $pro_count ); ?></span>
					</button>
						<?php foreach ( $pro_cats as $cat_slug => $count ) : ?>
					<button type="button" class="cwg-subcat-btn cwg-subcat-btn--pro" data-category="<?php echo esc_attr( $cat_slug ); ?>" data-for="pro">
						<span class="dashicons <?php echo esc_attr( $this->category_icon( $cat_slug ) ); ?>"></span>
							<?php echo esc_html( $this->pro_category_label( $cat_slug ) ); ?>
						<span class="cwg-btn-count"><?php echo absint( $count ); ?></span>
					</button>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<!-- ═══════ UNIFIED GRID ═══════ -->
				<div class="cwg-promo-grid" id="cwg-promo-grid">
					<?php 
					foreach ( $all_products as $product ) :
						$status = $this->get_activation_status( $product, $bundle_active );
						$this->render_card( $product, $status );
					endforeach; 
					?>
				</div>

				<?php endif; ?>

				<div class="cwg-promo-footer">
					<p>
						<?php esc_html_e( 'Need help? Visit', 'back-in-stock-notifier-for-woocommerce' ); ?>
						<a href="https://support.codewoogeek.online" target="_blank"><?php esc_html_e( 'ProPluginsLab Support', 'back-in-stock-notifier-for-woocommerce' ); ?></a>
					</p>
				</div>

			</div>
			<?php
		}
	}

	new CWG_Instock_Promotions();
}
