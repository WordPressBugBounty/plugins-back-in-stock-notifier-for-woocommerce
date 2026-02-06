<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class CWG_Instock_Mailer {

	public function __construct() {

	}

	public function from_email() {
		$options = get_option( 'cwginstocksettings' );
		$from_email = ! empty( $options['mail_from_email'] ) ? $options['mail_from_email'] : get_option( 'woocommerce_email_from_address', get_bloginfo( 'admin_email' ) );
		/**
		 *  Modify the "From" email address
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'cwginstock_from_email', $from_email );
	}

	public function from_name() {
		$options = get_option( 'cwginstocksettings' );
		$from_name = ! empty( $options['mail_from_name'] ) ? $options['mail_from_name'] : get_option( 'woocommerce_email_from_name', get_bloginfo( 'name' ) );
		/**
		 *  Modify the "From" name
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'cwginstock_from_name', $from_name );
	}

	public function reply_to_email() {
		$options = get_option( 'cwginstocksettings' );
		$reply_to = ! empty( $options['mail_reply_to'] ) ? $options['mail_reply_to'] : '';
		/**
		 *  Modify the "Reply To" email address
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'cwginstock_reply_to_email', $reply_to );
	}

	public function format_data( $message ) {
		$replace = html_entity_decode( $message );
		return $replace;
	}

	public function get_subject() {
		/**
		 * Replace shortcode in subject
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'cwg' . $this->slug . '_subject', $this->format_data( do_shortcode( $this->replace_shortcode( $this->get_subject ) ) ), $this->subscriber_id );
	}

	public function get_message() {
		/**
		 * Replace shortcode in message
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'cwg' . $this->slug . '_message', do_shortcode( $this->replace_shortcode( $this->get_message ) ), $this->subscriber_id );
	}

	private function replace_shortcode( $content ) {
		$obj = new CWG_Instock_API();
		if ( ! get_post_meta( $this->subscriber_id, 'cwginstock_bypass_pid', true ) ) {
			$pid = get_post_meta( $this->subscriber_id, 'cwginstock_pid', true );
		} else {
			$pid = get_post_meta( $this->subscriber_id, 'cwginstock_bypass_pid', true );
		}
		$product_name         = $obj->display_product_name( $this->subscriber_id );
		$only_product_name    = $obj->display_only_product_name( $this->subscriber_id );
		$product_link         = $obj->display_product_link( $this->subscriber_id );
		$only_product_sku     = $obj->get_product_sku( $this->subscriber_id );
		$product_price        = $obj->get_product_price( $this->subscriber_id );
		$product_image        = $obj->get_product_image( $this->subscriber_id );
		$subscriber_name      = $obj->get_subscriber_name( $this->subscriber_id );
		$subscriber_firstname = $obj->get_subscriber_firstname( $this->subscriber_id );
		$subscriber_lastname  = $obj->get_subscriber_lastname( $this->subscriber_id );
		$subscriber_phone     = $obj->get_subscriber_phone( $this->subscriber_id );
		$cart_url             = $obj->get_cart_link( $this->subscriber_id ); // esc_url_raw(add_query_arg('add-to-cart', $pid, get_permalink(wc_get_page_id('cart'))));
		$blogname             = get_bloginfo( 'name' );
		$find_array           = array( '{product_name}', '{product_id}', '{product_link}', '{shopname}', '{email_id}', '{subscriber_email}', '{cart_link}', '{only_product_name}', '{only_product_sku}', '{product_price}', '{product_image}', '{subscriber_name}', '{subscriber_phone}', '{subscriber_firstname}', '{subscriber_lastname}' );
		$replace_array        = array( wp_strip_all_tags( $product_name ), $pid, $product_link, $blogname, $this->email, $this->email, $cart_url, $only_product_name, $only_product_sku, $product_price, $product_image, $subscriber_name, $subscriber_phone, $subscriber_firstname, $subscriber_lastname );
		$formatted_content    = str_replace( $find_array, $replace_array, $content );
		/**
		 * Replace shortcode
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'cwginstock_replace_shortcode', $formatted_content, $this->subscriber_id );
	}

	public function format_html_message() {
		ob_start();
		if ( function_exists( 'wc_get_template' ) ) {
			// wc_get_template('emails/email-header.php', array('email_heading' => $this->get_subject()));
			/**
			 * Customize the header text
			 *
			 * @since 1.0.0
			 */
			do_action( 'woocommerce_email_header', $this->get_subject(), null );
			echo do_shortcode( $this->get_message() );
			/**
			 * Customize the footer text
			 *
			 * @since 1.0.0
			 */
			do_action( 'woocommerce_email_footer', get_option( 'woocommerce_email_footer_text' ) );
			// wc_get_template('emails/email-footer.php');
		} else {
			woocommerce_get_template( 'emails/email-header.php', array( 'email_heading' => $this->get_subject() ) );
			echo do_shortcode( $this->get_message() );
			woocommerce_get_template( 'emails/email-footer.php' );
		}
		return ob_get_clean();
	}

	public function send() {
		$to = $this->email;

		$mailer   = WC()->mailer();
		
		// Set the from email and name for WooCommerce mailer
		add_filter( 'woocommerce_email_from_address', array( $this, 'from_email' ), 10, 1 );
		add_filter( 'woocommerce_email_from_name', array( $this, 'from_name' ), 10, 1 );
		
		// Add reply-to header if set using wp_mail hook
		$reply_to = $this->reply_to_email();
		if ( ! empty( $reply_to ) ) {
			add_filter( 'wp_mail', array( $this, 'add_reply_to_wp_mail' ), 10, 1 );
		}
		
		$sendmail = $mailer->send( $to, $this->get_subject(), $this->format_html_message() );
		
		// Remove filters
		remove_filter( 'woocommerce_email_from_address', array( $this, 'from_email' ), 10 );
		remove_filter( 'woocommerce_email_from_name', array( $this, 'from_name' ), 10 );
		if ( ! empty( $reply_to ) ) {
			remove_filter( 'wp_mail', array( $this, 'add_reply_to_wp_mail' ), 10 );
		}
		
		/**
		 * Send Mail
		 *
		 * @since 1.0.0
		 */
		do_action( 'cwg_instock_after_' . $this->slug . '_mail', $to, $this->subscriber_id );
		if ( 'subscribe' == $this->slug ) {
			$option = get_option( 'cwginstock_imail_settings' );
			/**
			 * Filter for modifying the subject
			 *
			 * @since 1.0.0
			 */
			$this->get_subject = apply_filters( 'cwgimail_raw_subject', $option['copy_sub_subject'], $this->subscriber_id );
			/**
			 * Filter for modifying the message
			 *
			 * @since 1.0.0
			 */
			$this->get_message = apply_filters( 'cwgimail_raw_message', nl2br( $option['copy_sub_message'] ), $this->subscriber_id );

			/**
			 * Additionally Send Subscription mail as a copy to specific email ids
			 *
			 * @since 1.0.0
			 */
			do_action( 'cwg_instock_mail_send_as_copy', $to, $this->get_subject(), $this->format_html_message() );
		}
		if ( $sendmail ) {
			/**
			 * Mail Sent Success
			 *
			 * @since 1.0.0
			 */
			do_action( 'cwg_' . $this->slug . '_mail_sent_success', $this->subscriber_id );
			return true;
		} else {
			/**
			 * Mail Sent Failure
			 *
			 * @since 1.0.0
			 */
			do_action( 'cwg_' . $this->slug . '_mail_sent_failure', $this->subscriber_id );
			return false;
		}
	}

	public function add_reply_to_wp_mail( $args ) {
		$reply_to = $this->reply_to_email();
		if ( ! empty( $reply_to ) ) {
			if ( ! isset( $args['headers'] ) ) {
				$args['headers'] = array();
			} elseif ( is_string( $args['headers'] ) ) {
				$args['headers'] = array( $args['headers'] );
			}
			$args['headers'][] = 'Reply-To: ' . sanitize_email( $reply_to );
		}
		return $args;
	}

}
