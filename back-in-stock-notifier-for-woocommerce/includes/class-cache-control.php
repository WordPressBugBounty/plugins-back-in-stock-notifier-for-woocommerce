<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'CWG_Instock_Cache_Control' ) ) {

	class CWG_Instock_Cache_Control {

		public function __construct() {
			//implemented it for w3total cache to auto purge upon stock status change
			add_action( 'cwginstock_before_trigger_status', array( $this, 'auto_purge_cache_for_product' ), 10, 3 );
		}

		public function auto_purge_cache_for_product( $id, $stock_status, $obj ) {
			$product_obj = wc_get_product($id); //sometimes obj in parameter may return empty
			if ($product_obj) {
				$get_type = 'variation' == $obj->get_type() ? true : false;
				if ($get_type) {
					$get_parent_id = $obj->get_parent_id();
					$id = $get_parent_id;
				}
			}
			
			if ( function_exists( 'w3tc_pgcache_flush_post' ) ) {
				// Purge cache for this product page
				w3tc_pgcache_flush_post( $id );
			} elseif ( function_exists( 'w3tc_flush_post' ) ) {
				// Fallback for older W3TC versions
				w3tc_flush_post( $id );
			} else {
				// Purge entire cache if post-specific flush not available
				if ( function_exists( 'w3tc_flush_all' ) ) {
					w3tc_flush_all();
				}
			}
		 
		}


	}

	new CWG_Instock_Cache_Control();
}
