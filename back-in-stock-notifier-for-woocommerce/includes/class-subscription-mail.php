<?php
/**
 * Subscription Confirmation Mailer
 *
 * Since 7.0.0 this class delegates to the WooCommerce email system
 * (WC_Email_BIS_Subscription) while maintaining backward compatibility
 * with old settings and filter hooks.
 *
 * @package BackInStockNotifier
 */

class CWG_Instock_Subscription extends CWG_Instock_Mailer {

	protected $slug;
	protected $subscriber_id;
	protected $email;
	protected $get_subject;
	protected $get_message;

	public function __construct( $subscriber_id ) {
		parent::__construct();
		$this->slug          = 'subscribe';
		$this->subscriber_id = $subscriber_id;
		$this->email         = get_post_meta( $subscriber_id, 'cwginstock_subscriber_email', true );

		/**
		 * Action triggers before subscription mail.
		 *
		 * @since 1.0.0
		 */
		do_action( 'cwg_instock_before_' . $this->slug . '_mail', $this->email, $this->subscriber_id );

		// Read from old settings for backward compatibility with filters
		$option = get_option( 'cwginstocksettings' );

		/**
		 * Filter for modifying the subject.
		 *
		 * @since 1.0.0
		 */
		$this->get_subject = apply_filters( 'cwgsubscribe_raw_subject', $option['success_sub_subject'], $subscriber_id );

		/**
		 * Filter for modifying the message.
		 *
		 * @since 1.0.0
		 */
		$this->get_message = apply_filters( 'cwgsubscribe_raw_message', nl2br( $option['success_sub_message'] ), $subscriber_id );
	}

	/**
	 * Send method — delegates to WC email if available.
	 *
	 * @since 7.0.0
	 * @return bool
	 */
	public function send() {
		// Try to use the WC email system first
		if ( function_exists( 'WC' ) && WC()->mailer() ) {
			$wc_emails = WC()->mailer()->get_emails();
			if ( isset( $wc_emails['WC_Email_BIS_Subscription'] ) ) {
				$wc_email = $wc_emails['WC_Email_BIS_Subscription'];

				// Check if enabled via WC settings
				$wc_settings = get_option( 'woocommerce_cwg_bis_subscription_settings', array() );
				$is_enabled  = isset( $wc_settings['enabled'] ) ? $wc_settings['enabled'] : 'yes';

				// Also check old setting for backward compatibility
				$old_option  = get_option( 'cwginstocksettings', array() );
				$old_enabled = isset( $old_option['enable_success_sub_mail'] ) ? $old_option['enable_success_sub_mail'] : '1';

				if ( 'yes' === $is_enabled || '1' === $old_enabled ) {
					$wc_email->trigger( $this->subscriber_id );

					// Handle copy mail functionality
					$this->handle_copy_mail();
					

					/**
					 * Mail Sent Success
					 *
					 * @since 1.0.0
					 */
					do_action( 'cwg_' . $this->slug . '_mail_sent_success', $this->subscriber_id );
					return true;
				}

				return false;
			}
		}

		// Fallback to legacy send method
		return parent::send();
	}

	/**
	 * Handle the copy mail functionality from the original send flow.
	 *
	 * @since 7.0.0
	 */
	private function handle_copy_mail() {
		$option = get_option( 'cwginstock_imail_settings' );
		if ( ! empty( $option ) ) {
			/**
			 * Filter for modifying the subject
			 */
			$this->get_subject = apply_filters( 'cwgimail_raw_subject', $option['copy_sub_subject'], $this->subscriber_id );
			/**
			 * Filter for modifying the message
			 */
			$this->get_message = apply_filters( 'cwgimail_raw_message', nl2br( $option['copy_sub_message'] ), $this->subscriber_id );

			/**
			 * Additionally Send Subscription mail as a copy to specific email ids
			 */
			do_action( 'cwg_instock_mail_send_as_copy', $this->email, $this->get_subject(), $this->format_html_message() );
		}
	}
}
