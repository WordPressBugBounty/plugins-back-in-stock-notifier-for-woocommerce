<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'CWG_Instock_Auto_Delete' ) ) {

	class CWG_Instock_Auto_Delete {

		public function __construct() {
			register_activation_hook( CWGINSTOCK_FILE, array( $this, 'register_schedule' ) );
			add_action( 'cwginstock_register_settings', array( $this, 'add_settings_field' ), 200 );
			add_action( 'cwg_delete_subscribers', array( $this, 'generate_get_subscribers' ) );
			add_action( 'before_delete_post', array( $this, 'delete_post_before' ), 10, 1 );
		}

		public function add_settings_field() {
			add_settings_section( 'cwginstock_auto_delete_section', __( 'Auto Delete Settings', 'back-in-stock-notifier-for-woocommerce' ), array( $this, 'auto_delete_section_heading' ), 'cwginstocknotifier_settings' );
			add_settings_field( 'cwg_instock_enable_auto_delete', __( 'Enable Auto Delete', 'back-in-stock-notifier-for-woocommerce' ), array( $this, 'enable_auto_delete' ), 'cwginstocknotifier_settings', 'cwginstock_auto_delete_section' );
			add_settings_field( 'cwg_instock_delete_subscribers', __( 'Delete subscribers after x days (By considering the following statuses: MAIL SENT, UNSUBSCRIBED, and PURCHASED)', 'back-in-stock-notifier-for-woocommerce' ), array( $this, 'delete_subscribers_for_x_days' ), 'cwginstocknotifier_settings', 'cwginstock_auto_delete_section' );
		}

		public function auto_delete_section_heading() {
			$autodelete_heading = __( 'After a certain number of days, delete subscribers from the following statuses: MAIL SENT, UNSUBSCRIBED, and PURCHASED', 'back-in-stock-notifier-for-woocommerce' );
			echo do_shortcode( $autodelete_heading );
		}

		public function enable_auto_delete() {
			$options = get_option( 'cwginstocksettings' );
			?>
			<input type='checkbox' name='cwginstocksettings[enable_auto_delete]' <?php isset( $options['enable_auto_delete'] ) ? checked( $options['enable_auto_delete'], 1 ) : ''; ?> value="1" />
			<?php
		}

		public function delete_subscribers_for_x_days() {
			$options = get_option( 'cwginstocksettings' );
			$get_option_value = isset( $options['delete_subscribers_for_x_days'] ) && $options['delete_subscribers_for_x_days'] > 0 ? $options['delete_subscribers_for_x_days'] : 7;
			?>
			<input type='number' style='width: 400px;' name='cwginstocksettings[delete_subscribers_for_x_days]'
				value="<?php echo wp_kses_post( $get_option_value ); ?>" step="any" />
			<?php
		}

		public function register_schedule() {
			if ( ! as_next_scheduled_action( 'cwg_delete_subscribers' ) ) {
				as_schedule_recurring_action( time(), 300, 'cwg_delete_subscribers' );
			}
		}


		public function generate_get_subscribers() {
			$options = get_option( 'cwginstocksettings' );
			$check_auto_delete_enable = isset( $options['enable_auto_delete'] ) && '1' == $options['enable_auto_delete'] ? true : false;
			if ( $check_auto_delete_enable ) {
				$get_days_delete_subscriber = isset( $options['delete_subscribers_for_x_days'] ) && $options['delete_subscribers_for_x_days'] > 0 ? $options['delete_subscribers_for_x_days'] : 7; // default 7 days
				$args = array(
					'numberposts' => -1,
					'post_type' => 'cwginstocknotifier',
					'post_status' => array( 'cwg_unsubscribed', 'cwg_mailsent', 'cwg_converted' ),
					'fields' => 'ids',
					'date_query' => array(
						'before' => gmdate( 'Y-m-d', strtotime( "-$get_days_delete_subscriber days" ) ),
					),
				);
				$posts = get_posts( $args );
				if ( is_array( $posts ) && ! empty( $posts ) ) {
					foreach ( $posts as $id ) {
						$logger = new CWG_Instock_Logger( 'success', "Subscriber deleted successfully via Auto Delete Settings- #$id" );
						$logger->record_log();
						wp_delete_post( $id, true );
					}
				}
			}
		}


		//Detect the delete post before and record it in the logger
		public function delete_post_before( $post_id ) {
			$post = get_post( $post_id );
			if ( $post && 'cwginstocknotifier' === $post->post_type ) {

				$user = wp_get_current_user();
				$request_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';
				$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );

				// Save log
				$log_data = array(
					'post_id' => $post_id,
					'post_title' => $post->post_title,
					'deleted_by' => $user->user_login ?? 'Unknown',
					'user_id' => $user->ID ?? 0,
					'user_ip' => $request_ip,
					'time' => current_time( 'mysql' ),
					'trace' => print_r( $backtrace, true ),
				);

				$log_info = '[DELETION LOG] ' . print_r( $log_data, true );
				$logger = new CWG_Instock_Logger( 'success', $log_info );
				$logger->record_log();
			}
		}

	}

	new CWG_Instock_Auto_Delete();
}
