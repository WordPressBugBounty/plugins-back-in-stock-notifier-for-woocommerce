<?php
if (! defined('ABSPATH')) {
	exit;
}

if (! class_exists('CWG_Instock_Settings')) {

	class CWG_Instock_Settings {
	


		private $api;

		public function __construct() {
			add_action('admin_menu', array($this, 'add_settings_menu'));
			add_action('admin_init', array($this, 'register_manage_settings'));
			add_action('admin_init', array($this, 'default_value'));
			$this->api = new CWG_Instock_API();
		}

		public function add_settings_menu() {
			add_submenu_page('edit.php?post_type=cwginstocknotifier', __('Settings', 'back-in-stock-notifier-for-woocommerce'), __('Settings', 'back-in-stock-notifier-for-woocommerce'), 'manage_woocommerce', 'cwg-instock-mailer', array($this, 'manage_settings'));
		}

		public function manage_settings() {
			echo "<div class='wrap'>";
			settings_errors();
			?>
			<form action='options.php' method='post' id="cwginstocknotifier_settings">

				<h1>
					<?php esc_html_e('Back In Stock Notifier for WooCommerce Settings', 'back-in-stock-notifier-for-woocommerce'); ?>
				</h1>
				<div class="notice notice-success cwg_marketing_notice">
					<p>
						<strong><?php esc_html_e( 'Supercharge your store!', 'back-in-stock-notifier-for-woocommerce' ); ?></strong>
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: %s: URL to extensions page */
								__( 'Check out our <a href="%s"><strong>Extensions & Add-ons</strong></a> to unlock more features.', 'back-in-stock-notifier-for-woocommerce' ),
								esc_url( admin_url( 'edit.php?post_type=cwginstocknotifier&page=cwg-instock-extensions' ) )
							)
						);
						?>
					</p>
				</div>
				<?php
				settings_fields('cwginstocknotifier_settings');
				/**
				 * Action before the setting section
				 *
				 * @since 1.0.0
				 */
				do_action('cwginstocksettings_before_section');
				// get settings tab
				$settings_ui = get_option('cwginstock_backend_ui', 'tabbed_ui');
				if ('tabbed_ui' == $settings_ui) {
					do_tabbed_settings_sections('cwginstocknotifier_settings');
				} else {
					do_settings_sections('cwginstocknotifier_settings');
				}
				submit_button();
				?>
			</form>
			<?php
			echo '</div>';
		}

		public function register_manage_settings() {
			// phpcs:ignore PluginCheck.CodeAnalysis.SettingSanitization.register_settingDynamic
			register_setting('cwginstocknotifier_settings', 'cwginstocksettings', array($this, 'sanitize_data'));
			add_settings_section('cwginstock_section', __('Frontend Form', 'back-in-stock-notifier-for-woocommerce'), array($this, 'section_heading'), 'cwginstocknotifier_settings');
			add_settings_field('cwg_frontend_displayform_type', __('Frontend Subscribe Form Display Type', 'back-in-stock-notifier-for-woocommerce'), array($this, 'cwg_frontend_displayform'), 'cwginstocknotifier_settings', 'cwginstock_section');
			add_settings_field('cwg_instock_form_design', __('Subscribe Form Design', 'back-in-stock-notifier-for-woocommerce'), array($this, 'form_design_field'), 'cwginstocknotifier_settings', 'cwginstock_section');
			add_settings_field('cwg_instock_popup_design', __('Popup Design', 'back-in-stock-notifier-for-woocommerce'), array($this, 'popup_design_field'), 'cwginstocknotifier_settings', 'cwginstock_section');
			add_settings_field('cwg_instock_form_title', __('Title for Subscribe Form', 'back-in-stock-notifier-for-woocommerce'), array($this, 'form_title'), 'cwginstocknotifier_settings', 'cwginstock_section');
			add_settings_field('cwg_instock_name_placeholder', __('Placeholder for Name Field', 'back-in-stock-notifier-for-woocommerce'), array($this, 'form_name_placeholder'), 'cwginstocknotifier_settings', 'cwginstock_section');

			add_settings_field('cwg_instock_form_placeholder', __('Placeholder for Email Field', 'back-in-stock-notifier-for-woocommerce'), array($this, 'form_email_placeholder'), 'cwginstocknotifier_settings', 'cwginstock_section');
			add_settings_field('cwg_instock_form_button', __('Button Label', 'back-in-stock-notifier-for-woocommerce'), array($this, 'button_label'), 'cwginstocknotifier_settings', 'cwginstock_section');

			add_settings_section('cwginstock_section_visibility', __('Visibility Settings', 'back-in-stock-notifier-for-woocommerce'), array($this, 'visibility_section_heading'), 'cwginstocknotifier_settings');
			add_settings_field('cwginstock_hide_name', __('Hide Name', 'back-in-stock-notifier-for-woocommerce'), array($this, 'hide_name_field'), 'cwginstocknotifier_settings', 'cwginstock_section_visibility');
			// phone
			add_settings_field('cwginstock_show_phone', __('Show Phone', 'back-in-stock-notifier-for-woocommerce'), array($this, 'show_phone_field'), 'cwginstocknotifier_settings', 'cwginstock_section_visibility');
			add_settings_field('cwginstock_show_phone_optional', __('Phone field optional', 'back-in-stock-notifier-for-woocommerce'), array($this, 'phone_field_optional'), 'cwginstocknotifier_settings', 'cwginstock_section_visibility');
			add_settings_field('cwginstock_phone_default_country', __('Default Country for Phone Field', 'back-in-stock-notifier-for-woocommerce'), array($this, 'default_country'), 'cwginstocknotifier_settings', 'cwginstock_section_visibility');
			add_settings_field('cwginstock_phone_country_placeholder', __('Default Country for Phone Field Placeholder', 'back-in-stock-notifier-for-woocommerce'), array($this, 'default_country_placeholder'), 'cwginstocknotifier_settings', 'cwginstock_section_visibility');
			add_settings_field('cwginstock_phone_custom_placeholder', __('Custom Placeholder', 'back-in-stock-notifier-for-woocommerce'), array($this, 'custom_placeholder'), 'cwginstocknotifier_settings', 'cwginstock_section_visibility');
			add_settings_field('cwginstock_remove_placeholder', __('Hide Country Placeholder', 'back-in-stock-notifier-for-woocommerce'), array($this, 'hide_placeholder'), 'cwginstocknotifier_settings', 'cwginstock_section_visibility');

			add_settings_field('cwginstock_visibility_guest', __('Hide Subscribe Form for Guests', 'back-in-stock-notifier-for-woocommerce'), array($this, 'hide_form_for_guest'), 'cwginstocknotifier_settings', 'cwginstock_section_visibility');

			add_settings_field('cwginstock_visibility_guest_message', __('Message to display when the subscribe form is hidden for non logged-in Users', 'back-in-stock-notifier-for-woocommerce'), array($this, 'hide_form_for_guest_msg'), 'cwginstocknotifier_settings', 'cwginstock_section_visibility');
			// since version 1.7
			add_settings_field('cwginstock_visibility_member', __('Hide Subscribe Form for Members', 'back-in-stock-notifier-for-woocommerce'), array($this, 'hide_form_for_member'), 'cwginstocknotifier_settings', 'cwginstock_section_visibility');

			add_settings_field('cwginstock_visibility_backorder', __('Show Subscribe Form on Backorders', 'back-in-stock-notifier-for-woocommerce'), array($this, 'show_form_for_backorders'), 'cwginstocknotifier_settings', 'cwginstock_section_visibility');
			add_settings_field('cwginstock_visibility_subscribebutton_catalog', __('Show Subscribe Now Button in Catalog Page(shop/category)', 'back-in-stock-notifier-for-woocommerce'), array($this, 'show_subscribe_button_catalog'), 'cwginstocknotifier_settings', 'cwginstock_section_visibility');
			add_settings_field('cwginstock_hide_readmore_button', __('Hide Read more Button in Catalog Page(shop/category)', 'back-in-stock-notifier-for-woocommerce'), array($this, 'hide_readmore_button_catalog'), 'cwginstocknotifier_settings', 'cwginstock_section_visibility');

			add_settings_field('cwginstock_visibility_products', __('Show/Hide Subscribe Form for specific products', 'back-in-stock-notifier-for-woocommerce'), array($this, 'visibility_for_specific_products'), 'cwginstocknotifier_settings', 'cwginstock_section_visibility');
			add_settings_field('cwginstock_visibility_categories', __('Show/Hide Subscribe Form for specific categories', 'back-in-stock-notifier-for-woocommerce'), array($this, 'visibility_for_specific_categories'), 'cwginstocknotifier_settings', 'cwginstock_section_visibility');
			add_settings_field('cwginstock_visibility_tags', __('Show/Hide Subscribe Form for specific tags', 'back-in-stock-notifier-for-woocommerce'), array($this, 'visibility_for_specific_tags'), 'cwginstocknotifier_settings', 'cwginstock_section_visibility');

			add_settings_field('cwginstock_visibility_on_regular', __('Hide Subscribe Form on Regular Products out of stock', 'back-in-stock-notifier-for-woocommerce'), array($this, 'visibility_settings_for_product_on_regular'), 'cwginstocknotifier_settings', 'cwginstock_section_visibility');
			add_settings_field('cwginstock_visibility_on_sale', __('Hide Subscribe Form on Sale Products out of stock', 'back-in-stock-notifier-for-woocommerce'), array($this, 'visibility_settings_for_product_on_sale'), 'cwginstocknotifier_settings', 'cwginstock_section_visibility');

			add_settings_field('cwginstock_visibility_on_free_product', __('Hide Subscribe Form on Free or Zero Price Out of Stock Products', 'back-in-stock-notifier-for-woocommerce'), array($this, 'visibility_settings_for_product_on_free'), 'cwginstocknotifier_settings', 'cwginstock_section_visibility');

			add_settings_field('cwginstock_bypass_disabled_variation', __("Don't overwrite disabled out of stock variations from theme configuration", 'back-in-stock-notifier-for-woocommerce'), array($this, 'disabled_variation_settings_option'), 'cwginstocknotifier_settings', 'cwginstock_section_visibility');
			add_settings_field('cwginstock_bypass_wc_visibility', __('Ignore WooCommerce Out of Stock Visibility Settings for Variation', 'back-in-stock-notifier-for-woocommerce'), array($this, 'ignore_settings_for_wc_out_of_stock_visibility'), 'cwginstocknotifier_settings', 'cwginstock_section_visibility');

			add_settings_section('cwginstock_section_error', __('Message Settings', 'back-in-stock-notifier-for-woocommerce'), array($this, 'error_section_heading'), 'cwginstocknotifier_settings');
			add_settings_field('cwg_instock_sub_success', __('Success Subscription Message', 'back-in-stock-notifier-for-woocommerce'), array($this, 'success_subscription_message'), 'cwginstocknotifier_settings', 'cwginstock_section_error');
			add_settings_field('cwg_instock_already_exists', __('Email Already Subscribed Message', 'back-in-stock-notifier-for-woocommerce'), array($this, 'email_already_subscribed'), 'cwginstocknotifier_settings', 'cwginstock_section_error');

			add_settings_field('cwg_instock_error_name_empty', __('Name Field Empty Error', 'back-in-stock-notifier-for-woocommerce'), array($this, 'empty_name_fields'), 'cwginstocknotifier_settings', 'cwginstock_section_error');
			add_settings_field('cwg_instock_error_email_empty', __('Email Field Empty Error', 'back-in-stock-notifier-for-woocommerce'), array($this, 'empty_email_address'), 'cwginstocknotifier_settings', 'cwginstock_section_error');
			add_settings_field('cwg_instock_error_email_invalid', __('Invalid Email Error', 'back-in-stock-notifier-for-woocommerce'), array($this, 'invalid_email_address'), 'cwginstocknotifier_settings', 'cwginstock_section_error');
			add_settings_field('cwg_instock_error_phone_invalid', __('Invalid Phone Number Error', 'back-in-stock-notifier-for-woocommerce'), array($this, 'invalid_phone_number'), 'cwginstocknotifier_settings', 'cwginstock_section_error');
			add_settings_field('cwg_instock_error_phone_too_short', __('Phone Number too short error', 'back-in-stock-notifier-for-woocommerce'), array($this, 'phone_number_too_short'), 'cwginstocknotifier_settings', 'cwginstock_section_error');
			add_settings_field('cwg_instock_error_phone_too_long', __('Phone Number too long error', 'back-in-stock-notifier-for-woocommerce'), array($this, 'phone_number_too_long'), 'cwginstocknotifier_settings', 'cwginstock_section_error');

			add_settings_section('cwginstock_section_mail', __('Mail Settings', 'back-in-stock-notifier-for-woocommerce'), array($this, 'mail_settings_heading'), 'cwginstocknotifier_settings');

			add_settings_field('cwg_instock_email_template_buttons', __('Email Notifications', 'back-in-stock-notifier-for-woocommerce'), array($this, 'email_template_buttons'), 'cwginstocknotifier_settings', 'cwginstock_section_mail');

			add_settings_field('cwg_instock_mail_from_name', __('From Name', 'back-in-stock-notifier-for-woocommerce'), array($this, 'mail_from_name'), 'cwginstocknotifier_settings', 'cwginstock_section_mail');
			add_settings_field('cwg_instock_mail_from_email', __('From Email', 'back-in-stock-notifier-for-woocommerce'), array($this, 'mail_from_email'), 'cwginstocknotifier_settings', 'cwginstock_section_mail');
			add_settings_field('cwg_instock_mail_reply_to', __('Reply To Email', 'back-in-stock-notifier-for-woocommerce'), array($this, 'mail_reply_to'), 'cwginstocknotifier_settings', 'cwginstock_section_mail');

			add_settings_field('cwg_instock_success_subscription_copy', __('Additionally Send this Subscription mail as a copy to specific email ids', 'back-in-stock-notifier-for-woocommerce'), array($this, 'enable_copy_subscription'), 'cwginstocknotifier_settings', 'cwginstock_section_mail');
			add_settings_field('cwg_instock_success_subscription_copy_recipients', __('Enter Email Ids separated by commas that you want to receive subscription copy mail', 'back-in-stock-notifier-for-woocommerce'), array($this, 'subscription_copy_recipients'), 'cwginstocknotifier_settings', 'cwginstock_section_mail');
			/**
			 * Action hook to move the settings of a copy subscription subject and message
			 * 
			 * @since 5.7.3 
			 */
			do_action('cwginstock_copy_subscription_settings');
			add_settings_field('cwg_instock_mail_product_visibility_status', __('Consider Only Published Product Status', 'back-in-stock-notifier-for-woocommerce'), array($this, 'enable_instock_mail_for_product_status'), 'cwginstocknotifier_settings', 'cwginstock_section_mail');
			add_settings_field('cwg_instock_mail_set_minimum_stock_quantity', __('Minimum stock quantity threshold value', 'back-in-stock-notifier-for-woocommerce'), array($this, 'instock_mail_message_set_stock_quantity'), 'cwginstocknotifier_settings', 'cwginstock_section_mail');
			add_settings_field('cwg_instock_post_status_subscribed', __('Keep Subscription Entry to Subscribed Status even Instock Email Sent (Unless it is Unsubscribed)', 'back-in-stock-notifier-for-woocommerce'), array($this, 'enable_post_status_subscribed'), 'cwginstocknotifier_settings', 'cwginstock_section_mail');
			add_settings_field('cwg_instock_fcfs_enable', __('Fair Sending: Notify only as many Subscribers as Units in Stock', 'back-in-stock-notifier-for-woocommerce'), array($this, 'fcfs_enable_field'), 'cwginstocknotifier_settings', 'cwginstock_section_mail');
			add_settings_field('cwg_instock_fcfs_per_unit', __('Fair Sending: Subscribers to Notify per Unit in Stock', 'back-in-stock-notifier-for-woocommerce'), array($this, 'fcfs_per_unit_field'), 'cwginstocknotifier_settings', 'cwginstock_section_mail');


			// ── Auto Coupon ──
			add_settings_section('cwginstock_section_coupon', __('Auto Coupon', 'back-in-stock-notifier-for-woocommerce'), array($this, 'auto_coupon_heading'), 'cwginstocknotifier_settings');
			add_settings_field('cwg_instock_enable_auto_coupon', __('Enable Auto Coupon', 'back-in-stock-notifier-for-woocommerce'), array($this, 'enable_auto_coupon_field'), 'cwginstocknotifier_settings', 'cwginstock_section_coupon');
			add_settings_field('cwg_instock_coupon_discount_type', __('Discount Type', 'back-in-stock-notifier-for-woocommerce'), array($this, 'coupon_discount_type_field'), 'cwginstocknotifier_settings', 'cwginstock_section_coupon');
			add_settings_field('cwg_instock_coupon_amount', __('Coupon Amount', 'back-in-stock-notifier-for-woocommerce'), array($this, 'coupon_amount_field'), 'cwginstocknotifier_settings', 'cwginstock_section_coupon');
			add_settings_field('cwg_instock_coupon_expiry_days', __('Coupon Valid For (days)', 'back-in-stock-notifier-for-woocommerce'), array($this, 'coupon_expiry_field'), 'cwginstocknotifier_settings', 'cwginstock_section_coupon');
			add_settings_field('cwg_instock_coupon_prefix', __('Coupon Code Prefix', 'back-in-stock-notifier-for-woocommerce'), array($this, 'coupon_prefix_field'), 'cwginstocknotifier_settings', 'cwginstock_section_coupon');
			add_settings_field('cwg_instock_coupon_usage_limit', __('Usage Limit per Coupon', 'back-in-stock-notifier-for-woocommerce'), array($this, 'coupon_usage_limit_field'), 'cwginstocknotifier_settings', 'cwginstock_section_coupon');
			add_settings_field('cwg_instock_coupon_minimum_amount', __('Minimum Spend', 'back-in-stock-notifier-for-woocommerce'), array($this, 'coupon_minimum_amount_field'), 'cwginstocknotifier_settings', 'cwginstock_section_coupon');
			add_settings_field('cwg_instock_coupon_skip_when_on_sale', __('Do Not Create Coupon for Products on Sale', 'back-in-stock-notifier-for-woocommerce'), array($this, 'coupon_skip_sale_field'), 'cwginstocknotifier_settings', 'cwginstock_section_coupon');
			add_settings_field('cwg_instock_coupon_exclude_sale_items', __('Coupon Cannot Be Used on Sale Items', 'back-in-stock-notifier-for-woocommerce'), array($this, 'coupon_exclude_sale_field'), 'cwginstocknotifier_settings', 'cwginstock_section_coupon');
			add_settings_field('cwg_instock_coupon_individual_use', __('Cannot Be Combined with Other Coupons', 'back-in-stock-notifier-for-woocommerce'), array($this, 'coupon_individual_use_field'), 'cwginstocknotifier_settings', 'cwginstock_section_coupon');
			add_settings_field('cwg_instock_coupon_restrict_email', __('Restrict Coupon to Subscriber Email', 'back-in-stock-notifier-for-woocommerce'), array($this, 'coupon_restrict_email_field'), 'cwginstocknotifier_settings', 'cwginstock_section_coupon');
			add_settings_field('cwg_instock_coupon_free_shipping', __('Allow Free Shipping', 'back-in-stock-notifier-for-woocommerce'), array($this, 'coupon_free_shipping_field'), 'cwginstocknotifier_settings', 'cwginstock_section_coupon');

			add_settings_section('cwginstock_section_bgprocess', __('Background Process Engine - Advanced Settings', 'back-in-stock-notifier-for-woocommerce'), array($this, 'background_process_heading'), 'cwginstocknotifier_settings');
			add_settings_field('cwginstock_bgp_selection', __('Background Process Engine', 'back-in-stock-notifier-for-woocommerce'), array($this, 'bgp_engine'), 'cwginstocknotifier_settings', 'cwginstock_section_bgprocess');
			add_settings_field('cwg_instock_email_throttle', __('Limit Email Sending Speed', 'back-in-stock-notifier-for-woocommerce'), array($this, 'email_throttle_field'), 'cwginstocknotifier_settings', 'cwginstock_section_bgprocess');
			add_settings_field('cwg_instock_emails_per_minute', __('Maximum Emails per Minute', 'back-in-stock-notifier-for-woocommerce'), array($this, 'emails_per_minute_field'), 'cwginstocknotifier_settings', 'cwginstock_section_bgprocess');
			add_settings_field('cwg_instock_queue_recovery', __('Auto-retry Stuck Emails', 'back-in-stock-notifier-for-woocommerce'), array($this, 'queue_recovery_field'), 'cwginstocknotifier_settings', 'cwginstock_section_bgprocess');
			add_settings_field('cwg_instock_queue_recovery_frequency', __('Retry Frequency', 'back-in-stock-notifier-for-woocommerce'), array($this, 'queue_recovery_frequency_field'), 'cwginstocknotifier_settings', 'cwginstock_section_bgprocess');
			add_settings_field('cwg_instock_queue_recovery_attempts', __('Maximum Retry Attempts', 'back-in-stock-notifier-for-woocommerce'), array($this, 'queue_recovery_attempts_field'), 'cwginstocknotifier_settings', 'cwginstock_section_bgprocess');
			add_settings_field('cwg_instock_queue_recovery_status', __('Status After All Retries Fail', 'back-in-stock-notifier-for-woocommerce'), array($this, 'queue_recovery_status_field'), 'cwginstocknotifier_settings', 'cwginstock_section_bgprocess');
			/**
			 * Action to register settings
			 *
			 * @since 1.0.0
			 */
			do_action('cwginstock_register_settings');
		}

		public function section_heading() {
			esc_html_e('Customize the Frontend Subscribe Form when Product become out of stock', 'back-in-stock-notifier-for-woocommerce');
		}

		public function cwg_frontend_displayform() {
			$options = get_option('cwginstocksettings');

			$array_of_modes = array(
				'1' => __('Inline Subscribe Form', 'back-in-stock-notifier-for-woocommerce'),
				'2' => __('Pop-Up Subscribe Form', 'back-in-stock-notifier-for-woocommerce'),
			);
			?>
			<select class="cwg_form_display_type" name="cwginstocksettings[mode]">
				<?php
				if (is_array($array_of_modes) && ! empty($array_of_modes)) {
					foreach ($array_of_modes as $each_key => $each_value) {
						$chosen_mode = isset($options['mode']) && $options['mode'] == $each_key ? 'selected=selected' : '';
						?>
						<option value="<?php echo do_shortcode($each_key); ?>" <?php echo do_shortcode($chosen_mode); ?>>
							<?php echo do_shortcode($each_value); ?>
						</option>
						<?php
					}
				}
				?>
			</select>
			<?php
		}

		public function form_design_field() {
			$current = function_exists('cwg_instock_get_form_design') ? cwg_instock_get_form_design() : 'default';
			$designs = function_exists('cwg_instock_get_form_designs') ? cwg_instock_get_form_designs() : array();
			?>
			<select name="cwginstocksettings[form_design]" style="width:400px;">
				<?php foreach ($designs as $key => $label) : ?>
					<option value="<?php echo esc_attr($key); ?>" <?php selected($current, $key); ?>><?php echo esc_html($label); ?></option>
				<?php endforeach; ?>
			</select>
			<i>
				<p><?php esc_html_e('Choose how the inline subscribe form looks on the product page. "Default (classic)" keeps the original look exactly as it is, and loads no extra stylesheet. The other designs restyle the same form, so all your settings, fields and add-ons keep working.', 'back-in-stock-notifier-for-woocommerce'); ?>
				</p>
			</i>
			<?php
		}

		public function popup_design_field() {
			$current = function_exists('cwg_instock_get_popup_design') ? cwg_instock_get_popup_design() : 'sweetalert';
			$designs = function_exists('cwg_instock_get_popup_designs') ? cwg_instock_get_popup_designs() : array();
			?>
			<select class="cwg_popup_design" name="cwginstocksettings[popup_design]" style="width:400px;">
				<?php foreach ($designs as $key => $label) : ?>
					<option value="<?php echo esc_attr($key); ?>" <?php selected($current, $key); ?>><?php echo esc_html($label); ?></option>
				<?php endforeach; ?>
			</select>
			<i>
				<p><?php esc_html_e('Choose the popup style used when the subscribe form is shown in a popup. "SweetAlert (default)" is the original popup. The other options use a built-in lightweight popup with no extra library. Applies only when the Frontend Subscribe Form Display Type is set to popup.', 'back-in-stock-notifier-for-woocommerce'); ?>
				</p>
			</i>
			<?php
		}

		public function form_title() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='text' style='width: 400px;' name='cwginstocksettings[form_title]'
				value="<?php echo wp_kses_post($this->api->sanitize_text_field($options['form_title'])); ?>" />
			<?php
		}

		public function form_name_placeholder() {
			$options      = get_option('cwginstocksettings');
			$option_value = isset($options['name_placeholder']) ? $options['name_placeholder'] : __('Your Name', 'back-in-stock-notifier-for-woocommerce');
			?>
			<input type='text' style='width: 400px;' name='cwginstocksettings[name_placeholder]'
				value="<?php echo wp_kses_post($this->api->sanitize_text_field($option_value)); ?>" />
			<?php
		}

		public function form_email_placeholder() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='text' style='width: 400px;' name='cwginstocksettings[form_placeholder]'
				value="<?php echo wp_kses_post($this->api->sanitize_text_field($options['form_placeholder'])); ?>" />
			<?php
		}

		public function button_label() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='text' style='width: 400px;' name='cwginstocksettings[button_label]'
				value="<?php echo wp_kses_post($this->api->sanitize_text_field($options['button_label'])); ?>" />
			<?php
		}

		public function visibility_section_heading() {
			esc_html_e('Visibility Settings for Subscriber Form Frontend', 'back-in-stock-notifier-for-woocommerce');
		}

		public function hide_name_field() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='checkbox' name='cwginstocksettings[hide_name_field]' <?php isset($options['hide_name_field']) ? checked($options['hide_name_field'], 1) : ''; ?> value="1" />
			<p><i>
					<?php esc_html_e('Hide name field in Subscribe Form', 'back-in-stock-notifier-for-woocommerce'); ?>
				</i></p>
			<?php
		}

		public function show_phone_field() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='checkbox' class='show_phone_field' name='cwginstocksettings[show_phone_field]' <?php isset($options['show_phone_field']) ? checked($options['show_phone_field'], 1) : ''; ?> value="1" />
			<p><i>
					<?php esc_html_e('Show phone field in Subscribe Form', 'back-in-stock-notifier-for-woocommerce'); ?>
				</i></p>
			<?php
		}

		public function phone_field_optional() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='checkbox' class='phone_field_optional' name='cwginstocksettings[phone_field_optional]' <?php isset($options['phone_field_optional']) ? checked($options['phone_field_optional'], 1) : ''; ?> value="1" />
			<p><i>
					<?php esc_html_e('Enable this option to make phone field as optional', 'back-in-stock-notifier-for-woocommerce'); ?>
				</i></p>
			<?php
		}

		public function default_country() {
			$options = get_option('cwginstocksettings');
			?>
			<select name='cwginstocksettings[default_country]' class='cwg_default_country'>
				<option value=''>Select Default Country</option>
				<?php
				$countries_obj = new WC_Countries();
				if ($countries_obj) {
					$countries = $countries_obj->__get('countries');
					foreach ($countries as $each_country => $country_name) {
						?>
						<option value='<?php echo esc_attr($each_country); ?>' <?php echo isset($options['default_country']) && $each_country == $options['default_country'] ? 'selected=selected' : ''; ?>>
							<?php echo esc_attr($country_name); ?>
						</option>
						<?php
					}
				}
				?>
			</select>
			<?php
		}

		public function default_country_placeholder() {
			$options = get_option('cwginstocksettings');
			?>
			<select class="cwg_default_country_placeholder" name="cwginstocksettings[default_country_placeholder]"
				style='width: 200px;'>
				<option value="default" <?php echo isset($options['default_country_placeholder']) && 'default' == $options['default_country_placeholder'] ? 'selected=selected' : ''; ?>>
					<?php esc_html_e('Default/Automatic', 'back-in-stock-notifier-for-woocommerce'); ?>
				</option>
				<option value="custom" <?php echo isset($options['default_country_placeholder']) && 'custom' == $options['default_country_placeholder'] ? 'selected=selected' : ''; ?>>
					<?php esc_html_e('Custom Placeholder', 'back-in-stock-notifier-for-woocommerce'); ?>
				</option>
			</select>
			<?php
		}

		public function custom_placeholder() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='text' class="cwg_custom_placeholder" style='width: 400px;' name='cwginstocksettings[custom_placeholder]'
				value='<?php echo wp_kses_post(isset($options['custom_placeholder']) ? $options['custom_placeholder'] : ''); ?>' />
			<?php
		}

		public function hide_placeholder() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='checkbox' class='hide_country_placeholder' name='cwginstocksettings[hide_country_placeholder]' <?php isset($options['hide_country_placeholder']) ? checked($options['hide_country_placeholder'], 1) : ''; ?> value="1" />
			<p><i>
					<?php esc_html_e('Enable this option to hide the placeholder for the phone field in the front-end subscribe form', 'back-in-stock-notifier-for-woocommerce'); ?>
				</i></p>
			<?php
		}

		public function hide_form_for_guest() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='checkbox' class='hide_form_guests' name='cwginstocksettings[hide_form_guests]' <?php isset($options['hide_form_guests']) ? checked($options['hide_form_guests'], 1) : ''; ?> value="1" />
			<p><i>
					<?php esc_html_e('Hide Subscribe Form for non logged-in Users', 'back-in-stock-notifier-for-woocommerce'); ?>
				</i></p>
			<?php
		}

		public function hide_form_for_guest_msg() {
			$options                 = get_option('cwginstocksettings');
			$hide_form_for_guest_msg = isset($options['hide_form_for_guest_msg']) ? $options['hide_form_for_guest_msg'] : '';
			?>
			<textarea class='hide_form_for_guest_msg' name="cwginstocksettings[hide_form_for_guest_msg]"
				style="width:350px;"><?php echo wp_kses_post($this->api->sanitize_textarea_field($hide_form_for_guest_msg)); ?></textarea>
			<p><i><?php esc_html_e('Message to display when the subscribe form is hidden for non logged-in Users', 'back-in-stock-notifier-for-woocommerce'); ?></i>
			</p>
			<?php
		}

		public function hide_form_for_member() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='checkbox' name='cwginstocksettings[hide_form_members]' <?php isset($options['hide_form_members']) ? checked($options['hide_form_members'], 1) : ''; ?> value="1" />
			<p><i>
					<?php esc_html_e('Hide Subscribe Form for logged-in Users', 'back-in-stock-notifier-for-woocommerce'); ?>
				</i></p>
			<?php
		}

		public function show_form_for_backorders() {
			$options = get_option('cwginstocksettings');
			?>
			<input type="checkbox" name="cwginstocksettings[show_on_backorders]" <?php isset($options['show_on_backorders']) ? checked($options['show_on_backorders'], 1) : ''; ?> value="1" />
			<p><i>
					<?php esc_html_e('Display Subscribe Form for Back Order', 'back-in-stock-notifier-for-woocommerce'); ?>
				</i></p>
			<?php
		}

		public function show_subscribe_button_catalog() {
			$options = get_option('cwginstocksettings');
			?>
			<input type="checkbox" name="cwginstocksettings[show_subscribe_button_catalog]" <?php isset($options['show_subscribe_button_catalog']) ? checked($options['show_subscribe_button_catalog'], 1) : ''; ?>
				value="1" />
			<p><i>
					<?php esc_html_e('Display Subscribe Now Button in Catalog Page', 'back-in-stock-notifier-for-woocommerce'); ?>
				</i></p>
			<?php
		}

		public function hide_readmore_button_catalog() {
			$options = get_option('cwginstocksettings');
			?>
			<input type="checkbox" name="cwginstocksettings[hide_readmore_button]" <?php isset($options['hide_readmore_button']) ? checked($options['hide_readmore_button'], 1) : ''; ?> value="1" />
			<p><i>
					<?php esc_html_e('Hide Read more button in catalog page(shop/category)', 'back-in-stock-notifier-for-woocommerce'); ?>
				</i></p>
			<?php
		}

		public function visibility_for_specific_products() {
			$options = get_option('cwginstocksettings');
			?>
			<select style="width:320px;"
				data-placeholder="<?php esc_html_e('Select Products', 'back-in-stock-notifier-for-woocommerce'); ?>"
				data-allow_clear="true" tabindex="-1" aria-hidden="true" name="cwginstocksettings[specific_products][]"
				multiple="multiple" class="wc-product-search">
				<?php
				$current_v = isset($options['specific_products']) ? $options['specific_products'] : '';
				if (is_array($current_v) && ! empty($current_v)) {
					foreach ($current_v as $each_id) {
						$product = wc_get_product($each_id);
						if ($product) {
							printf('<option value="%s"%s>%s</option>', intval($each_id), ' selected="selected"', wp_kses_post($product->get_formatted_name()));
						}
					}
				}
				?>
			</select>
			<label><input type="radio" name="cwginstocksettings[specific_products_visibility]" <?php isset($options['specific_products_visibility']) ? checked($options['specific_products_visibility'], 1) : ''; ?>
					value="1" />
				<?php esc_html_e('Show', 'back-in-stock-notifier-for-woocommerce'); ?>
			</label>
			<label><input type="radio" name="cwginstocksettings[specific_products_visibility]" <?php isset($options['specific_products_visibility']) ? checked($options['specific_products_visibility'], 2) : ''; ?>
					value="2" />
				<?php esc_html_e('Hide', 'back-in-stock-notifier-for-woocommerce'); ?>
			</label>
			<p><i>
					<?php esc_html_e('By Default this field will empty means subscribe form will shown to all out of stock products by default', 'back-in-stock-notifier-for-woocommerce'); ?>
				</i></p>
			<?php
		}

		public function visibility_for_specific_categories() {
			$options = get_option('cwginstocksettings');
			?>
			<select style="width:320px;"
				data-placeholder="<?php esc_html_e('Select Categories', 'back-in-stock-notifier-for-woocommerce'); ?>"
				data-allow_clear="true" name="cwginstocksettings[specific_categories][]" multiple="multiple"
				class="wc-category-search">
				<?php
				$current_v = isset($options['specific_categories']) ? $options['specific_categories'] : '';
				if (is_array($current_v) && ! empty($current_v)) {
					foreach ($current_v as $each_slug) {
						$current_category = $each_slug ? get_term_by('slug', $each_slug, 'product_cat') : false;
						if ($current_category) {
							printf('<option value="%s"%s>%s</option>', esc_attr($each_slug), ' selected="selected"', esc_attr($current_category->name . '(' . $current_category->count . ')'));
						}
					}
				}
				?>
			</select>
			<label><input type="radio" name="cwginstocksettings[specific_categories_visibility]" <?php isset($options['specific_categories_visibility']) ? checked($options['specific_categories_visibility'], 1) : ''; ?>
					value="1" />
				<?php esc_html_e('Show', 'back-in-stock-notifier-for-woocommerce'); ?>
			</label>
			<label><input type="radio" name="cwginstocksettings[specific_categories_visibility]" <?php isset($options['specific_categories_visibility']) ? checked($options['specific_categories_visibility'], 2) : ''; ?>
					value="2" />
				<?php esc_html_e('Hide', 'back-in-stock-notifier-for-woocommerce'); ?>
			</label>
			<p><i>
					<?php esc_html_e('By Default this field will empty means subscribe form will shown to all out of stock products by default', 'back-in-stock-notifier-for-woocommerce'); ?>
				</i></p>
			<?php
		}

		public function visibility_for_specific_tags() {
			$options = get_option('cwginstocksettings');
			?>
			<select style="width:320px;"
				data-placeholder="<?php esc_html_e('Select Product Tags', 'back-in-stock-notifier-for-woocommerce'); ?>"
				data-allow_clear="true" name="cwginstocksettings[specific_tags][]" multiple="multiple" class="wc-tag-search">
				<?php
				$current_v = isset($options['specific_tags']) ? $options['specific_tags'] : '';
				if (is_array($current_v) && ! empty($current_v)) {
					foreach ($current_v as $each_slug) {
						$current_category = $each_slug ? get_term_by('slug', $each_slug, 'product_tag') : false;
						if ($current_category) {
							printf('<option value="%s"%s>%s</option>', esc_attr($each_slug), ' selected="selected"', esc_attr($current_category->name . '(' . $current_category->count . ')'));
						}
					}
				}
				?>
			</select>
			<label><input type="radio" name="cwginstocksettings[specific_tags_visibility]" <?php isset($options['specific_tags_visibility']) ? checked($options['specific_tags_visibility'], 1) : ''; ?>
					value="1" />
				<?php esc_html_e('Show', 'back-in-stock-notifier-for-woocommerce'); ?>
			</label>
			<label><input type="radio" name="cwginstocksettings[specific_tags_visibility]" <?php isset($options['specific_tags_visibility']) ? checked($options['specific_tags_visibility'], 2) : ''; ?>
					value="2" />
				<?php esc_html_e('Hide', 'back-in-stock-notifier-for-woocommerce'); ?>
			</label>
			<p><i>
					<?php esc_html_e('By Default this field will empty means subscribe form will shown to all out of stock products by default', 'back-in-stock-notifier-for-woocommerce'); ?>
				</i></p>
			<?php
		}

		public function visibility_settings_for_product_on_sale() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='checkbox' name='cwginstocksettings[hide_on_sale]' <?php isset($options['hide_on_sale']) ? checked($options['hide_on_sale'], 1) : ''; ?> value="1" />
			<p><i>
					<?php esc_html_e('Hide Subscribe Form on Sale Products out of stock', 'back-in-stock-notifier-for-woocommerce'); ?>
				</i></p>
			<?php
		}

		public function disabled_variation_settings_option() {
			$options = get_option('cwginstocksettings');
			?>
			<p>
				<?php
				esc_html_e(
					'Some themes disable variation out of stock by default and some by an option, when activate our plugin it overwrite
                theme configuration(disabled variation become selectable), so by enable this option our plugin settings will not
                overwrite theme configuration',
					'back-in-stock-notifier-for-woocommerce'
				);
				?>
			</p>
			<input type='checkbox' name='cwginstocksettings[ignore_disabled_variation]' <?php isset($options['ignore_disabled_variation']) ? checked($options['ignore_disabled_variation'], 1) : ''; ?> value="1" />
			<p><i>
					<?php esc_html_e('Enable this option to not overwrite disabled out of stock variation settings from themes(some themes)', 'back-in-stock-notifier-for-woocommerce'); ?>
				</i></p>
			<?php
		}

		public function ignore_settings_for_wc_out_of_stock_visibility() {
			$options = get_option('cwginstocksettings');
			?>
			<p>
				<?php
				esc_html_e(
					'WooCommerce has an option to hide out of stock products from catalog(WooCommerce->Products->Inventory->Out of stock
                visibililty),when you enable/enabled this option will hide out of stock products from shop page/category page, but
                this also hide out of stock variations from variation dropdown, for that we provide option to ignore that
                woocommerce out of stock visibility settings only for variable products',
					'back-in-stock-notifier-for-woocommerce'
				);
				?>

			</p>
			<input type='checkbox' name='cwginstocksettings[ignore_wc_visibility]' <?php isset($options['ignore_wc_visibility']) ? checked($options['ignore_wc_visibility'], 1) : ''; ?> value="1" />
			<p><i>
					<?php esc_html_e('Enable this option to ignore WooCommerce Out of stock Visibility Settings for Variations', 'back-in-stock-notifier-for-woocommerce'); ?>
				</i></p>
			<?php
		}

		public function visibility_settings_for_product_on_regular() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='checkbox' name='cwginstocksettings[hide_on_regular]' <?php isset($options['hide_on_regular']) ? checked($options['hide_on_regular'], 1) : ''; ?> value="1" />
			<p><i>
					<?php esc_html_e('Hide Subscribe Form on Regular Products out of stock', 'back-in-stock-notifier-for-woocommerce'); ?>
				</i></p>
			<?php
		}

		public function visibility_settings_for_product_on_free() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='checkbox' name='cwginstocksettings[hide_on_free]' <?php isset($options['hide_on_free']) ? checked($options['hide_on_free'], 1) : ''; ?> value="1" />
			<p><i>
					<?php esc_html_e('Hide Subscribe Form on Free or Zero Price Out of Stock Products', 'back-in-stock-notifier-for-woocommerce'); ?>
				</i></p>
			<?php
		}

		public function error_section_heading() {
			esc_html_e('Customize Error Message and its Visibility', 'back-in-stock-notifier-for-woocommerce');
		}

		public function empty_name_fields() {
			$options      = get_option('cwginstocksettings');
			$option_value = isset($options['empty_name_message']) ? $options['empty_name_message'] : __('Name cannot be empty', 'back-in-stock-notifier-for-woocommerce');
			?>
			<input type='text' style='width: 400px;' name='cwginstocksettings[empty_name_message]'
				value="<?php echo wp_kses_post($this->api->sanitize_text_field($option_value)); ?>" />
			<?php
		}

		public function empty_email_address() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='text' style='width: 400px;' name='cwginstocksettings[empty_error_message]'
				value="<?php echo wp_kses_post($this->api->sanitize_text_field($options['empty_error_message'])); ?>" />
			<?php
		}

		public function invalid_email_address() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='text' style='width: 400px;' name='cwginstocksettings[invalid_email_error]'
				value="<?php echo wp_kses_post($this->api->sanitize_text_field($options['invalid_email_error'])); ?>" />
			<?php
		}

		public function invalid_phone_number() {
			$options              = get_option('cwginstocksettings');
			$invalid_phone_number = isset($options['invalid_phone_error']) ? $options['invalid_phone_error'] : esc_html__('Please enter valid Phone Number', 'back-in-stock-notifier-for-woocommerce');
			?>
			<input type='text' style='width: 400px;' name='cwginstocksettings[invalid_phone_error]'
				value="<?php echo wp_kses_post($this->api->sanitize_text_field($invalid_phone_number)); ?>" />
			<?php
		}

		public function phone_number_too_short() {
			$options                = get_option('cwginstocksettings');
			$phone_number_too_short = isset($options['phone_number_too_short']) ? $options['phone_number_too_short'] : esc_html__('Phone Number too short', 'back-in-stock-notifier-for-woocommerce');
			?>
			<input type='text' style='width: 400px;' name='cwginstocksettings[phone_number_too_short]'
				value="<?php echo wp_kses_post($this->api->sanitize_text_field($phone_number_too_short)); ?>" />
			<?php
		}

		public function phone_number_too_long() {
			$options               = get_option('cwginstocksettings');
			$phone_number_too_long = isset($options['phone_number_too_long']) ? $options['phone_number_too_long'] : esc_html__('Phone Number too long', 'back-in-stock-notifier-for-woocommerce');
			?>
			<input type='text' style='width: 400px;' name='cwginstocksettings[phone_number_too_long]'
				value="<?php echo wp_kses_post($this->api->sanitize_text_field($phone_number_too_long)); ?>" />
			<?php
		}

		public function mail_settings_heading() {
			echo '<p>' . esc_html__( 'Email notifications are now managed through WooCommerce Email Templates for better customization. Use the buttons below to configure each email template.', 'back-in-stock-notifier-for-woocommerce' ) . '</p>';
		}

		
		/**
		 * Render buttons to link to WooCommerce email template settings.
		 *
		 * @since 7.0.0
		 */
		public function email_template_buttons() {
			$sub_url     = CWG_Instock_Email_Manager::get_email_settings_url( 'WC_Email_BIS_Subscription' );
			$instock_url = CWG_Instock_Email_Manager::get_email_settings_url( 'WC_Email_BIS_Instock' );
			?>
			<div class="cwg-email-buttons-wrap">
				<div class="cwg-email-btn-row">
					<a href="<?php echo esc_url( $sub_url ); ?>" class="cwg-email-template-btn">
						<span class="dashicons dashicons-email"></span>
						<?php esc_html_e( 'Customize Subscription Confirmation Email', 'back-in-stock-notifier-for-woocommerce' ); ?>
					</a>
					<a href="<?php echo esc_url( $instock_url ); ?>" class="cwg-email-template-btn">
						<span class="dashicons dashicons-email-alt"></span>
						<?php esc_html_e( 'Customize Back In Stock Email', 'back-in-stock-notifier-for-woocommerce' ); ?>
					</a>
				</div>
				<div class="cwg-email-info-box">
					<span class="dashicons dashicons-info-outline"></span>
					<?php esc_html_e( 'These email templates are fully integrated with WooCommerce. You can customize the subject, heading, content, and email type directly through the WooCommerce email settings interface. Your existing settings have been automatically migrated.', 'back-in-stock-notifier-for-woocommerce' ); ?>
					<br><br>
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: %s: URL to WooCommerce emails settings */
							__( 'You can also find them under <a href="%s">WooCommerce → Settings → Emails</a>.', 'back-in-stock-notifier-for-woocommerce' ),
							esc_url( admin_url( 'admin.php?page=wc-settings&tab=email' ) )
						)
					);
					?>
				</div>
			</div>
			<?php
		}


		public function mail_from_name() {
			$options = get_option('cwginstocksettings');
			//from name default value fetch it from woocommerce settings
			if (empty($options['mail_from_name'])) {
				$wc_from_name              = get_option('woocommerce_email_from_name');
				$options['mail_from_name'] = ! empty($wc_from_name) ? $wc_from_name : get_bloginfo('name');
			}
			?>
			<input type='text' style='width: 400px;' name='cwginstocksettings[mail_from_name]'
				value="<?php echo wp_kses_post($this->api->sanitize_text_field($options['mail_from_name'])); ?>" />
			<?php
		}

		public function mail_from_email() {
			$options = get_option('cwginstocksettings');
			//from email default value fetch it from woocommerce settings
			if (empty($options['mail_from_email'])) {
				$wc_from_email              = get_option('woocommerce_email_from_address');
				$options['mail_from_email'] = ! empty($wc_from_email) ? $wc_from_email : get_bloginfo('admin_email');
			}
			?>
			<input type='text' style='width: 400px;' name='cwginstocksettings[mail_from_email]'
				value="<?php echo wp_kses_post($this->api->sanitize_text_field($options['mail_from_email'])); ?>" />
			<?php
		}

		public function mail_reply_to() {
			$options = get_option('cwginstocksettings');
			if (empty($options['mail_reply_to'])) {
				$options['mail_reply_to'] = '';
			}
			?>
			<input type='text' style='width: 400px;' name='cwginstocksettings[mail_reply_to]'
				value="<?php echo wp_kses_post($this->api->sanitize_text_field($options['mail_reply_to'])); ?>" />
			<?php
			//description to explain if you don't want to add reply to keep it as empty
			echo esc_html( __( 'If you want to set Reply-To email address then enter email id otherwise keep it empty', 'back-in-stock-notifier-for-woocommerce' ) );

		}

		public function success_subscription_mail() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='checkbox' name='cwginstocksettings[enable_success_sub_mail]' <?php isset($options['enable_success_sub_mail']) ? checked($options['enable_success_sub_mail'], 1) : ''; ?> value="1" />
			<?php
		}

		public function enable_copy_subscription() {
			$options = get_option('cwginstocksettings');
			?>
			<input type="checkbox" name="cwginstocksettings[enable_copy_subscription]" <?php isset($options['enable_copy_subscription']) ? checked($options['enable_copy_subscription'], 1) : ''; ?> value='1' />
			<?php
			echo esc_html(__('For Example: If admin/shop owner want to receive email copy of subcribers then enable this option followed by enter their email ids', 'back-in-stock-notifier-for-woocommerce'));
		}

		public function success_subscription_mail_subject() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='text' style='width: 400px;' name='cwginstocksettings[success_sub_subject]'
				value="<?php echo wp_kses_post($this->api->sanitize_text_field($options['success_sub_subject'])); ?>" />
			<?php
		}

		public function success_subscription_mail_message() {
			$options = get_option('cwginstocksettings');
			?>
			<textarea rows="15" cols="50"
				name="cwginstocksettings[success_sub_message]"><?php echo wp_kses_post($this->api->sanitize_textarea_field($options['success_sub_message'])); ?></textarea>
			<?php
		}

		public function subscription_copy_recipients() {
			$options = get_option('cwginstocksettings');
			?>
			<textarea rows='15' cols='50'
				name='cwginstocksettings[subscription_copy_recipients]'><?php echo wp_kses_post(isset($options['subscription_copy_recipients']) ? $options['subscription_copy_recipients'] : ''); ?></textarea>
			<?php
		}

		public function enable_instock_mail() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='checkbox' name='cwginstocksettings[enable_instock_mail]' <?php isset($options['enable_instock_mail']) ? checked($options['enable_instock_mail'], 1) : ''; ?> value="1" />
			<?php
		}

		public function enable_instock_mail_for_product_status() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='checkbox' name='cwginstocksettings[enable_instock_mail_for_product_status]' <?php isset($options['enable_instock_mail_for_product_status']) ? checked($options['enable_instock_mail_for_product_status'], 1) : ''; ?> value="1" />
			<p><i>
					<?php esc_html_e('By enable this option, instock email will be send to the published product. Status with private/draft product status will not be considered.', 'back-in-stock-notifier-for-woocommerce'); ?>
				</i></p>
			<?php
		}

		public function instock_mail_subject() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='text' style='width: 400px;' name='cwginstocksettings[instock_mail_subject]'
				value="<?php echo wp_kses_post($this->api->sanitize_text_field($options['instock_mail_subject'])); ?>" />
			<?php
		}

		public function instock_mail_message() {
			$options = get_option('cwginstocksettings');
			?>
			<textarea rows="15" cols="50"
				name="cwginstocksettings[instock_mail_message]"><?php echo wp_kses_post($this->api->sanitize_textarea_field($options['instock_mail_message'])); ?></textarea>
			<?php
		}

		public function instock_mail_message_set_stock_quantity() {
			$options               = get_option('cwginstocksettings');
			$get_option_value_user = isset($options['set_stock_quantity_for_instock_mail']) && $options['set_stock_quantity_for_instock_mail'] > 0 ? $options['set_stock_quantity_for_instock_mail'] : 0;
			?>
			<input type='number' style='width: 400px;' name='cwginstocksettings[set_stock_quantity_for_instock_mail]'
				value="<?php echo wp_kses_post($get_option_value_user); ?>" step="any" />
			<i>
				<p>
					<?php
					esc_html_e(
						'Using this option Instock Email trigger can be controllable, when you manage product stock by quantity. For Ex:
                    If you set 5 in this option, you have to update product stock more than or equal to 5 in product stock quantity
                    in order to trigger instock email',
						'back-in-stock-notifier-for-woocommerce'
					)
					?>
				</p>
			</i>
			<?php
		}

		public function enable_post_status_subscribed() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='checkbox' name='cwginstocksettings[keep_status_subscribed]' <?php isset($options['keep_status_subscribed']) ? checked($options['keep_status_subscribed'], 1) : ''; ?> value="1" />
			<span
				style="color: red;display: block; margin-top: 5px;"><?php esc_html_e('Not recommended', 'back-in-stock-notifier-for-woocommerce'); ?></span>

			<i>
				<p><?php esc_html_e('Using this option prevents subscribers from having to repeatedly sign up for notifications when a product becomes available in limited quantities after going out of stock. For instance, if there are 100 subscribers signed up for notifications and only 10 units are expected to be restocked at a time, enabling this option ensures that those subscribers won\'t need to re-register for notifications each time the product is restocked', 'back-in-stock-notifier-for-woocommerce'); ?>
				</p>
				<p><?php esc_html_e('Note: Enabling this option repeatedly sends instock notifications whenever a product becomes available until the subscribed product is "purchased" or "unsubscribed."', 'back-in-stock-notifier-for-woocommerce'); ?>
				</p>
			</i>
			<?php
		}

		public function fcfs_enable_field() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='checkbox' name='cwginstocksettings[enable_fcfs_notification]' <?php isset($options['enable_fcfs_notification']) ? checked($options['enable_fcfs_notification'], 1) : ''; ?> value="1" />
			<i>
				<p><?php esc_html_e('When a product is restocked with only a few units, notify just the earliest subscribers instead of everyone. For example: 100 subscribers are waiting and you restock 5 units, only the first 5 subscribers are notified. The remaining 95 stay on the waitlist and are notified automatically on the next restock, they do not need to subscribe again.', 'back-in-stock-notifier-for-woocommerce'); ?>
				</p>
				<p><?php esc_html_e('Subscribers who already received a notification are moved to the end of the line, so everyone gets a fair turn. Applies only to automatic instock emails for products that manage stock quantity. Manual and bulk sends are never limited.', 'back-in-stock-notifier-for-woocommerce'); ?>
				</p>
			</i>
			<?php
		}

		public function fcfs_per_unit_field() {
			$options  = get_option('cwginstocksettings');
			$per_unit = isset($options['fcfs_subscribers_per_unit']) && absint($options['fcfs_subscribers_per_unit']) > 0 ? absint($options['fcfs_subscribers_per_unit']) : 1;
			?>
			<input type='number' style='width: 400px;' name='cwginstocksettings[fcfs_subscribers_per_unit]'
				value="<?php echo esc_attr($per_unit); ?>" min="1" max="100" step="1" />
			<i>
				<p><?php esc_html_e('How many subscribers to notify for each unit in stock. Default is 1, so restocking 5 units notifies the first 5 subscribers. Increase this if you expect that not every notified subscriber will buy. For example: set 2 and restocking 5 units notifies the first 10 subscribers. Used only when Fair Sending above is enabled.', 'back-in-stock-notifier-for-woocommerce'); ?>
				</p>
			</i>
			<?php
		}

		public function auto_coupon_heading() {
			$placeholders = '<code>{coupon_code}</code>, <code>{coupon_amount}</code>, <code>{coupon_expiry}</code>';
			echo wp_kses_post(
				sprintf(
					/* translators: %s: list of coupon placeholders */
					__( 'Automatically create a unique discount coupon for each subscriber when their back in stock email is sent, to encourage them to buy. Coupons are valid only for the exact product that person subscribed to. Nothing is created while this is disabled. To use it, add %s to your Back In Stock email under WooCommerce > Settings > Emails. A coupon is generated only when one of these placeholders is present in the email.', 'back-in-stock-notifier-for-woocommerce' ),
					$placeholders
				)
			);
		}

		public function enable_auto_coupon_field() {
			$options = get_option('cwginstocksettings');
			$enabled = isset($options['enable_auto_coupon']) && '1' == $options['enable_auto_coupon'];
			?>
			<input type='checkbox' name='cwginstocksettings[enable_auto_coupon]' <?php checked($enabled, true); ?> value="1" />
			<i>
				<p><?php esc_html_e('Off by default. When enabled, each subscriber receives their own single use coupon code in the back in stock email. Coupons are never created for stores that do not use the coupon placeholders in the email.', 'back-in-stock-notifier-for-woocommerce'); ?>
				</p>
			</i>
			<?php
		}

		public function coupon_discount_type_field() {
			$options = get_option('cwginstocksettings');
			$current = isset($options['coupon_discount_type']) && '' != $options['coupon_discount_type'] ? $options['coupon_discount_type'] : 'percent';
			$types   = array(
				'percent'       => __('Percentage discount', 'back-in-stock-notifier-for-woocommerce'),
				'fixed_product' => __('Fixed product discount', 'back-in-stock-notifier-for-woocommerce'),
				'fixed_cart'    => __('Fixed cart discount', 'back-in-stock-notifier-for-woocommerce'),
			);
			?>
			<select name="cwginstocksettings[coupon_discount_type]" style="width:400px;">
				<?php foreach ($types as $key => $label) : ?>
					<option value="<?php echo esc_attr($key); ?>" <?php selected($current, $key); ?>><?php echo esc_html($label); ?></option>
				<?php endforeach; ?>
			</select>
			<i><p><?php esc_html_e('Same discount types as WooCommerce coupons.', 'back-in-stock-notifier-for-woocommerce'); ?></p></i>
			<?php
		}

		public function coupon_amount_field() {
			$options = get_option('cwginstocksettings');
			$amount  = isset($options['coupon_amount']) && '' != $options['coupon_amount'] ? $options['coupon_amount'] : 10;
			?>
			<input type='number' style='width: 400px;' name='cwginstocksettings[coupon_amount]' value="<?php echo esc_attr($amount); ?>" min="0" step="0.01" />
			<i><p><?php esc_html_e('The discount value. For a percentage discount enter 10 for 10%. Set 0 to disable coupon creation.', 'back-in-stock-notifier-for-woocommerce'); ?></p></i>
			<?php
		}

		public function coupon_expiry_field() {
			$options = get_option('cwginstocksettings');
			$days    = isset($options['coupon_expiry_days']) && '' != $options['coupon_expiry_days'] ? $options['coupon_expiry_days'] : 7;
			?>
			<input type='number' style='width: 400px;' name='cwginstocksettings[coupon_expiry_days]' value="<?php echo esc_attr($days); ?>" min="0" step="1" />
			<i><p><?php esc_html_e('How many days the coupon stays valid after it is created. A short window such as 3 to 7 days creates urgency. Enter 0 for no expiry.', 'back-in-stock-notifier-for-woocommerce'); ?></p></i>
			<?php
		}

		public function coupon_prefix_field() {
			$options = get_option('cwginstocksettings');
			$prefix  = isset($options['coupon_prefix']) && '' != $options['coupon_prefix'] ? $options['coupon_prefix'] : 'BIS';
			?>
			<input type='text' style='width: 400px;' name='cwginstocksettings[coupon_prefix]' value="<?php echo esc_attr($prefix); ?>" />
			<i><p><?php esc_html_e('Codes look like PREFIX-XXXXXXXX. Letters, numbers, dash and underscore only.', 'back-in-stock-notifier-for-woocommerce'); ?></p></i>
			<?php
		}

		public function coupon_usage_limit_field() {
			$options = get_option('cwginstocksettings');
			$limit   = isset($options['coupon_usage_limit']) && '' != $options['coupon_usage_limit'] ? $options['coupon_usage_limit'] : 1;
			?>
			<input type='number' style='width: 400px;' name='cwginstocksettings[coupon_usage_limit]' value="<?php echo esc_attr($limit); ?>" min="1" step="1" />
			<i><p><?php esc_html_e('How many times a single generated coupon can be used. Keep this at 1 so each code belongs to one customer.', 'back-in-stock-notifier-for-woocommerce'); ?></p></i>
			<?php
		}

		public function coupon_minimum_amount_field() {
			$options = get_option('cwginstocksettings');
			$min     = isset($options['coupon_minimum_amount']) && '' != $options['coupon_minimum_amount'] ? $options['coupon_minimum_amount'] : '';
			?>
			<input type='number' style='width: 400px;' name='cwginstocksettings[coupon_minimum_amount]' value="<?php echo esc_attr($min); ?>" min="0" step="0.01" />
			<i><p><?php esc_html_e('Optional minimum cart total required to use the coupon. Leave empty or 0 for no minimum.', 'back-in-stock-notifier-for-woocommerce'); ?></p></i>
			<?php
		}

		public function coupon_skip_sale_field() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='checkbox' name='cwginstocksettings[coupon_skip_when_on_sale]' <?php isset($options['coupon_skip_when_on_sale']) ? checked($options['coupon_skip_when_on_sale'], 1) : ''; ?> value="1" />
			<i><p><?php esc_html_e('When enabled, no coupon is created at all if the restocked product is already on sale, so you never discount twice. The email is still sent, just without a coupon code.', 'back-in-stock-notifier-for-woocommerce'); ?></p></i>
			<?php
		}

		public function coupon_exclude_sale_field() {
			$options = get_option('cwginstocksettings');
			$checked = ! isset($options['coupon_exclude_sale_items']) || '1' == $options['coupon_exclude_sale_items'];
			?>
			<input type='checkbox' name='cwginstocksettings[coupon_exclude_sale_items]' <?php checked($checked, true); ?> value="1" />
			<i><p><?php esc_html_e('Standard WooCommerce coupon rule. When enabled the coupon will not apply to items that are on sale at checkout time.', 'back-in-stock-notifier-for-woocommerce'); ?></p></i>
			<?php
		}

		public function coupon_individual_use_field() {
			$options = get_option('cwginstocksettings');
			$checked = ! isset($options['coupon_individual_use']) || '1' == $options['coupon_individual_use'];
			?>
			<input type='checkbox' name='cwginstocksettings[coupon_individual_use]' <?php checked($checked, true); ?> value="1" />
			<i><p><?php esc_html_e('Prevents this coupon being combined with your other coupons.', 'back-in-stock-notifier-for-woocommerce'); ?></p></i>
			<?php
		}

		public function coupon_restrict_email_field() {
			$options = get_option('cwginstocksettings');
			$checked = ! isset($options['coupon_restrict_email']) || '1' == $options['coupon_restrict_email'];
			?>
			<input type='checkbox' name='cwginstocksettings[coupon_restrict_email]' <?php checked($checked, true); ?> value="1" />
			<i><p><?php esc_html_e('Locks the coupon to the subscriber email address so a forwarded code cannot be used by someone else. Recommended.', 'back-in-stock-notifier-for-woocommerce'); ?></p></i>
			<?php
		}

		public function coupon_free_shipping_field() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='checkbox' name='cwginstocksettings[coupon_free_shipping]' <?php isset($options['coupon_free_shipping']) ? checked($options['coupon_free_shipping'], 1) : ''; ?> value="1" />
			<i><p><?php esc_html_e('Also grants free shipping, if your shipping settings allow a free shipping method with a coupon.', 'back-in-stock-notifier-for-woocommerce'); ?></p></i>
			<?php
		}

		public function background_process_heading() {
			esc_html_e('Please select background process engine, this is important to send a mail in background by default it is WP Background Process and you can also choose WooCommerce Background Process', 'back-in-stock-notifier-for-woocommerce');
		}

		public function email_throttle_field() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='checkbox' name='cwginstocksettings[enable_email_throttle]' <?php isset($options['enable_email_throttle']) ? checked($options['enable_email_throttle'], 1) : ''; ?> value="1" />
			<i>
				<p><?php esc_html_e('Send automatic instock emails gradually instead of all at once. Useful when your hosting provider or SMTP service limits how many emails you can send per minute or hour. Emails over the limit are automatically sent in the following minutes, nothing is lost or skipped.', 'back-in-stock-notifier-for-woocommerce'); ?>
				</p>
				<p><?php esc_html_e('Applies only to automatic background emails. Manual sends, bulk sends and subscription confirmation emails are never delayed.', 'back-in-stock-notifier-for-woocommerce'); ?>
				</p>
			</i>
			<?php
		}

		public function emails_per_minute_field() {
			$options = get_option('cwginstocksettings');
			$rate    = isset($options['emails_per_minute']) && absint($options['emails_per_minute']) > 0 ? absint($options['emails_per_minute']) : 60;
			?>
			<input type='number' style='width: 400px;' name='cwginstocksettings[emails_per_minute]'
				value="<?php echo esc_attr($rate); ?>" min="1" max="500" step="1" />
			<i>
				<p><?php esc_html_e('Maximum number of automatic instock emails to send per minute. Default is 60. Check your hosting or SMTP provider limits and set a slightly lower value. For example: a provider limit of 600 emails per hour means at most 10 per minute. Used only when Limit Email Sending Speed above is enabled.', 'back-in-stock-notifier-for-woocommerce'); ?>
				</p>
			</i>
			<?php
		}

		public function queue_recovery_field() {
			$options = get_option('cwginstocksettings');
			// Off by default, only enable deliberately.
			$enabled = isset($options['enable_queue_recovery']) && '1' == $options['enable_queue_recovery'];
			?>
			<input type='checkbox' name='cwginstocksettings[enable_queue_recovery]' <?php checked($enabled, true); ?> value="1" />
			<i>
				<p><?php esc_html_e('Subscribers are queued the moment a product is restocked, then emailed by a background task. If that task fails or never runs (for example when the server cron is not firing), those subscribers can get stuck without an email. When enabled, a recovery task periodically finds these stuck entries and tries to send their email again. Leave this off unless you actually see stuck entries, no background task is scheduled while it is off.', 'back-in-stock-notifier-for-woocommerce'); ?>
				</p>
			</i>
			<?php
		}

		public function queue_recovery_frequency_field() {
			$options  = get_option('cwginstocksettings');
			$selected = isset($options['queue_recovery_frequency']) && '' != $options['queue_recovery_frequency'] ? $options['queue_recovery_frequency'] : 'every_hour';
			$choices  = array(
				'every_hour'     => __('Every Hour', 'back-in-stock-notifier-for-woocommerce'),
				'every_6_hours'  => __('Every 6 Hours', 'back-in-stock-notifier-for-woocommerce'),
				'every_12_hours' => __('Every 12 Hours', 'back-in-stock-notifier-for-woocommerce'),
				'every_day'      => __('Every Day', 'back-in-stock-notifier-for-woocommerce'),
			);
			?>
			<select name="cwginstocksettings[queue_recovery_frequency]" style="width:400px;">
				<?php foreach ($choices as $value => $label) : ?>
					<option value="<?php echo esc_attr($value); ?>" <?php selected($selected, $value); ?>><?php echo esc_html($label); ?></option>
				<?php endforeach; ?>
			</select>
			<i>
				<p><?php esc_html_e('How often the recovery task runs and how far apart the retries are spaced. For example, with "Every Hour" and 2 attempts, a stuck entry is retried at about 1 hour and again at 2 hours, then it stops. The minimum is 1 hour to keep the scheduled-action log tidy. Only applies when Auto-retry Stuck Emails is enabled.', 'back-in-stock-notifier-for-woocommerce'); ?>
				</p>
			</i>
			<?php
		}

		public function queue_recovery_attempts_field() {
			$options  = get_option('cwginstocksettings');
			$attempts = isset($options['queue_recovery_max_attempts']) && absint($options['queue_recovery_max_attempts']) > 0 ? absint($options['queue_recovery_max_attempts']) : 2;
			?>
			<input type='number' style='width: 400px;' name='cwginstocksettings[queue_recovery_max_attempts]'
				value="<?php echo esc_attr($attempts); ?>" min="1" max="10" step="1" />
			<i>
				<p><?php esc_html_e('How many times to retry before giving up. For example, with a 5 hour interval and 2 attempts, the plugin retries at about 5 hours and again at 10 hours. After the last attempt fails, it stops and sets the status below. Default is 2.', 'back-in-stock-notifier-for-woocommerce'); ?>
				</p>
			</i>
			<?php
		}

		public function queue_recovery_status_field() {
			$options = get_option('cwginstocksettings');
			$current = isset($options['queue_recovery_final_status']) && '' != $options['queue_recovery_final_status'] ? $options['queue_recovery_final_status'] : 'cwg_mailnotsent';
			$choices = array(
				'cwg_mailnotsent' => __('Mail Not Sent (recommended)', 'back-in-stock-notifier-for-woocommerce'),
				'cwg_subscribed'  => __('Subscribed (retry on next restock)', 'back-in-stock-notifier-for-woocommerce'),
				'cwg_unsubscribed' => __('Unsubscribed', 'back-in-stock-notifier-for-woocommerce'),
			);
			?>
			<select name="cwginstocksettings[queue_recovery_final_status]" style="width:400px;">
				<?php foreach ($choices as $value => $label) : ?>
					<option value="<?php echo esc_attr($value); ?>" <?php selected($current, $value); ?>><?php echo esc_html($label); ?></option>
				<?php endforeach; ?>
			</select>
			<i>
				<p><?php esc_html_e('The status a stuck entry is set to after all retry attempts fail. "Mail Not Sent" keeps it visible so you can send manually later. "Subscribed" puts it back in the pool to be tried again on the next restock.', 'back-in-stock-notifier-for-woocommerce'); ?>
				</p>
			</i>
			<?php
		}

		public function bgp_engine() {
			$options = get_option('cwginstocksettings');
			?>
			<select name="cwginstocksettings[bgp_engine]" style="width:400px;">
				<option value="wcbgp" <?php echo isset($options['bgp_engine']) && 'wcbgp' == $options['bgp_engine'] ? 'selected=selected' : ''; ?>>
					<?php esc_html_e('WooCommerce Background Process', 'back-in-stock-notifier-for-woocommerce'); ?>
				</option>
				<option value="wpbgp" <?php echo isset($options['bgp_engine']) && 'wpbgp' == $options['bgp_engine'] ? 'selected=selected' : ''; ?>>
					<?php esc_html_e('Default Background Process', 'back-in-stock-notifier-for-woocommerce'); ?>
				</option>
			</select>
			<?php
		}

		public function success_subscription_message() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='text' style='width: 400px;' name='cwginstocksettings[success_subscription]'
				value="<?php echo wp_kses_post($this->api->sanitize_text_field($options['success_subscription'])); ?>" />
			<i>
				<p>
					<?php esc_html_e('Supported Shortcodes {product_name}, {only_product_name}', 'back-in-stock-notifier-for-woocommerce'); ?>
				</p>


			</i>
			<?php
		}

		public function email_already_subscribed() {
			$options = get_option('cwginstocksettings');
			?>
			<input type='text' style='width: 400px;' name='cwginstocksettings[already_subscribed]'
				value="<?php echo wp_kses_post($this->api->sanitize_text_field($options['already_subscribed'])); ?>" />
			<i>
				<p> <?php esc_html_e('Supported Shortcodes {product_name}, {only_product_name}', 'back-in-stock-notifier-for-woocommerce'); ?>
				</p>
			</i>
			<?php
		}

		public function default_value() {
			$success_subscribe_message = __( 'Hello {subscriber_name},<br/><br/>Thank you for subscribing to {product_name} (#{product_id}). We will send you an email at {subscriber_email} as soon as this item is back in stock. You can review the product here: {product_link}.<br/><br/>Thanks for shopping with {shopname}.', 'back-in-stock-notifier-for-woocommerce' );
			$instock_message           = __( 'Hello {subscriber_name},<br/><br/>Good news! {product_name} is now back in stock. You can view the item here: {product_link} or add it directly to your cart: {cart_link}. We only have limited stock available, so please act quickly. Thanks for subscribing with {shopname}.', 'back-in-stock-notifier-for-woocommerce' );
			/**
			 * Filter for modifying the array of default values
			 *
			 * @since 1.0.0
			 */
			$data = apply_filters(
				'cwginstock_default_values', array(
				'form_title' => __('Email when stock available', 'back-in-stock-notifier-for-woocommerce'),
				'name_placeholder' => __('Your Name', 'back-in-stock-notifier-for-woocommerce'),
				'form_placeholder' => __('Your Email Address', 'back-in-stock-notifier-for-woocommerce'),
				'button_label' => __('Subscribe Now', 'back-in-stock-notifier-for-woocommerce'),
				'empty_error_message' => __('Email Address cannot be empty', 'back-in-stock-notifier-for-woocommerce'),
				'invalid_email_error' => __('Please enter valid Email Address', 'back-in-stock-notifier-for-woocommerce'),
				'enable_success_sub_mail' => '1',
				'success_sub_subject' => __('You subscribed to {product_name} at {shopname}', 'back-in-stock-notifier-for-woocommerce'),
				'success_sub_message' => $success_subscribe_message,
				'enable_instock_mail' => '1',
				'instock_mail_subject' => __('Product {product_name} is back in stock', 'back-in-stock-notifier-for-woocommerce'),
				'instock_mail_message' => $instock_message,
				'success_subscription' => __('You have successfully subscribed, we will inform you when this product back in stock', 'back-in-stock-notifier-for-woocommerce'),
				'already_subscribed' => __('Seems like you have already subscribed to this product', 'back-in-stock-notifier-for-woocommerce'),
				'empty_name_message' => __('Name cannot be empty', 'back-in-stock-notifier-for-woocommerce'),
				'invalid_phone_error' => __('Please enter valid Phone Number', 'back-in-stock-notifier-for-woocommerce'),
				'phone_number_too_short' => __('Phone number is too short', 'back-in-stock-notifier-for-woocommerce'),
				'phone_number_too_long' => __('Phone number is too long', 'back-in-stock-notifier-for-woocommerce'),
				));

			if (is_array($data) && ! empty($data)) {
				add_option('cwginstocksettings', $data);
			}
			$get_data = get_option('cwginstocksettings');

			if (! isset($get_data['specific_categories_visibility'])) {
				$get_data['specific_categories_visibility'] = '1';
				$get_data['specific_products_visibility']   = '1';
				update_option('cwginstocksettings', $get_data);
			}

			$get_data = get_option('cwginstocksettings');
			if (! isset($get_data['specific_tags_visibility'])) {
				$get_data['specific_tags_visibility'] = '1';
				update_option('cwginstocksettings', $get_data);
			}
			/**
			 * Action related to default settings
			 *
			 * @since 1.0.0
			 */
			do_action('cwginstock_settings_default');
		}

		public function sanitize_data( $input) {
			/**
			 * Filter for textarea fields
			 *
			 * @since 1.0.0
			 */
			$textarea_field = apply_filters('cwg_instock_textarea_fields', array('instock_mail_message', 'success_sub_message', 'hide_form_for_guest_msg'));
			if (is_array($input) && ! empty($input)) {
				foreach ($input as $key => $value) {
					if (! is_array($value)) {
						if (in_array($key, $textarea_field)) {
							$input[$key] = $this->api->sanitize_textarea_field($value);
						} else {
							$input[$key] = $this->api->sanitize_text_field($value);
						}
					}
				}
			}

			return $input;
		}
	}

	new CWG_Instock_Settings();
}
