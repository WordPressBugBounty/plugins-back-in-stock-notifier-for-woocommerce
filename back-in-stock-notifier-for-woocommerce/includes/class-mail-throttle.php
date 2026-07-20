<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CWG_Instock_Mail_Throttle' ) ) {

	/**
	 * Limits how many automatic instock emails go out per minute.
	 *
	 * Applies only to automatic background sends. Manual sends, bulk sends
	 * and subscription confirmation emails are never throttled. Disabled by
	 * default, existing behaviour is unchanged until the merchant opts in.
	 *
	 * @since 7.3.0
	 */
	class CWG_Instock_Mail_Throttle {

		const COUNTER_KEY = 'cwg_bis_mail_rate';

		public static function is_enabled() {
			$options = get_option( 'cwginstocksettings', array() );
			return is_array( $options ) && isset( $options['enable_email_throttle'] ) && '1' == $options['enable_email_throttle'];
		}

		/**
		 * Allowed emails per minute, clamped to a safe range.
		 *
		 * @return int
		 */
		public static function get_rate() {
			$options = get_option( 'cwginstocksettings', array() );
			$rate    = isset( $options['emails_per_minute'] ) ? absint( $options['emails_per_minute'] ) : 60;
			if ( $rate < 1 ) {
				$rate = 60;
			}
			$rate = min( $rate, 500 );
			/**
			 * Filter the allowed automatic emails per minute.
			 *
			 * @since 7.3.0
			 */
			return (int) apply_filters( 'cwginstock_emails_per_minute', $rate );
		}

		/**
		 * Try to reserve one send slot in the current minute window.
		 *
		 * @return bool True when sending is allowed (slot reserved).
		 */
		public static function try_acquire() {
			if ( ! self::is_enabled() ) {
				return true;
			}

			$window = (int) floor( time() / MINUTE_IN_SECONDS );
			$key    = self::COUNTER_KEY . '_' . $window;
			$count  = (int) get_transient( $key );

			if ( $count >= self::get_rate() ) {
				return false;
			}

			set_transient( $key, $count + 1, 2 * MINUTE_IN_SECONDS );
			return true;
		}

		/**
		 * Wait up to $max_wait seconds for a free send slot.
		 *
		 * Only called from background processes, never from normal requests.
		 *
		 * @param int $max_wait Maximum seconds to wait.
		 * @return bool True once a slot is reserved, false on timeout.
		 */
		public static function wait_for_slot( $max_wait = 10 ) {
			if ( self::try_acquire() ) {
				return true;
			}

			$waited = 0;
			while ( $waited < $max_wait ) {
				sleep( 1 );
				$waited++;
				if ( self::try_acquire() ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Re-schedule the given subscriber ids to be processed a minute later.
		 *
		 * @param array $ids Subscriber post ids.
		 * @return bool True when the deferral was scheduled.
		 */
		public static function defer( array $ids ) {
			$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
			if ( empty( $ids ) || ! function_exists( 'as_schedule_single_action' ) ) {
				return false;
			}

			as_schedule_single_action( time() + 70, 'cwginstock_notify_process', array( 'pid' => $ids ), 'back-in-stock-notifier-for-woocommerce' );

			$count  = count( $ids );
			$rate   = self::get_rate();
			$logger = new CWG_Instock_Logger( 'info', "Email throttle: limit of $rate emails per minute reached, $count mail(s) deferred to the next minute" );
			$logger->record_log();

			return true;
		}
	}
}
