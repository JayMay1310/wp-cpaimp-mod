<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Cpaimp_DB' ) ) {

	class Cpaimp_DB {

		protected static $_instance = null;

		private $db;

		private $vendors_table = '';
		private $term_data_table = '';
		private $product_data_table = '';
		private $attributes_table = '';

		private function __construct() { }
	    private function __clone() { }

		public static function get_instance() {
	        if ( null === self::$_instance ) {
	            self::$_instance = new self();
	            self::$_instance->init();
	        }

	        return self::$_instance;
	    }
		
		public function init() {
			global $wpdb;
			$this->db = $wpdb;

			$this->vendors_table = $wpdb->prefix . 'cpaimp_vendors';
			$this->term_data_table = $wpdb->prefix . 'cpaimp_term_data';
			$this->product_data_table = $wpdb->prefix . 'cpaimp_product_data';
			$this->attributes_table = $wpdb->prefix . 'cpaimp_attributes';
		}

		public function create_tables() {
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

			$query = "CREATE TABLE IF NOT EXISTS `{$this->vendors_table}` (
					`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
					`slug` VARCHAR(64) NOT NULL UNIQUE,
					PRIMARY KEY (`id`)
				) CHARACTER SET utf8 COLLATE utf8_general_ci;";
			dbDelta( $query );

			$query = "CREATE TABLE IF NOT EXISTS `{$this->term_data_table}` (
					`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
					`term_id` INT UNSIGNED NOT NULL UNIQUE,
					`vendor_id` INT UNSIGNED NOT NULL,
					`original_id` INT UNSIGNED NOT NULL,
					`updated` TINYINT UNSIGNED NOT NULL DEFAULT 0,
					PRIMARY KEY (`id`)
				) CHARACTER SET utf8 COLLATE utf8_general_ci;";
			dbDelta( $query );

			$query = "ALTER TABLE `{$this->term_data_table}` ADD INDEX(`term_id`);";
			dbDelta( $query );
			// $query = "ALTER TABLE `{$this->term_data_table}` ADD INDEX(`vendor_id`);";
			// dbDelta( $query );
			// $query = "ALTER TABLE `{$this->term_data_table}` ADD INDEX(`original_id`);";
			// dbDelta( $query );

			$query = "CREATE TABLE IF NOT EXISTS `{$this->product_data_table}` (
					`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
					`product_id` INT UNSIGNED NOT NULL UNIQUE,
					`vendor_id` INT UNSIGNED NOT NULL,
					`original_id` INT UNSIGNED NOT NULL,
					`updated` TINYINT UNSIGNED NOT NULL DEFAULT 0,
					PRIMARY KEY (`id`)
				) CHARACTER SET utf8 COLLATE utf8_general_ci;";
			dbDelta( $query );

			$query = "ALTER TABLE `{$this->product_data_table}` ADD INDEX(`product_id`);";
			dbDelta( $query );
			// $query = "ALTER TABLE `{$this->product_data_table}` ADD INDEX(`vendor_id`);";
			// dbDelta( $query );
			// $query = "ALTER TABLE `{$this->product_data_table}` ADD INDEX(`original_id`);";
			// dbDelta( $query );

			$query = "CREATE TABLE IF NOT EXISTS `{$this->attributes_table}` (
					`term_taxonomy_id` INT UNSIGNED NOT NULL,
					`vendor_id` INT UNSIGNED NOT NULL
				) CHARACTER SET utf8 COLLATE utf8_general_ci;";
			dbDelta( $query );

			$query = "ALTER TABLE `{$this->attributes_table}` ADD INDEX(`term_taxonomy_id`);";
			dbDelta( $query );
		}

		public function add_term($term_id, $vendor_id, $original_id, $updated = 0) {
			$this->db->insert(
				$this->term_data_table,
				compact( 'term_id', 'vendor_id', 'original_id', 'updated' ),
				['%d', '%d', '%d', '%d']
			);
		}

		public function update_term($term_id, $data) {
			$this->db->update(
				$this->term_data_table,
				$data,
				['term_id' => $term_id]
			);
		}

		public function delete_term($term_id) {
			$query = "DELETE
				FROM `{$this->term_data_table}`
				WHERE `term_id` = {$term_id};";
			$this->db->query( $query );
		}

		public function delete_term_meta($term_id) {
			$query = "DELETE
				FROM `{$this->db->termmeta}`
				WHERE `term_id` = {$term_id};";
			$this->db->query( $query );
		}

		public function delete_old_terms($vendor_id) {
			$query = "SELECT `term_id`
				FROM `{$this->term_data_table}`
				WHERE `vendor_id` = {$vendor_id}
				AND `updated` = 0;";

			if ( $ids = $this->db->get_col( $query, 0 ) ) {
				foreach ( $ids as $term_id ) {
					wp_delete_term( $term_id, 'product_cat' );
					$this->delete_term_meta( $term_id );
					$this->delete_term( $term_id );
				}
			}

			$query = "UPDATE `{$this->term_data_table}`
				SET `updated` = 0
				WHERE `vendor_id` = {$vendor_id};";
			$this->db->query( $query );
		}

		public function get_term_id($vendor_id, $original_id) {
			$query = "SELECT `term_id`
				FROM `{$this->term_data_table}`
				WHERE `vendor_id` = {$vendor_id}
				AND `original_id` = {$original_id};";

			return $this->db->get_var( $query, 0, 0 );
		}

		public function delete_all_terms($vendor_id = 0) {
			$sub_query = "SELECT `term_id`
				FROM `{$this->term_data_table}`";
			$sub_query .= $vendor_id && $vendor_id > 0 ? " WHERE `vendor_id` = {$vendor_id}" : '';

			$query = "SELECT `term_id`
				FROM `{$this->db->term_taxonomy}`
				WHERE `term_id` IN ({$sub_query})
				AND `taxonomy` = 'product_cat';";

			if ( $ids = $this->db->get_col( $query, 0 ) ) {
				foreach ( $ids as $term_id ) {
					wp_delete_term( $term_id, 'product_cat' );
					$this->delete_term( $term_id );
				}
			}
		}

		public function delete_all_empty_terms($vendor_id = 0) {
			$sub_query = "SELECT `term_id`
				FROM `{$this->term_data_table}`";
			$sub_query .= $vendor_id && $vendor_id > 0 ? " WHERE `vendor_id` = {$vendor_id}" : '';

			$query = "SELECT `term_id`
				FROM `{$this->db->term_taxonomy}`
				WHERE `term_id` IN ({$sub_query})
				AND `taxonomy` = 'product_cat'
				AND `count` <= 0;";

			if ( $ids = $this->db->get_col( $query, 0 ) ) {
				foreach ( $ids as $term_id ) {
					wp_delete_term( $term_id, 'product_cat' );
					$this->delete_term( $term_id );
				}
			}
		}



		public function add_vendor($slug) {
			$this->db->insert(
				$this->vendors_table,
				['slug' => $slug],
				['%s']
			);
			return $this->db->insert_id;
		}

		public function get_vendor_id_by_slug($slug) {
			$query = "SELECT `id`
				FROM `{$this->vendors_table}`
				WHERE `slug` = '{$slug}';";

			return $this->db->get_var( $query, 0, 0 );
		}



		public function add_product($product_id, $vendor_id, $original_id, $updated = 0) {
			$this->db->insert(
				$this->product_data_table,
				compact( 'product_id', 'vendor_id', 'original_id', 'updated' ),
				['%d', '%d', '%d', '%d']
			);
		}

		public function update_product($product_id, $data) {
			$this->db->update(
				$this->product_data_table,
				$data,
				['product_id' => $product_id]
			);
		}

		public function mark_all_products_as_updated($vendor_id) {
			$this->db->update(
				$this->product_data_table,
				['updated' => 1],
				['vendor_id' => $vendor_id],
				['%d'], ['%d']
			);
		}

		public function delete_product($product_id) {
			$query = "DELETE
				FROM `{$this->product_data_table}`
				WHERE `product_id` = {$product_id};";
			$this->db->query( $query );
		}

		public function delete_product_meta($product_id) {
			$query = "DELETE
				FROM `{$this->db->postmeta}`
				WHERE `post_id` = {$product_id};";
			$this->db->query( $query );
		}

		public function delete_product_term_relationships($product_id) {
			$query = "SELECT `term_taxonomy_id`
				FROM `{$this->db->term_relationships}`
				WHERE `object_id` = {$product_id};";
			$term_taxonomy_ids = $this->db->get_col( $query, 0 );

			if ( $term_taxonomy_ids ) {
				// Updating counters
				$in = implode( ',', $term_taxonomy_ids );
				$query = "UPDATE `{$this->db->term_taxonomy}`
					SET `count` = `count` - 1
					WHERE `term_taxonomy_id` IN ({$in});";
				$this->db->query( $query );

				// Dealing with product attributes
				/* $query = "SELECT *
					FROM `{$this->db->term_taxonomy}`
					WHERE `term_taxonomy_id` IN ({$in});";
				$results = $this->db->get_results( $query );
				if ( $results ) {
					foreach ( $results as $row ) {
						// Term has no posts and taxonomy can be WooCommerce attribute
						if ( $row->count <= 0 && 'pa_' === substr( $row->taxonomy, 0, 3 ) ) {
							$attr_name = substr( $row->taxonomy, 3 );
							$query = "SELECT *
								FROM `{$this->db->prefix}woocommerce_attribute_taxonomies`
								WHERE `attribute_name` = '{$attr_name}';";

							$attribute = $this->db->get_row( $query );
							if ( ! is_null( $attribute ) ) {
								// Attribute exists
								$query = "DELETE
									FROM `{$this->db->term_taxonomy}`
									WHERE `term_taxonomy_id` = {$row->term_taxonomy_id};";
								$this->db->query( $query );

								$query = "DELETE
									FROM `{$this->db->terms}`
									WHERE `term_id` = {$row->term_id};";
								$this->db->query( $query );

								$query = "SELECT COUNT(`taxonomy`) AS `cc`
									FROM `{$this->db->term_taxonomy}`
									WHERE `taxonomy` = '{$row->taxonomy}';";
								$attrs_left = $this->db->get_var( $query, 0, 0 );

								if ( 0 == $attrs_left ) {
									$query = "DELETE
										FROM `{$this->db->prefix}woocommerce_attribute_taxonomies`
										WHERE `attribute_id` = $attribute->attribute_id;";
									$this->db->query( $query );
								}
							}
						}
					}
				} */

				$query = "DELETE
					FROM `{$this->db->term_relationships}`
					WHERE `object_id` = {$product_id};";
				$this->db->query( $query );
			}
		}

		public function get_product_id($vendor_id, $original_id) {
			$query = "SELECT `product_id`
				FROM `{$this->product_data_table}`
				WHERE `vendor_id` = {$vendor_id}
				AND `original_id` = {$original_id};";

			return $this->db->get_var( $query, 0, 0 );
		}

		public function delete_old_products($vendor_id) {
			$query = "SELECT `product_id`
				FROM `{$this->product_data_table}`
				WHERE `vendor_id` = {$vendor_id}
				AND `updated` = 0;";

			if ( $ids = $this->db->get_col( $query, 0 ) ) {
				foreach ( $ids as $product_id ) {
					$this->delete_product_attachments( $product_id );
					wp_delete_post( $product_id, true );
					$this->delete_product( $product_id );
				}
			}

			$query = "UPDATE `{$this->product_data_table}`
				SET `updated` = 0
				WHERE `vendor_id` = {$vendor_id};";
			$this->db->query( $query );
		}

		private function delete_product_attachments($product_id) {
			$children = get_children( [
				'post_parent' => $product_id,
				'post_status' => 'inherit',
				'post_type' => 'attachment',
				'post_mime_type' => 'image',
				'numberposts' => -1
			] );
			foreach ( $children as $attachment ) {
				wp_delete_attachment( $attachment->ID, true );
			}
		}

		public function delete_all_products($vendor_id = 0) {
			$query = "SELECT `product_id`
				FROM `{$this->product_data_table}`";
			$query .= ( $vendor_id && $vendor_id > 0 ? " WHERE `vendor_id` = {$vendor_id}" : '' ) . ';';

			if ( $ids = $this->db->get_col( $query, 0 ) ) {
				foreach ( $ids as $product_id ) {
					$this->delete_product_attachments( $product_id );
					wp_delete_post( $product_id, true );
					$this->delete_product( $product_id );
				}
			}
		}



		public function add_attribute($data) {
			$this->db->insert(
				$this->attributes_table,
				$data,
				['%d', '%d']
			);
		}

		public function delete_empty_attributes($vendor_id) {
			$query = "SELECT `term_taxonomy_id`
				FROM `{$this->db->term_taxonomy}`
				WHERE `term_taxonomy_id` IN (
					SELECT `term_taxonomy_id`
					FROM `{$this->attributes_table}`
					WHERE `vendor_id` = $vendor_id
				)
				AND `count` <= 0;";

			if ( $term_taxonomy_ids = $this->db->get_col( $query, 0 ) ) {
				$in_ids_str = implode( ',', $term_taxonomy_ids );
				$query = "SELECT `term_id`
					FROM `{$this->db->term_taxonomy}`
					WHERE `term_taxonomy_id` IN ({$in_ids_str});";

				$term_ids_to_delete = $this->db->get_col( $query, 0 );
				$term_ids_str = implode( ',', $term_ids_to_delete );
				$query = "DELETE
					FROM `{$this->db->terms}`
					WHERE `term_id` IN ({$term_ids_str});";
				$this->db->query( $query );

				$query = "DELETE
					FROM `{$this->db->term_taxonomy}`
					WHERE `term_taxonomy_id` IN ({$in_ids_str});";
				$this->db->query( $query );

				$query = "DELETE
					FROM `{$this->attributes_table}`
					WHERE `term_taxonomy_id` IN ({$in_ids_str})
					AND `vendor_id` = $vendor_id;";
				$this->db->query( $query );
			}
		}



		public function truncate_tables() {
			$query = "TRUNCATE TABLE `{$this->term_data_table}`;";
			$this->db->query( $query );
			$query = "TRUNCATE TABLE `{$this->product_data_table}`;";
			$this->db->query( $query );
			$query = "TRUNCATE TABLE `{$this->attributes_table}`;";
			$this->db->query( $query );
		}


		public function clear_woo() {
			$q = new WP_Query( [
				'post_type' => 'product',
				'posts_per_page' => -1,
				'fields' => 'ids'
			] );
			$ids = $q->posts;
			foreach ( $ids as $product_id ) {
				$this->delete_product_attachments( $product_id );
				wp_delete_post( $product_id, true );
				$this->delete_product( $product_id );
			}

			$query = "SELECT `attribute_name`
				FROM `{$this->db->prefix}woocommerce_attribute_taxonomies`;";
			$attribute_names = $this->db->get_col( $query, 0 );
			$attribute_names = array_map( function($attribute_name) {
				return "pa_{$attribute_name}";
			}, $attribute_names );

			$in_str = "'" . implode( "','", $attribute_names ) . "','product_cat'";
			$query = "SELECT `term_taxonomy_id`, `term_id`
				FROM `{$this->db->term_taxonomy}`
				WHERE `taxonomy` IN ({$in_str});";
			$term_taxonomy_ids = $this->db->get_col( $query, 0 );
			$term_ids = $this->db->get_col( $query, 1 );

			$in_str = implode( ',', $term_ids );
			$query = "DELETE
				FROM `{$this->db->terms}`
				WHERE `term_id` IN ({$in_str});";
			$this->db->query( $query );

			$query = "DELETE
				FROM `{$this->db->termmeta}`
				WHERE `term_id` IN ({$in_str});";
			$this->db->query( $query );

			$in_str = implode( ',', $term_taxonomy_ids );
			$query = "DELETE
				FROM `{$this->db->term_taxonomy}`
				WHERE `term_taxonomy_id` IN ({$in_str});";
			$this->db->query( $query );

			$query = "DELETE
				FROM `{$this->db->term_relationships}`
				WHERE `term_taxonomy_id` IN ({$in_str});";
			$this->db->query( $query );

			$query = "TRUNCATE TABLE `{$this->db->prefix}woocommerce_attribute_taxonomies`;";
			$this->db->query( $query );

			delete_option( '_transient_wc_attribute_taxonomies' );

			$this->truncate_tables();
		}
	}
}