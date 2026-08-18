<?php
/**
 * WooCommerce Email: Back In Stock - Product Available
 *
 * All shortcodes from the legacy system are supported as placeholders:
 * {product_name}, {only_product_name}, {product_id}, {product_link},
 * {product_price}, {product_image}, {only_product_sku}, {cart_link},
 * {subscriber_name}, {subscriber_firstname}, {subscriber_lastname},
 * {subscriber_email}, {email_id}, {subscriber_phone}, {shopname}
 *
 * @package BackInStockNotifier
 * @since   7.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Email_BIS_Instock' ) ) {

	class WC_Email_BIS_Instock extends WC_Email {

		public $subscriber_id = 0;

		/**
		 * Default placeholder set used to reset state between sends.
		 *
		 * @since 7.3.0
		 * @var array
		 */
		protected $base_placeholders = array();

		/**
		 * Whether wp_mail reported a delivery failure during the current send.
		 *
		 * @since 7.3.0
		 * @var bool
		 */
		protected $mail_failed = false;

		/**
		 * Whether wp_mail confirmed the send during the current send.
		 *
		 * @since 7.3.0
		 * @var bool
		 */
		protected $mail_succeeded = false;

		/**
		 * Whether wp_mail was short-circuited (blocked) during the current send.
		 *
		 * @since 7.3.0
		 * @var bool
		 */
		protected $mail_blocked = false;

		public function __construct() {
			$this->id             = 'cwg_bis_instock';
			$this->customer_email = true;
			$this->title          = __( 'Back In Stock - Product Available', 'back-in-stock-notifier-for-woocommerce' );
			$this->description    = __( 'Sent to subscribers when a product they subscribed to is back in stock. <strong>Available placeholders:</strong> <code>{product_name}</code>, <code>{product_id}</code>, <code>{product_link}</code>, <code>{shopname}</code>, <code>{email_id}</code>, <code>{subscriber_email}</code>, <code>{cart_link}</code>, <code>{only_product_name}</code>, <code>{only_product_sku}</code>, <code>{product_price}</code>, <code>{product_image}</code>, <code>{subscriber_name}</code>, <code>{subscriber_phone}</code>, <code>{subscriber_firstname}</code>, <code>{subscriber_lastname}</code>, <code>{product_table}</code>, <code>{coupon_code}</code>, <code>{coupon_amount}</code>, <code>{coupon_expiry}</code>. <strong>{product_table}</strong> renders an order style table for the subscribed product. The coupon placeholders require Auto Coupon to be enabled under Instock Notifier &gt; Settings &gt; Auto Coupon, and a coupon is only created when one of them is used here.', 'back-in-stock-notifier-for-woocommerce' );
			$this->template_html  = 'emails/bis-instock.php';
			$this->template_plain = 'emails/plain/bis-instock.php';

			// All legacy shortcodes as WC placeholders
			$this->placeholders = array(
				'{product_name}'         => '',
				'{only_product_name}'    => '',
				'{product_id}'           => '',
				'{product_link}'         => '',
				'{product_price}'        => '',
				'{product_image}'        => '',
				'{only_product_sku}'     => '',
				'{cart_link}'            => '',
				'{subscriber_name}'      => '',
				'{subscriber_firstname}' => '',
				'{subscriber_lastname}'  => '',
				'{subscriber_email}'     => '',
				'{email_id}'             => '',
				'{subscriber_phone}'     => '',
				'{shopname}'             => $this->get_blogname(),
				'{product_table}'        => '',
			);

			$this->heading = __( 'Your subscribed item {product_name} is now available', 'back-in-stock-notifier-for-woocommerce' );
			$this->subject = __( 'Good news! {product_name} is back in stock', 'back-in-stock-notifier-for-woocommerce' );

			parent::__construct();
			$this->template_base = CWGINSTOCK_PLUGINDIR . 'templates/';

			// Keep a copy of the default placeholders to reset between sends.
			$this->base_placeholders = $this->placeholders;

			// Supply sample values for the WooCommerce email preview screen.
			add_filter( 'woocommerce_email_preview_placeholders', array( $this, 'preview_placeholders' ), 10, 2 );
		}

		public function get_default_additional_content() {
			return __( 'Hello {subscriber_name},<br/><br/>Good news! {product_name} is now back in stock. View it here: {product_link} or add it directly to your cart: {cart_link}. We only have limited stock available, so please act quickly. Thanks for subscribing with {shopname}.', 'back-in-stock-notifier-for-woocommerce' );
		}

		/**
		 * Populate all placeholders from subscriber data.
		 */
		private function populate_placeholders( $subscriber_id ) {
			// Reset placeholders so values from the previous subscriber
			// in the batch do not leak into this email.
			if ( ! empty( $this->base_placeholders ) ) {
				$this->placeholders = $this->base_placeholders;
			}

			$api = new CWG_Instock_API();

			if ( ! get_post_meta( $subscriber_id, 'cwginstock_bypass_pid', true ) ) {
				$pid = get_post_meta( $subscriber_id, 'cwginstock_pid', true );
			} else {
				$pid = get_post_meta( $subscriber_id, 'cwginstock_bypass_pid', true );
			}

			$this->placeholders['{product_name}']         = wp_strip_all_tags( $api->display_product_name( $subscriber_id ) );
			$this->placeholders['{only_product_name}']    = $api->display_only_product_name( $subscriber_id );
			$this->placeholders['{product_id}']           = $pid;
			$this->placeholders['{product_link}']         = $api->display_product_link( $subscriber_id );
			$this->placeholders['{product_price}']        = $api->get_product_price( $subscriber_id );
			$this->placeholders['{only_product_sku}']     = $api->get_product_sku( $subscriber_id );
			$this->placeholders['{product_image}']        = $api->get_product_image( $subscriber_id ); 
			$this->placeholders['{cart_link}']            = $api->get_cart_link( $subscriber_id );
			$this->placeholders['{subscriber_name}']      = $api->get_subscriber_name( $subscriber_id );
			$this->placeholders['{subscriber_firstname}'] = $api->get_subscriber_firstname( $subscriber_id );
			$this->placeholders['{subscriber_lastname}']  = $api->get_subscriber_lastname( $subscriber_id );
			$this->placeholders['{subscriber_email}']     = $this->recipient;
			$this->placeholders['{email_id}']             = $this->recipient; 
			$this->placeholders['{subscriber_phone}']     = $api->get_subscriber_phone( $subscriber_id );
			$this->placeholders['{shopname}']             = $this->get_blogname();

			// Order style line item table for the subscribed product.
			if ( function_exists( 'cwg_instock_get_product_table_html' ) ) {
				$this->placeholders['{product_table}'] = cwg_instock_get_product_table_html( $subscriber_id, false );
			}


			$this->apply_legacy_shortcodes( $subscriber_id );
			/**
			 * Allow developers to modify placeholders
			 *
			 * @since 7.0.0
			 */
			$this->placeholders = apply_filters(
				'cwginstock_email_placeholders',
				$this->placeholders,
				$subscriber_id,
				$this
			);
		}

		protected function apply_legacy_shortcodes( $subscriber_id ) {

			// No add-on listening - nothing to resolve.
			if ( ! has_filter( 'cwginstock_replace_shortcode' ) ) {
				return;
			}

			/**
			 * Tokens handed to the legacy `cwginstock_replace_shortcode` filter.
			 *
			 * Add-ons that still rely on the pre-7.0.0 shortcode system should
			 * register their tokens here.
			 *
			 * @since 7.3.0
			 *
			 * @param string[] $tokens Placeholder tokens, braces included.
			 * @param WC_Email $email  Current email instance.
			 */
			$tokens = apply_filters(
				'cwginstock_legacy_shortcode_tokens',
				array( '{cwginstock_unsubscribe}' ),
				$this
			);

			foreach ( array_unique( (array) $tokens ) as $token ) {

				// Never clobber a value core already resolved.
				if ( isset( $this->placeholders[ $token ] ) && '' !== $this->placeholders[ $token ] ) {
					continue;
				}

				/**
				 * Allow add-ons using the legacy shortcode system to resolve placeholder tokens.
				 *
				 * @since 7.3.0
				 */
				$value = apply_filters( 'cwginstock_replace_shortcode', $token, $subscriber_id);

				// Register only if a listener actually returned something new.
				if ( is_scalar( $value ) && (string) $value !== $token ) {
					$this->placeholders[ $token ] = $value;
				}
			}
		}




		/**
		 * Trigger the sending of this email.
		 *
		 * @param int $subscriber_id The subscriber post ID.
		 * @return bool|null True when sent, false when the send failed,
		 *                   null when no send was attempted (disabled/invalid id).
		 */
		public function trigger( $subscriber_id ) {
			$this->setup_locale();

			if ( ! $subscriber_id ) {
				$this->restore_locale();
				return null;
			}

			$this->subscriber_id = absint( $subscriber_id );
			$this->recipient     = get_post_meta( $this->subscriber_id, 'cwginstock_subscriber_email', true );

			if ( ! $this->recipient ) {
				$this->restore_locale();
				return false;
			}

			if ( ! $this->is_enabled() ) {
				// Email disabled, nothing attempted.
				$this->restore_locale();
				return null;
			}

			$this->populate_placeholders( $this->subscriber_id );

			if ( ! $this->get_recipient() ) {
				$this->restore_locale();
				return false;
			}

			// Track the wp_mail outcome events so the reported status reflects
			// what actually happened. Some SMTP plugins replace the mail
			// callback and return nothing on success or block sending entirely.
			$this->mail_failed    = false;
			$this->mail_succeeded = false;
			$this->mail_blocked   = false;
			add_action( 'wp_mail_failed', array( $this, 'catch_mail_failure' ) );
			add_action( 'wp_mail_succeeded', array( $this, 'catch_mail_success' ) );
			add_filter( 'pre_wp_mail', array( $this, 'catch_mail_block' ), PHP_INT_MAX );

			$send_result = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

			remove_action( 'wp_mail_failed', array( $this, 'catch_mail_failure' ) );
			remove_action( 'wp_mail_succeeded', array( $this, 'catch_mail_success' ) );
			remove_filter( 'pre_wp_mail', array( $this, 'catch_mail_block' ), PHP_INT_MAX );

			if ( ! $send_result ) {
				if ( $this->mail_succeeded ) {
					// Mail went out but the return value was swallowed.
					$send_result = true;
				} elseif ( $this->mail_failed || $this->mail_blocked ) {
					// Real failure, or a plugin blocked the send.
					$send_result = false;
				} else {
					/**
					 * No success/failure event fired, a queueing plugin likely
					 * replaced the mail callback. Treat as sent/queued.
					 *
					 * @since 7.3.0
					 */
					$send_result = (bool) apply_filters( 'cwginstock_assume_sent_on_empty_mail_result', true, $this->subscriber_id, $this );
				}
			}

			$this->restore_locale();
			return $send_result;
		}

		/**
		 * Flag that wp_mail reported a delivery failure during send().
		 *
		 * @since 7.3.0
		 * @param WP_Error $error The wp_mail error.
		 */
		public function catch_mail_failure( $error ) {
			$this->mail_failed = true;
		}

		/**
		 * Flag that wp_mail confirmed the send.
		 *
		 * @since 7.3.0
		 * @param array $mail_data The wp_mail arguments.
		 */
		public function catch_mail_success( $mail_data ) {
			$this->mail_succeeded = true;
		}

		/**
		 * Flag when wp_mail is short-circuited via pre_wp_mail (blocked send).
		 *
		 * @since 7.3.0
		 * @param null|bool $short_circuit Non-null when another plugin blocks the send.
		 * @return null|bool
		 */
		public function catch_mail_block( $short_circuit ) {
			if ( null !== $short_circuit ) {
				$this->mail_blocked = true;
			}
			return $short_circuit;
		}


		/**
		 * Sample placeholder values for the WooCommerce email preview, so the
		 * preview shows realistic content instead of empty or raw placeholders.
		 *
		 * @since 7.4.0
		 * @param array  $placeholders Existing preview placeholders.
		 * @param string $email_type   Email class being previewed.
		 * @return array
		 */
		public function preview_placeholders( $placeholders, $email_type = '' ) {
			if ( ! is_array( $placeholders ) ) {
				$placeholders = array();
			}

			// Only touch the preview of this plugin's own email.
			if ( $email_type && ! in_array( $email_type, array( get_class( $this ), 'cwg_bis_instock' ), true ) ) {
				return $placeholders;
			}

			$sample_product = __( 'Sample Product', 'back-in-stock-notifier-for-woocommerce' );
			$sample_email   = 'customer@example.com';

			$sample = array(
				'{product_name}'         => $sample_product,
				'{only_product_name}'    => $sample_product,
				'{product_id}'           => '123',
				'{product_link}'         => home_url( '/' ),
				'{product_price}'        => function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( 29 ) ) : '29',
				'{only_product_sku}'     => 'SKU-1234',
				'{cart_link}'            => home_url( '/' ),
				'{product_image}'        => '',
				'{subscriber_name}'      => __( 'Jane Doe', 'back-in-stock-notifier-for-woocommerce' ),
				'{subscriber_firstname}' => __( 'Jane', 'back-in-stock-notifier-for-woocommerce' ),
				'{subscriber_lastname}'  => __( 'Doe', 'back-in-stock-notifier-for-woocommerce' ),
				'{subscriber_email}'     => $sample_email,
				'{email_id}'             => $sample_email,
				'{subscriber_phone}'     => '+1 555 0100',
				'{shopname}'             => $this->get_blogname(),
			);

			if ( function_exists( 'cwg_instock_get_product_table_html' ) ) {
				$sample['{product_table}'] = cwg_instock_get_product_table_html( 0, true );
			}

			$sample['{coupon_code}']   = 'SAMPLE-COUPON';
			$sample['{coupon_amount}'] = class_exists( 'CWG_Instock_Auto_Coupon' ) ? CWG_Instock_Auto_Coupon::format_amount() : '10%';
			$sample['{coupon_expiry}'] = wp_date( get_option( 'date_format' ), time() + WEEK_IN_SECONDS );

			return array_merge( $placeholders, $sample );
		}


		/**
		 * Render the email template with sample data for the preview screen.
		 *
		 * @since 7.4.0
		 * @return string
		 */
		protected function get_preview_content_html() {
			cwg_bis_remove_villa_header_override();
			return wc_get_template_html(
				$this->template_html,
				array(
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'subscriber_id'      => 0,
					'subscriber_name'    => __( 'Jane Doe', 'back-in-stock-notifier-for-woocommerce' ),
					'product_name'       => __( 'Sample Product', 'back-in-stock-notifier-for-woocommerce' ),
					'product_link'       => home_url( '/' ),
					'product_image'      => '',
					'product_price'      => function_exists( 'wc_price' ) ? wc_price( 29 ) : '29',
					'cart_link'          => home_url( '/' ),
					'product_id'         => 123,
					'blogname'           => $this->get_blogname(),
					'sent_to_admin'      => false,
					'plain_text'         => false,
					'email'              => $this,
				),
				'',
				$this->template_base
			);
		}

		public function get_content_html() {
			// Preview mode has no real subscriber, so render sample values.
			if ( function_exists( 'cwg_instock_is_email_preview' ) && cwg_instock_is_email_preview() && ! $this->subscriber_id ) {
				return $this->get_preview_content_html();
			}

			$api = new CWG_Instock_API();

			if ( ! get_post_meta( $this->subscriber_id, 'cwginstock_bypass_pid', true ) ) {
				$pid = get_post_meta( $this->subscriber_id, 'cwginstock_pid', true );
			} else {
				$pid = get_post_meta( $this->subscriber_id, 'cwginstock_bypass_pid', true );
			}

			//cwginstock_remove_villa_email_customizer();
			cwg_bis_remove_villa_header_override();
			return wc_get_template_html(
				$this->template_html,
				array(
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'subscriber_id'      => $this->subscriber_id,
					'subscriber_name'    => $api->get_subscriber_name( $this->subscriber_id ),
					'product_name'       => wp_strip_all_tags( $api->display_product_name( $this->subscriber_id ) ),
					'product_link'       => $api->display_product_link( $this->subscriber_id ),
					'product_image'      => $api->get_product_image( $this->subscriber_id ),
					'product_price'      => $api->get_product_price( $this->subscriber_id ),
					'cart_link'          => $api->get_cart_link( $this->subscriber_id ),
					'product_id'         => $pid,
					'blogname'           => $this->get_blogname(),
					'sent_to_admin'      => false,
					'plain_text'         => false,
					'email'              => $this,
				),
				'',
				$this->template_base
			);
		}

		public function get_content_plain() {
			$api = new CWG_Instock_API();
			cwg_bis_remove_villa_header_override();
			return wc_get_template_html(
				$this->template_plain,
				array(
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'subscriber_id'      => $this->subscriber_id,
					'subscriber_name'    => $api->get_subscriber_name( $this->subscriber_id ),
					'product_name'       => wp_strip_all_tags( $api->display_product_name( $this->subscriber_id ) ),
					'product_link'       => $api->display_product_link( $this->subscriber_id ),
					'cart_link'          => $api->get_cart_link( $this->subscriber_id ),
					'product_price'      => $api->get_product_price( $this->subscriber_id ),
					'blogname'           => $this->get_blogname(),
					'sent_to_admin'      => false,
					'plain_text'         => true,
					'email'              => $this,
				),
				'',
				$this->template_base
			);
		}

		public function init_form_fields() {
			$placeholder_text = sprintf(
				__( 'Available shortcodes: %s', 'back-in-stock-notifier-for-woocommerce' ),
				'<code>{product_name}</code>, <code>{product_id}</code>, <code>{product_link}</code>, <code>{shopname}</code>, <code>{email_id}</code>, <code>{subscriber_email}</code>, <code>{cart_link}</code>, <code>{only_product_name}</code>, <code>{only_product_sku}</code>, <code>{product_price}</code>, <code>{product_image}</code>, <code>{subscriber_name}</code>, <code>{subscriber_phone}</code>, <code>{subscriber_firstname}</code>, <code>{subscriber_lastname}</code>, <code>{product_table}</code>, <code>{coupon_code}</code>, <code>{coupon_amount}</code>, <code>{coupon_expiry}</code>'
			);

			$this->form_fields = array(
				'enabled'            => array(
					'title'   => __( 'Enable/Disable', 'back-in-stock-notifier-for-woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable this email notification', 'back-in-stock-notifier-for-woocommerce' ),
					'default' => 'yes',
				),
				'subject'            => array(
					'title'       => __( 'Subject', 'back-in-stock-notifier-for-woocommerce' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => $placeholder_text,
					'placeholder' => $this->get_default_subject(),
					'default'     => '',
				),
				'heading'            => array(
					'title'       => __( 'Email heading', 'back-in-stock-notifier-for-woocommerce' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => $placeholder_text,
					'placeholder' => $this->get_default_heading(),
					'default'     => '',
				),
				'additional_content' => array(
					'title'       => __( 'Additional content', 'back-in-stock-notifier-for-woocommerce' ),
					'description' => __( 'Text to appear below the main email content.', 'back-in-stock-notifier-for-woocommerce' ) . ' ' . $placeholder_text,
					'css'         => 'width:400px; height:75px;',
					'placeholder' => __( 'N/A', 'back-in-stock-notifier-for-woocommerce' ),
					'type'        => 'textarea',
					'default'     => $this->get_default_additional_content(),
					'desc_tip'    => true,
				),
				'email_type'         => array(
					'title'       => __( 'Email type', 'back-in-stock-notifier-for-woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Choose which format of email to send.', 'back-in-stock-notifier-for-woocommerce' ),
					'default'     => 'html',
					'class'       => 'email_type wc-enhanced-select',
					'options'     => $this->get_email_type_options(),
					'desc_tip'    => true,
				),
			);
		}
	}
}
