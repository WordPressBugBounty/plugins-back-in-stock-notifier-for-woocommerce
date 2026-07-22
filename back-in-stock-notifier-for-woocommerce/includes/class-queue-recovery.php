<?php
/**
 * Queue Recovery.
 *
 * Subscribers are moved to the 'cwg_queued' status the moment a product is
 * restocked, then a scheduled action sends their email and moves them to
 * 'cwg_mailsent'. If that scheduled action fails, times out, or Action
 * Scheduler simply never runs it, those subscribers stay stuck in
 * 'cwg_queued' forever and never receive their email.
 *
 * This recurring job finds entries that have been queued longer than a safe
 * threshold (so a normal in-progress send is never disturbed) and reprocesses
 * them through the same send pipeline. Products that are gone or no longer in
 * stock are reverted to 'cwg_subscribed' so they are picked up on the next
 * restock instead of remaining stranded.
 *
 * Works regardless of the chosen background process engine.
 *
 * @package BackInStockNotifier
 * @since   7.3.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CWG_Instock_Queue_Recovery' ) ) {

	class CWG_Instock_Queue_Recovery {

		const AS_HOOK         = 'cwginstock_recover_queued';
		const GROUP           = 'cwg-bis-recovery';
		const INTERVAL_OPTION = 'cwg_bis_recovery_interval';
		const ATTEMPTS_META   = 'cwginstock_recovery_attempts';
		const LAST_META       = 'cwginstock_recovery_last_gmt';

		public function __construct() {
			add_action( self::AS_HOOK, array( $this, 'recover_stranded_queue' ) );
			add_action( 'init', array( $this, 'maybe_schedule' ), 20 );
		}

		/**
		 * Whether the recovery mechanism is enabled. Off by default, the admin
		 * must opt in, so no background task exists until it is turned on.
		 *
		 * @return bool
		 */
		public static function is_enabled() {
			$options = get_option( 'cwginstocksettings', array() );
			$enabled = isset( $options['enable_queue_recovery'] ) && '1' == $options['enable_queue_recovery'];
			/**
			 * Filter to force-enable or force-disable the queue recovery mechanism.
			 *
			 * @since 7.3.1
			 */
			return (bool) apply_filters( 'cwginstock_enable_queue_recovery', $enabled );
		}

		/**
		 * Maximum retry attempts before finalizing (setting, default 2).
		 *
		 * @return int
		 */
		private function max_attempts() {
			$options  = get_option( 'cwginstocksettings', array() );
			$attempts = isset( $options['queue_recovery_max_attempts'] ) ? absint( $options['queue_recovery_max_attempts'] ) : 2;
			$attempts = $attempts > 0 ? $attempts : 2;
			return (int) apply_filters( 'cwginstock_queue_recovery_max_attempts', min( $attempts, 10 ) );
		}

		/**
		 * Status applied after all retries fail (setting, default cwg_mailnotsent).
		 *
		 * @return string
		 */
		private function final_status() {
			$options = get_option( 'cwginstocksettings', array() );
			$status  = isset( $options['queue_recovery_final_status'] ) ? $options['queue_recovery_final_status'] : 'cwg_mailnotsent';
			$allowed = array( 'cwg_mailnotsent', 'cwg_subscribed', 'cwg_unsubscribed' );
			if ( ! in_array( $status, $allowed, true ) ) {
				$status = 'cwg_mailnotsent';
			}
			return $status;
		}

		/**
		 * Ensure the recurring recovery action matches the current settings.
		 * Only schedules when enabled, and reschedules when the frequency
		 * changes. Uses a stored interval so it does not query Action Scheduler
		 * on every page load.
		 */
		public function maybe_schedule() {
			if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
				return;
			}

			$scheduled = (int) get_option( self::INTERVAL_OPTION, 0 );

			// Disabled: remove the task if one is currently scheduled.
			if ( ! self::is_enabled() ) {
				if ( $scheduled ) {
					self::unschedule();
				}
				return;
			}

			$interval = $this->interval_seconds();

			// Already scheduled at the current frequency, nothing to do.
			if ( $scheduled === $interval ) {
				return;
			}

			// First time, or the frequency changed: (re)schedule.
			as_unschedule_all_actions( self::AS_HOOK, array(), self::GROUP );
			as_schedule_recurring_action( time() + $interval, $interval, self::AS_HOOK, array(), self::GROUP );
			update_option( self::INTERVAL_OPTION, $interval, false );
		}

		/**
		 * Remove the scheduled recovery action.
		 */
		public static function unschedule() {
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( self::AS_HOOK, array(), self::GROUP );
			}
			delete_option( self::INTERVAL_OPTION );
		}

		/**
		 * Seconds for the recovery frequency, which is both how often the task
		 * runs and how far apart retries are spaced (setting, default hourly).
		 *
		 * @return int
		 */
		private function interval_seconds() {
			$options   = get_option( 'cwginstocksettings', array() );
			$frequency = isset( $options['queue_recovery_frequency'] ) ? $options['queue_recovery_frequency'] : 'every_hour';

			switch ( $frequency ) {
				case 'every_6_hours':
					$seconds = 6 * HOUR_IN_SECONDS;
					break;
				case 'every_12_hours':
					$seconds = 12 * HOUR_IN_SECONDS;
					break;
				case 'every_day':
					$seconds = DAY_IN_SECONDS;
					break;
				case 'every_hour':
				default:
					$seconds = HOUR_IN_SECONDS;
					break;
			}

			/**
			 * Filter the recovery interval, in seconds. Handy for testing a
			 * shorter interval than the one hour minimum the settings allow.
			 *
			 * @since 7.3.1
			 */
			$seconds = (int) apply_filters( 'cwginstock_queue_recovery_delay', $seconds );
			return max( MINUTE_IN_SECONDS, $seconds );
		}

		/**
		 * Reprocess subscribers stranded in the queue.
		 */
		public function recover_stranded_queue() {
			if ( ! self::is_enabled() ) {
				return;
			}

			global $wpdb;

			$interval = $this->interval_seconds();
			$max      = $this->max_attempts();
			$final    = $this->final_status();
			$now      = time();

			// Coarse filter: only entries queued at least one interval ago can be
			// eligible for their first (or next) attempt.
			$cutoff = gmdate( 'Y-m-d H:i:s', $now - $interval );

			/**
			 * Filter the maximum entries handled per recovery run.
			 *
			 * @since 7.3.1
			 */
			$batch = max( 1, (int) apply_filters( 'cwginstock_queue_recovery_batch', 50 ) );

			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					WHERE post_type = 'cwginstocknotifier'
					AND post_status = 'cwg_queued'
					AND post_modified_gmt < %s
					ORDER BY post_modified_gmt ASC
					LIMIT %d",
					$cutoff,
					$batch
				)
			);

			$ids = array_filter( array_map( 'absint', (array) $ids ) );
			if ( empty( $ids ) ) {
				return;
			}

			$api        = new CWG_Instock_API();
			$to_send    = array();
			$finalized  = 0;

			foreach ( $ids as $each_id ) {
				$attempts = (int) get_post_meta( $each_id, self::ATTEMPTS_META, true );

				// Time reference for spacing: last attempt, or the queue time.
				$last = get_post_meta( $each_id, self::LAST_META, true );
				if ( empty( $last ) ) {
					$last = get_post_field( 'post_modified_gmt', $each_id );
				}
				$last_ts = $last ? strtotime( $last . ' UTC' ) : 0;

				// Not time for the next attempt yet.
				if ( $last_ts && ( $now - $last_ts ) < $interval ) {
					continue;
				}

				// Already used all attempts, finalize and stop retrying.
				if ( $attempts >= $max ) {
					$this->finalize( $each_id, $final, $api );
					$finalized++;
					continue;
				}

				// Record this attempt.
				$attempts++;
				update_post_meta( $each_id, self::ATTEMPTS_META, $attempts );
				update_post_meta( $each_id, self::LAST_META, gmdate( 'Y-m-d H:i:s', $now ) );

				if ( ! get_post_meta( $each_id, 'cwginstock_bypass_pid', true ) ) {
					$pid = get_post_meta( $each_id, 'cwginstock_pid', true );
				} else {
					$pid = get_post_meta( $each_id, 'cwginstock_bypass_pid', true );
				}
				$product = wc_get_product( $pid );

				if ( $product instanceof WC_Product && $product->is_in_stock() ) {
					// In stock, run it through the real send pipeline.
					$to_send[] = $each_id;
				} elseif ( $attempts >= $max ) {
					// Last attempt and still cannot send, finalize now.
					$this->finalize( $each_id, $final, $api );
					$finalized++;
				}
				// Otherwise leave it queued and try again after the next interval.
			}

			if ( ! empty( $to_send ) ) {
				/**
				 * Reuse the existing in-stock send pipeline synchronously. It
				 * re-validates stock, respects the email throttle, sends, and
				 * moves each entry to mail sent / mail not sent.
				 */
				do_action( 'cwginstock_notify_process', $to_send );

				$logger = new CWG_Instock_Logger( 'success', 'Queue recovery: retried ' . count( $to_send ) . ' in-stock entr(ies) through the send pipeline' );
				$logger->record_log();
			}

			if ( $finalized > 0 ) {
				$logger = new CWG_Instock_Logger( 'info', "Queue recovery: finalized $finalized stuck entr(ies) to '$final' after $max attempt(s)" );
				$logger->record_log();
			}
		}

		/**
		 * Apply the final status to an entry that exhausted its retries and
		 * clear the recovery tracking meta.
		 *
		 * @param int               $subscriber_id Subscriber post id.
		 * @param string            $status        Final post status.
		 * @param CWG_Instock_API   $api           API helper instance.
		 */
		private function finalize( $subscriber_id, $status, $api ) {
			switch ( $status ) {
				case 'cwg_subscribed':
					$api->subscriber_subscribed( $subscriber_id );
					break;
				case 'cwg_unsubscribed':
					$api->subscriber_unsubscribed( $subscriber_id );
					break;
				case 'cwg_mailnotsent':
				default:
					$api->mail_not_sent_status( $subscriber_id );
					break;
			}

			delete_post_meta( $subscriber_id, self::ATTEMPTS_META );
			delete_post_meta( $subscriber_id, self::LAST_META );

			/**
			 * Fires after a stranded queue entry is finalized.
			 *
			 * @since 7.3.1
			 */
			do_action( 'cwginstock_queue_recovery_finalized', $subscriber_id, $status );
		}
	}

	new CWG_Instock_Queue_Recovery();
}
