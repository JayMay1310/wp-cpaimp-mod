<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Cpaimp_Product' ) ) {

	class Cpaimp_Product {
		private static $config = [];
		private $id = 0;
		private $exists = false;
		private $params = [];

		private $src_id = 0;
		
		public function __construct($args = [], $additional_data = []) {
			self::$config = Cpaimp_Importer::get_instance();

			$this->params = $args;
			$this->src_id = $args['id'];
			$this->additional_data = $additional_data;
			$this->id = ImpDB()->get_product_id( (int)Cpaimp_Importer::$vendor_id, $this->src_id );
			$this->exists = (bool)$this->id;
		}

		public function save() {
			if ( $this->exists ) {
				$this->update();
			}
			else {
				$this->create();
			}

			// TODO: WTF?
			// if ( IM_Config::init()->get( 'ps_offer' ) ) {
			// 	global $wpdb;
			// 	$wpdb->query(
			// 		"UPDATE `{$wpdb->posts}`
			// 		SET offer = '" . ( isset( $_GET['offer'] ) ? $_GET['offer'] : '' ) . "'
			// 		WHERE `ID` = {$this->id};"
			// 	);
			// }

			if ( $this->id ) {
				if ( ! empty( $this->params['image'] ) ) {
					$this->download_thumbnail();
				}
				$this->set_terms();
				$this->set_params();
			}
		}

		public function create() {
		  	$post_id = wp_insert_post( [
		  		'post_title'		=> $this->params['title'],
				'post_content'		=> $this->params['description'],
				'post_type'			=> 'product',
				'post_status'		=> 'publish',
				// 'post_mime_type'	=> $this->params['id'],
				'comment_status'	=> 'closed',
				'post_name'			=> transliteration( $this->params['title'] ),
	    		// 'offer' => ( isset( $_GET['offer'] ) ) ? $_GET['offer'] : ''
		  	] );

		  	if ( $post_id && ! is_wp_error( $post_id ) ) {
		  		$this->id = $post_id;
		  		ImpDB()->add_product( $post_id, (int)Cpaimp_Importer::$vendor_id, $this->src_id, 1 );

		  		wp_set_object_terms( $post_id, 'external', 'product_type' );

		  		// set Cpaimp offer_id
				// add_post_meta( $post_id, 'offer_id', $this->params['id'] );

				// set Cpaimpn url
				if ( $this->params['url'] ) {
					add_post_meta( $post_id, 'url', $this->params['url'], true );
					add_post_meta( $post_id, '_product_url', $this->params['url'], true );
				}

				//set Cpaimp price
				if ( $this->params['price'] ) {
					add_post_meta( $post_id, '_regular_price', $this->params['price'], true );
					add_post_meta( $post_id, '_price', $this->params['price'], true );
					add_post_meta( $post_id, '_visibility', 'visible', true );
					add_post_meta( $post_id, '_stock_status', 'instock', true );
				}

				add_post_meta( $post_id, '_wp_page_template', 'sidebar-page.php', true );

				// TODO: WTF?
				// global $wpdb;
				// $query = "SELECT COUNT(*) as cc
		  //   		FROM `$wpdb->term_relationships`
		  //   		WHERE `object_id` = {$post_id}
		  //   		AND term_taxonomy_id = 5;";
			 //    $cc = $wpdb->get_var( $query, 0, 0 );

			 //    if ( 0 == $cc ) {
			 //    	$wpdb->query(
			 //    		"INSERT INTO $wpdb->term_relationships
			 //    		SET object_id = {$post_id}, term_taxonomy_id = 5, term_order = 0;"
			 //    	);
			 //    }
		  	}
		  	else {
		  		ImpLogger()->log( "[POST ERROR] Src ID: {$this->src_id}; Message: " . $post_id->get_error_message() );
		  	}
		}

		public function update() {
			if ( $post = get_post( $this->id ) ) {
				if ( 'trash' === $post->post_status ) {
			  		return;
			  	}
				  
  				//set Cpaimp url
  				// @see posts.php redirect_to_url()
				if ( $this->params['url'] ) {
					update_post_meta( $post->ID, 'url', $this->params['url'] );
				}			

				//set Cpaimp price
				if ( $this->params['price'] ) {
					update_post_meta( $post->ID, '_regular_price', $this->params['price'] );
					update_post_meta( $post->ID, '_price', $this->params['price'] );
					update_post_meta( $post->ID, '_visibility', 'visible' );
					update_post_meta( $post->ID, '_stock_status', 'instock' );
				}

			}
		}

		private function download_thumbnail() {
			$fileContents = false; // Нужна для выхода из метода без дерганья, внешних сайтов. (Принудительно)
			if ( ! $fileContents ) {
				//ImpLogger()->log( 'При попытке загрузить файл ' . $this->params['image'] . ' возникла ошибка: ' .
				//	"Содержимое файла не получено. Файл был присоединён старым способом" );

				update_post_meta( $this->id, 'image', $this->params['image'] );
				return;
			}

			$localFilepath = dirname( __FILE__ ) . '/downloads/' . basename( $this->params['image'] );
			
			if ( $fileContents && ( $f = fopen( $localFilepath, 'w' ) ) && fwrite( $f, $fileContents ) ) {
				fclose( $f );

				// Удаление не пользовательских аттачментов
				$children = get_children( [
					'post_parent' => $this->id,
					'post_status' => 'inherit',
					'post_type' => 'attachment',
					'post_mime_type' => 'image',
					'numberposts' => -1
				] );
				foreach ( $children as $attachment ) {
					if ( get_post_meta( $attachment->ID, 'is_image_from_cpaimp', true ) ) {
						wp_delete_attachment( $attachment->ID, true );
					}
				}


				$attachment = new Cpaimp_Attachment( $localFilepath, $this->id );
				if ( $attachment->insert() ) {
					$attachment->set_as_thumbnail();
				}
				else {
					ImpLogger()->log( 'Ошибка при установки миниатюры продукта' );
					update_post_meta( $this->id, 'image', $this->params['image'] );
				}

				if ( is_file( $localFilepath ) ) {
					unlink( $localFilepath );
				}
			}
			else {
				ImpLogger()->log( '[FILE: ' . $this->params['image'] . '] Unable to write file to filesystem or file content is empty' );
				ImpLogger()->log( '$localFilepath: ' . $localFilepath );
			}
		}

		private function set_terms() {
			$term_id = ImpDB()->get_term_id( (int)Cpaimp_Importer::$vendor_id, (int)$this->params['category_id'] );
			if ( $term_id ) {
				wp_set_object_terms( $this->id, [(int)$term_id], 'product_cat', true );
			}
		}

		private function set_params() {
			global $wpdb;
			$attributes = [];
  
			if ( '' !== $this->additional_data['vendor'] && '<![CDATA[]]>' !== $this->additional_data['vendor'] ) {
				$this->additional_data['Бренд'] = $this->additional_data['vendor'];
			}
			unset( $this->additional_data['vendor'] );
			unset( $this->additional_data['params_list'] );


			// Used in export module
  			$this->additional_data['IDorig'] = $this->src_id;  
			if ( Cpaimp_Importer::$vendor ) {
				$this->additional_data['Offer'] = Cpaimp_Importer::$vendor;
			}

			
			$wpdb->query(
				"DELETE FROM $wpdb->postmeta
				WHERE post_id = {$this->id}
				AND meta_key = '_product_attributes';"
			);

			$position = 0;
			foreach ( $this->additional_data as $name => $value ) {
				$value = str_replace( "'", '&apos;', $value );

				if ( in_array( $name, ['Размер', 'Цвет', 'Тип', 'Компания', 'Вид', 'Бренд', 'vendor', 'Материал', 'Сезон'] ) ) {  
					$Tax_ids['post_id'] = $this->id;
		  
					// добавление атрибута
					$attribute = array(
						'attribute_name' => wc_sanitize_taxonomy_name( $this->get_seo_url( $name ) ),
						'attribute_label' => $name,
						'attribute_type' => 'select',
						'attribute_orderby' => 'name',
						'attribute_public' => '0'
					);

					$rows = $wpdb->get_results("
						SELECT COUNT(*) as cc, attribute_id
						FROM {$wpdb->prefix}woocommerce_attribute_taxonomies
						WHERE attribute_name = '" . $attribute['attribute_name'] . "';"
					);

					if ( $rows[0]->cc == 0 ) {
						// Adding new attribute
						$wpdb->insert( $wpdb->prefix . 'woocommerce_attribute_taxonomies', $attribute );
						$Tax_ids['attribute_id']   = $wpdb->insert_id;
						$attribute['attribute_id'] = $wpdb->insert_id;

						// Updating precached attributes
						// Maybe we can use get_option???
						$option_value = $wpdb->get_var(
							"SELECT `option_value`
							FROM $wpdb->options
							WHERE option_name = '_transient_wc_attribute_taxonomies';",
						0, 0 );
						$taxonomies = unserialize( $option_value );
						$taxonomies[] = (object)$attribute;
						$taxonomies_str = serialize( $taxonomies );
						$wpdb->query(
							"UPDATE $wpdb->options
							SET option_value = '{$taxonomies_str}'
							WHERE option_name = '_transient_wc_attribute_taxonomies';"
						);
					}
					else {
						$Tax_ids['attribute_id'] = $rows[0]->attribute_id;
					}

					// добавление свойства атрибуту
					$attribute2 = [
						'name' => $value,
						'slug' => wc_sanitize_taxonomy_name( $this->get_seo_url( $value ) ),
						'term_group' => '0'
					];
					$rows = $wpdb->get_results("
						SELECT COUNT(*) as cc, term_id
						FROM $wpdb->terms
						WHERE name = '{$value}';"
					);
					if ( $rows[0]->cc == 0 ) {
						$wpdb->insert( $wpdb->terms, $attribute2 );
						$Tax_ids['term_id'] = $wpdb->insert_id;
					}
					else {
						$Tax_ids['term_id'] = $rows[0]->term_id;
					}


					$attribute3 = [
						'term_id' => $Tax_ids['term_id'],
						'taxonomy' => 'pa_' . $attribute['attribute_name']
					];
					$rows = $wpdb->get_results(
						"SELECT COUNT(*) as cc, term_taxonomy_id
						FROM $wpdb->term_taxonomy
						WHERE term_id = " . $attribute3['term_id'] . ";"
					);
					if ( $rows[0]->cc == 0 ) {
						$wpdb->insert( $wpdb->term_taxonomy, $attribute3 ); 
						$Tax_ids['term_taxonomy_id'] = $wpdb->insert_id;

						ImpDB()->add_attribute([
							'term_taxonomy_id' => $wpdb->insert_id,
							'vendor_id' => (int)Cpaimp_Importer::$vendor_id
						]);
					}
					else {
						$Tax_ids['term_taxonomy_id'] = $rows[0]->term_taxonomy_id; 
					}


					$attribute4 = [
						'term_id' => $Tax_ids['term_id'],
						'meta_key' => 'order_pa_' . $attribute['attribute_name'],
						'meta_value' => '0'
					];
					$wpdb->insert( $wpdb->termmeta, $attribute4 ); 


					$rows = $wpdb->get_results(
						"SELECT COUNT(*) as cc
						FROM $wpdb->term_relationships
						WHERE object_id = {$this->id}
						AND term_taxonomy_id = " . $Tax_ids['term_taxonomy_id'] . ";"
					);

					if ( $rows[0]->cc == 0 ) {
						$wpdb->insert(
							$wpdb->term_relationships,
							[
								'object_id' => $this->id,
								'term_taxonomy_id' => $Tax_ids['term_taxonomy_id'],
								'term_order' => 0
							],
							['%d','%d','%d']
						);

						// $wpdb->query(
						// 	"INSERT INTO $wpdb->term_relationships
						// 	SET object_id = {$this->id}, term_taxonomy_id = " . $Tax_ids['term_taxonomy_id'] . ", term_order = 0;"
						// );
					}
			

					// Make sure the 'name' is same as you have the attribute
					$attributes['pa_' . $attribute['attribute_name']] = array(					
						'name'         => 'pa_' . $attribute['attribute_name'],
						'value'        => '',
						'position'     => $position++,
						'is_visible'   => 1,
						'is_variation' => 0,
						'is_taxonomy'  => 1
					);

					$query = "UPDATE `{$wpdb->term_taxonomy}`
						SET `count` = `count` + 1
						WHERE `term_taxonomy_id` = " . $Tax_ids['term_taxonomy_id'] . ";";
					$wpdb->query( $query );
				}
				else {
					$attributes[htmlspecialchars( stripslashes( $name ) )] = array(
						'name'         => htmlspecialchars( stripslashes( $name ) ),
						'value'        => $value,
						'position'     => 1,
						'is_visible'   => ( 'IDorig' !== $name && 'Offer' !== $name ? 1 : 0 ),
						'is_variation' => 1,
						'is_taxonomy'  => 0
					);
				}
			}

			add_post_meta( $this->id, '_product_attributes', serialize( $attributes ) );
		}

		private function get_seo_url($url) {
			$tr = array( "А" => "A", "Б" => "B", "В" => "V", "Г" => "G", "Д" => "D", "Е" => "E", "Ж" => "J", "З" => "Z", "И" => "I", "Й" => "Y", "К" => "K", "Л" => "L", 
				"М" => "M", "Н" => "N", "О" => "O", "П" => "P", "Р" => "R", "С" => "S", "Т" => "T", "У" => "U", "Ф" => "F", "Х" => "H", "Ц" => "TS", "Ч" => "CH", 
				"Ш" => "SH", "Щ" => "SCH", "Ъ" => "", "Ы" => "YI", "Ь" => "", "Э" => "E", "Ю" => "YU", "Я" => "YA", "а" => "a", "б" => "b", "в" => "v", "г" => "g", 
				"д" => "d", "е" => "e", "ж" => "j", "з" => "z", "и" => "i", "й" => "y", "к" => "k", "л" => "l", "м" => "m", "н" => "n", "о" => "o", "п" => "p", 
				"р" => "r", "с" => "s", "т" => "t", "у" => "u", "ф" => "f", "х" => "h", "ц" => "ts", "ч" => "ch", "ш" => "sh", "щ" => "sch", "ъ" => "y", "ы" => "yi", 
				"ь" => "", "э" => "e", "ю" => "yu", "я" => "ya", "." => "-", " " => "-", "?" => "-", "/" => "-", "\\" => "-", "*" => "-", ":" => "-", "*" => "-", 
				">" => "-", "|" => "-", "'" => "", "&quot;" => "", "(" => "", ")" => "", "ё" => "e"
			); 
			$url = strtr( $url, $tr );
	 		return $url;
		}
	}
}