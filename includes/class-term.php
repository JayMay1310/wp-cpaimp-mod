<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Cpaimp_Term' ) ) {

	class Cpaimp_Term {

		public $id = 0;
		public $parent_id = 0;
		public $children = [];
		private $exists = false;

		public $src_id = 0;
		private $src_parent_id = 0;
		private $title = '';
		
		public function __construct($args = [], $parent_id = 0) {
			$this->src_id = $args['id'];
			$this->src_parent_id = isset( $args['parent_id'] ) ? $args['parent_id'] : 0;
			$this->title = $args['title'];

			$this->id = ImpDB()->get_term_id( (int)Cpaimp_Importer::$vendor_id, $this->src_id );
			$this->exists = (bool)$this->id;

			$this->parent_id = $parent_id;
		}

		public function save() {
			$result = wp_insert_term( $this->title, 'product_cat', [
				'parent' => $this->parent_id,
				'slug' => transliteration( $this->title )
			] );
			if ( ! is_wp_error( $result ) ) {
				$this->id = $result['term_id'];
				ImpDB()->add_term( $this->id, (int)Cpaimp_Importer::$vendor_id, $this->src_id, 1 );
			}
			else {
				ImpLogger()->log( "[ERROR] Title: {$this->title}; Src ID: {$this->src_id}; Message: " . $result->get_error_message() );
			}
		}

		public function update() {
			$wp_term = get_term( $this->id, 'product_cat' );
			if ( $wp_term && ! is_wp_error( $wp_term ) ) {
				$data = [];

				if ( $wp_term->parent != $this->parent_id ) {
					$data['parent'] = $this->parent_id;
				}
				$slug = transliteration( $this->title );
				if ( $wp_term->slug !== $slug ) {
					$data['slug'] = $slug;
				}

				$term_data = null;
				if ( $data ) {
					$term_data = wp_update_term( $this->id, 'product_cat', $data );
				}				

				if ( is_wp_error( $term_data ) ) {
					ImpLogger()->log( "[ERROR] Title: {$this->title}; Src ID: {$this->src_id}; Message: " . $term_data->get_error_message() );
				}

				// Even if wp_update_term returns WP_Error, term exists in XML
				// and we do not have to remove it in future, so set "updated" flag.
				ImpDB()->update_term( $this->id, ['updated' => 1] );
			}

			// $wp_tern can br null so check this again
			if ( is_wp_error( $wp_term ) ) {
				ImpLogger()->log( "[ERROR] Title: {$this->title}; Src ID: {$this->src_id}; Message: " . $wp_term->get_error_message() );
			}

				// if (IM_Config::init()->get('ps_offer')) {$wpdb->query(" UPDATE $wpdb->term_taxonomy SET offer='".((isset($_GET['offer'])) ? $_GET['offer'] : '')."' WHERE 	term_id = '".$dbItem->term_id."' ");}   
		}

		public static $cats_list = [];

		public static function build_terms_tree($root_categories = [], &$sibling_categories = []) {
			foreach ( $root_categories as $category ) {
				foreach ( $sibling_categories as $i => $cat_config ) {
					if ( $cat_config['parent_id'] == $category->src_id && ! in_array( $cat_config['id'], self::$cats_list ) ) {
						self::$cats_list[] = $cat_config['id'];
						$child_cat = new Cpaimp_Term( $cat_config, $category->id );
						$category->children[] = $child_cat;
						unset( $sibling_categories[$i] );
					}
				}

				if ( $category->children ) {
					self::build_terms_tree( $category->children, $sibling_categories );
				}
			}

			return $root_categories;
		}

		public static function save_terms_tree($categories = [], $parent_id = 0) {
			foreach ( $categories as $category ) {
				$category->parent_id = $parent_id;
				$category->save();
				if ( $category->children ) {
					self::save_terms_tree( $category->children, $category->id );
				}
			}
		}
	}
}