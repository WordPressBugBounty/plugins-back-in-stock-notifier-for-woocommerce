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
		 * Constructor.
		 */
		public function __construct() {
			$this->id             = 'cwg_bis_subscription';
			$this->customer_email = true;
			$this->title          = __( 'Back In Stock - Subscription Confirmation', 'back-in-stock-notifier-for-woocommerce' );
			$this->description    = __( 'Sent to the subscriber immediately after they subscribe to a back-in-stock notification.', 'back-in-stock-notifier-for-woocommerce' );
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
			$this->heading = __( 'Subscription Confirmed', 'back-in-stock-notifier-for-woocommerce' );
			$this->subject = __( 'You subscribed to {product_name} at {shopname}', 'back-in-stock-notifier-for-woocommerce' );

			parent::__construct();

			// Template path inside the plugin
			$this->template_base = CWGINSTOCK_PLUGINDIR . 'templates/';
		}

		/**
		 * Get default additional content.
		 *
		 * @return string
		 */
		public function get_default_additional_content() {
			return __( 'We will email you as soon as this product is back in stock.', 'back-in-stock-notifier-for-woocommerce' );
		}

		/**
		 * Populate all placeholders from subscriber data.
		 * Must be called BEFORE send() so shortcodes resolve in Subject, Heading, Content.
		 *
		 * @param int $subscriber_id The subscriber post ID.
		 */
		private function populate_placeholders( $subscriber_id ) {

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
			$this->placeholders['{product_image}']        = $api->get_product_image( $subscriber_id ); // ✅ added
			$this->placeholders['{cart_link}']            = $api->get_cart_link( $subscriber_id );     // ✅ added
			$this->placeholders['{subscriber_name}']      = $api->get_subscriber_name( $subscriber_id );
			$this->placeholders['{subscriber_firstname}'] = $api->get_subscriber_firstname( $subscriber_id );
			$this->placeholders['{subscriber_lastname}']  = $api->get_subscriber_lastname( $subscriber_id );
			$this->placeholders['{subscriber_email}']     = $this->recipient;
			$this->placeholders['{email_id}']             = $this->recipient; // ✅ legacy support
			$this->placeholders['{subscriber_phone}']     = $api->get_subscriber_phone( $subscriber_id );
			$this->placeholders['{shopname}']             = $this->get_blogname();

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

		/**
		 * Trigger the sending of this email.
		 *
		 * @param int $subscriber_id The subscriber post ID.
		 */
		public function trigger( $subscriber_id ) {
			$this->setup_locale();

			if ( ! $subscriber_id ) {
				$this->restore_locale();
				return;
			}

			$this->subscriber_id = absint( $subscriber_id );
			$this->recipient     = get_post_meta( $this->subscriber_id, 'cwginstock_subscriber_email', true );

			if ( ! $this->recipient ) {
				$this->restore_locale();
				return;
			}

			// Populate ALL placeholders BEFORE send() — ensures shortcodes resolve
			// in Subject, Heading, and Additional Content via WC's format_string()
			$this->populate_placeholders( $this->subscriber_id );

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}

			$this->restore_locale();
		}

		/**
		 * Get content HTML.
		 *
		 * @return string
		 */
		public function get_content_html() {
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
				'<code>{product_name}</code>, <code>{only_product_name}</code>, <code>{product_id}</code>, <code>{product_link}</code>, <code>{product_price}</code>, <code>{only_product_sku}</code>, <code>{subscriber_name}</code>, <code>{subscriber_firstname}</code>, <code>{subscriber_lastname}</code>, <code>{subscriber_email}</code>, <code>{subscriber_phone}</code>, <code>{shopname}</code>'
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
