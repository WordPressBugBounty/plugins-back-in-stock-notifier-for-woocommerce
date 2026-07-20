<?php
/**
 * WooCommerce Email: Back In Stock - Subscription Confirmation
 *
 * All shortcodes work in Subject, Heading, and Additional Content:
 * {product_name}, {only_product_name}, {product_id}, {product_link},
 * {product_price}, {only_product_sku}, {subscriber_name},
 * {subscriber_firstname}, {subscriber_lastname}, {subscriber_email},
 * {subscriber_phone}, {shopname}
 *
 * @package BackInStockNotifier
 * @since   7.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Email_BIS_Subscription' ) ) {

	class WC_Email_BIS_Subscription extends WC_Email {

		/**
		 * Subscriber post ID.
		 *
		 * @var int
		 */
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

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->id             = 'cwg_bis_subscription';
			$this->customer_email = true;
			$this->title          = __( 'Back In Stock - Subscription Confirmation', 'back-in-stock-notifier-for-woocommerce' );
			$this->description    = __( 'Sent to the subscriber immediately after they subscribe to a back-in-stock notification. <strong>Available placeholders:</strong> <code>{product_name}</code>, <code>{product_id}</code>, <code>{product_link}</code>, <code>{shopname}</code>, <code>{email_id}</code>, <code>{subscriber_email}</code>, <code>{cart_link}</code>, <code>{only_product_name}</code>, <code>{only_product_sku}</code>, <code>{product_price}</code>, <code>{product_image}</code>, <code>{subscriber_name}</code>, <code>{subscriber_phone}</code>, <code>{subscriber_firstname}</code>, <code>{subscriber_lastname}</code>.', 'back-in-stock-notifier-for-woocommerce' );
			$this->template_html  = 'emails/bis-subscription.php';
			$this->template_plain = 'emails/plain/bis-subscription.php';

			// All shortcodes as WC placeholders - these work in Subject, Heading, Additional Content
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
			);

			// Default values
			$this->heading = __( 'Thanks for subscribing to {product_name}', 'back-in-stock-notifier-for-woocommerce' );
			$this->subject = __( 'You subscribed to {product_name} at {shopname}', 'back-in-stock-notifier-for-woocommerce' );

			parent::__construct();

			// Template path inside the plugin
			$this->template_base = CWGINSTOCK_PLUGINDIR . 'templates/';

			// Keep a copy of the default placeholders to reset between sends.
			$this->base_placeholders = $this->placeholders;
		}

		/**
		 * Get default additional content.
		 *
		 * @return string
		 */
		public function get_default_additional_content() {
			return __( 'Hello {subscriber_name},<br/><br/>Thank you for subscribing to {product_name} (#{product_id}). We will send an update to {subscriber_email} as soon as this item is back in stock. You can review the product here: {product_link}.<br/><br/>Thanks for shopping with {shopname}.', 'back-in-stock-notifier-for-woocommerce' );
		}

		/**
		 * Populate all placeholders from subscriber data.
		 * Must be called BEFORE send() so shortcodes resolve in Subject, Heading, Content.
		 *
		 * @param int $subscriber_id The subscriber post ID.
		 */
		private function populate_placeholders( $subscriber_id ) {

			// Reset placeholders so values from the previous subscriber
			// do not leak into this email.
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

			// Populate placeholders before send() so shortcodes resolve in
			// Subject, Heading and Additional Content.
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
		 * Get content HTML.
		 *
		 * @return string
		 */
		public function get_content_html() {
			cwg_bis_remove_villa_header_override();
			return wc_get_template_html(
				$this->template_html,
				array(
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'subscriber_id'      => $this->subscriber_id,
					'subscriber_name'    => $this->placeholders['{subscriber_name}'],
					'product_name'       => $this->placeholders['{product_name}'],
					'product_link'       => $this->placeholders['{product_link}'],
					'blogname'           => $this->get_blogname(),
					'sent_to_admin'      => false,
					'plain_text'         => false,
					'email'              => $this,
				),
				'',
				$this->template_base
			);
		}

		/**
		 * Get content plain text.
		 *
		 * @return string
		 */
		public function get_content_plain() {
			cwg_bis_remove_villa_header_override();
			return wc_get_template_html(
				$this->template_plain,
				array(
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'subscriber_id'      => $this->subscriber_id,
					'subscriber_name'    => $this->placeholders['{subscriber_name}'],
					'product_name'       => $this->placeholders['{product_name}'],
					'product_link'       => $this->placeholders['{product_link}'],
					'blogname'           => $this->get_blogname(),
					'sent_to_admin'      => false,
					'plain_text'         => true,
					'email'              => $this,
				),
				'',
				$this->template_base
			);
		}

		/**
		 * Initialise settings form fields.
		 * Lists ALL available shortcodes for Subject and Heading.
		 */
		public function init_form_fields() {
			/* translators: %s: list of shortcodes */
			$placeholder_text = sprintf(
				__( 'Available shortcodes: %s', 'back-in-stock-notifier-for-woocommerce' ),
				'<code>{product_name}</code>, <code>{product_id}</code>, <code>{product_link}</code>, <code>{shopname}</code>, <code>{email_id}</code>, <code>{subscriber_email}</code>, <code>{cart_link}</code>, <code>{only_product_name}</code>, <code>{only_product_sku}</code>, <code>{product_price}</code>, <code>{product_image}</code>, <code>{subscriber_name}</code>, <code>{subscriber_phone}</code>, <code>{subscriber_firstname}</code>, <code>{subscriber_lastname}</code>'
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
