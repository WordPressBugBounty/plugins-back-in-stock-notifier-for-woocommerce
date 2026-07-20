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

		// Build SQL query
		$sql = "
            UPDATE {$this->wpdb->posts}
            SET post_status = %s
            WHERE ID IN ($placeholders)
        ";

		// Prepare query with args
		$args   = array_merge( array( $new_status ), $post_ids );
		$result = $this->wpdb->query( $this->wpdb->prepare( $sql, ...$args ) );

		// Direct SQL bypasses the object cache, clear each post to avoid stale statuses.
		foreach ( $post_ids as $post_id ) {
			clean_post_cache( $post_id );
		}

		return $result;
	}
}

