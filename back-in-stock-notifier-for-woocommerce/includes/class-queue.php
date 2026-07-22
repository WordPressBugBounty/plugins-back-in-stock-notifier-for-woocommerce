<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class CWG_BIS_Queue {

	protected $wpdb;

	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function update_post_statuses( array $post_ids, $new_status ) {
		$post_ids = array_filter( array_map( 'absint', $post_ids ) );
		if ( empty( $post_ids ) ) {
			return false;
		}

		// Prepare placeholders for SQL
		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

		// Stamp the modified time so the queue-recovery job can tell how long an
		// entry has been sitting in the queue (used to detect stranded sends).
		$now     = current_time( 'mysql' );
		$now_gmt = current_time( 'mysql', true );

		// Build SQL query
		$sql = "
            UPDATE {$this->wpdb->posts}
            SET post_status = %s, post_modified = %s, post_modified_gmt = %s
            WHERE ID IN ($placeholders)
        ";

		// Prepare query with args
		$args   = array_merge( array( $new_status, $now, $now_gmt ), $post_ids );
		$result = $this->wpdb->query( $this->wpdb->prepare( $sql, ...$args ) );

		// A fresh queue cycle resets the recovery attempt tracking so retries
		// always start from zero for this batch.
		if ( 'cwg_queued' === $new_status ) {
			$meta_placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
			$this->wpdb->query(
				$this->wpdb->prepare(
					"DELETE FROM {$this->wpdb->postmeta}
					WHERE meta_key IN ('cwginstock_recovery_attempts','cwginstock_recovery_last_gmt')
					AND post_id IN ($meta_placeholders)",
					...$post_ids
				)
			);
		}

		// Direct SQL bypasses the object cache, clear each post to avoid stale statuses.
		foreach ( $post_ids as $post_id ) {
			clean_post_cache( $post_id );
		}

		return $result;
	}
}

