<?php
ini_set('memory_limit', '-1');
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Cpaimp_XMLFile' ) ) {	

	class Cpaimp_XMLFile {
		
		private static $config = [];

		public $filepath = '';		
		public $is_coupon_xml = false;
		public $content = '';
		public $xml_offer = '';

		// Used only in "load_file_part" method
		private $buffer = '';
		private $tmp_filepath;
		private $fh;

		

		private $advcampaign = [];
		private $type_str = [];
		private $species_str = [];

		public function __construct($filepath = '') {
			if ( $filepath && file_exists( $filepath ) ) {
				self::$config = Cpaimp_Importer::get_instance();

				$clear_file = new Cpaimp_Clear_Filter_Execute($filepath);

				$this->filepath = $filepath;
				$this->content = file_get_contents( $filepath );
				$this->is_coupon_xml = ( strpos( $this->content, 'advcampaign_categories' ) > 0 );
				$this->xml_offer = $this->is_coupon_xml ? 'coupon' : 'offer';
				$this->fh = fopen( $filepath, 'r' );
			}
			else {
				throw new Exception( 'Error during XML file creation!', 10 );
			}
		}

		public function set_additional_content($content = '') {
			if ( ! empty( $content ) ) {
				$this->content = str_replace( '</categoryId>', "{$content}</categoryId>", $this->content );
				$this->content = str_replace( '</category_id>', "{$content}</category_id>", $this->content );

				preg_match_all( "{ \<category\s (.*?) id=\" (.*?) \" }xs", $this->content, $founds );
				for ( $i = 0; $i < sizeof( $founds[0] ); $i++ ) {
					$search  = '<category ' . $founds[1][$i] . 'id="' . $founds[2][$i] . '"';
					$replace = '<category ' . $founds[1][$i] . 'id="' . $founds[2][$i] . $content . '"';
					$this->content = str_replace( $search, $replace, $this->content );
				}

				preg_match_all( "{ \<category\s (.*?) parentId=\" (.*?) \" }xs", $this->content, $founds );
				for ( $i = 0; $i < sizeof( $founds[0] ); $i++ ) {
					$search  = '<category ' . $founds[1][$i] . 'parentId="' . $founds[2][$i] . '"';
					$replace = '<category ' . $founds[1][$i] . 'parentId="' . $founds[2][$i] . $content . '"';
					$this->content = str_replace( $search, $replace, $this->content );
				}

				$this->tmp_filepath = $this->filepath . '.tmp';				
				file_put_contents( $this->tmp_filepath, $this->content );
				fclose( $this->fh );
				$this->fh = fopen( $this->tmp_filepath, 'r' );
			}
		}

		public function process_coupon_xml() {
			if ( $this->is_coupon_xml ) {

				$content = $this->load_file_part( '</advcampaigns>' );
				preg_match_all( "{ \<advcampaign\sid=\" (.*?) \" (.*?) \<name\> (.*?) \<\/name\> }xs", $content, $founds );
				for ( $i = 0; $i < sizeof( $founds[0] ); $i++ ) {
					$this->advcampaign[$founds[1][$i]] = $founds[3][$i];
				}
				
				$content = $this->load_file_part( '</types>' );
				preg_match_all( "{ \<type\sid=\" (.*?) \" (.*?) \> (.*?) \<\/type\> }xs", $content, $founds );
				for ( $i = 0; $i < sizeof( $founds[0] ); $i++ ) {
					$this->type_str[$founds[1][$i]] = $founds[3][$i];
				}
			}
		}

		public function process_species() {
			if ( $this->is_coupon_xml ) {
				$content = $this->load_file_part( '</species>' );
				preg_match_all( "{ \<specie\sid=\" (.*?) \" (.*?) \> (.*?) \<\/specie\> }xs", $content, $founds );
				for ( $i = 0; $i < sizeof( $founds[0] ); $i++ ) {
					$this->species_str[$founds[1][$i]] = $founds[3][$i];
				}				
			}
		}

		public function parse_categories() {
			$xml_categories = 'categories';
			$content = $this->load_file_part( "</{$xml_categories}>" );

			$ps = mb_strpos( $content, "<{$xml_categories}>", 0, 'utf-8' );
			$pe = mb_strpos( $content, "</{$xml_categories}>", 0, 'utf-8' );
			if ( $ps && $pe ) {
				$contentCats = mb_substr( $content, $ps + mb_strlen( "<{$xml_categories}>", 'utf-8' ), $pe - $ps - mb_strlen( "<{$xml_categories}>", 'utf-8' ), 'utf-8' );
				$content = mb_substr( $content, $pe + mb_strlen( "</{$xml_categories}>", 'utf-8' ), mb_strlen( $content, 'utf-8' ), 'utf-8' );
				
				$root_categories = $sibling_categories = [];

				while (true) {
					$ps = mb_strpos( $contentCats, '<category', 0, 'utf-8' );
					$pe = mb_strpos( $contentCats, '</category>', 0, 'utf-8' );
					if ( false !== $ps && false !== $pe ) {
						$category = mb_substr( $contentCats, $ps + mb_strlen( '<category', 'utf-8' ), $pe - $ps - mb_strlen( '<category', 'utf-8' ), 'utf-8' );
						$contentCats = mb_substr( $contentCats, $pe + mb_strlen( '</category>', 'utf-8' ), mb_strlen( $contentCats, 'utf-8' ), 'utf-8' );

						$matches = [];
						preg_match( '/ id="(\d+)"/', $category, $matches );
						$id = (int)$matches[1];

						$ps = mb_strpos( $category, '>', 0, 'utf-8' );
						$title = mb_substr( $category, $ps + 1, mb_strlen( $category, 'utf-8' ), 'utf-8' );
						$title = str_replace( ['<![CDATA[', ']]>'], '', $title );

						$cat = [
							'id' => $id,
							'title' => $title
						];
						if ( preg_match( '/parentId="(\d+)"/', $category, $matches ) or preg_match( '/parent_id="(\d+)"/', $category, $matches )) {
							$cat['parent_id'] = $matches[1];
							$sibling_categories[] = $cat;
						}
						else {
							if ( ! in_array( $id, Cpaimp_Term::$cats_list ) ) {
								$root_categories[] = new Cpaimp_Term( $cat );
								Cpaimp_Term::$cats_list[] = $id;
							}
						}						
					}
					else {
						break;
					}
				}

				$tree = Cpaimp_Term::build_terms_tree( $root_categories, $sibling_categories );
				Cpaimp_Term::save_terms_tree( $tree );
			}
		}

		public function parse_goods() {
			$import_goods = [];
			while ( true ) {
				$content = $this->load_file_part( "</{$this->xml_offer}>" );
				if ( substr_count( $content, "<{$this->xml_offer} " ) > 1 ) {
					fseek( $f, ftell( $f ) - strlen( strstr( $content, "</{$this->xml_offer}>" ) ) + 8 );
				}
				$psp = mb_strpos( $content, "<{$this->xml_offer} ", 0, 'utf-8' );
				$pep = mb_strpos( $content, "</{$this->xml_offer}>", 0, 'utf-8' );

				if ( false !== $psp && false !== $pep ) {
					$product = mb_substr( $content, $psp + mb_strlen( "<{$this->xml_offer}", 'utf-8' ), $pep - $psp - mb_strlen( "<{$this->xml_offer} ", 'utf-8' ) + 1, 'utf-8' );
					$matches = [];
			    			    
				    if ( $this->is_coupon_xml ) {
				    	$product = str_replace( array("\r", "\n"), '', $product );
				    	$product = str_replace( 'category_id>', 'categoryId>', $product );
				    	$product = str_replace( 'gotolink>', 'url>', $product );
				    	$product = str_replace( 'logo>', 'picture>', $product );
				    	$product.= '<price>0</price><currencyId>RUR</currencyId>';
				    }

					if ( ! CpaimpImport::filterImport( $product ) ) {
						continue;
					}						
			    
					preg_match( '/ id="(.+?)"/', $product, $matches );
					$id = @$matches[1];
					preg_match( '|\<url\>(.+?)\</url\>|', $product, $matches );
					$url = @$matches[1];
					preg_match( '|\<price\>(.+?)\</price\>|', $product, $matches );
					$price = @$matches[1];
					preg_match( '|\<currencyId\>(.+?)\</currencyId\>|', $product, $matches );
					$currency = @$matches[1];
					preg_match( '|\<picture>(.+?)</picture\>|s', $product, $matches );
					$image = @$matches[1];
					preg_match( '|\<model>(.+?)</model\>|s', $product, $matches );
					$model = @$matches[1];
					preg_match( '|\<sales_notes>(.+?)</sales_notes\>|s', $product, $matches );
					$sales_notes = @$matches[1];

					preg_match( '|\<name\>(.+?)\</name\>|', $product, $matches );
					$title = @$matches[1];
					$title = str_replace( ['<![CDATA[', ']]>'], '', $title );
					if ( !$title && preg_match( '|\<model\>(.+?)\</model\>|', $product, $matches ) ) {
						$title = @$matches[1];
						$title = str_replace( ['<![CDATA[', ']]>'], '', $title );
					}

					$descr = '';
					if ( substr_count( $product, '<description>' ) > 0 ) {
						$ps = mb_strpos( $product, '<description>', 0, 'utf-8' );
						$pe = mb_strpos( $product, '</description>', 0, 'utf-8' );
						$descr = mb_substr( $product, $ps + mb_strlen( '<description>', 'utf-8' ), $pe - $ps - mb_strlen( '<description>', 'utf-8' ), 'utf-8' );
						$descr = str_replace( ['<![CDATA[', ']]>'], '', $descr);

						// Delete all links
				    	if ( strpos( $descr, 'http://' ) > 0 ) {
				    		$descr = substr( $descr, 0, strpos( $descr, 'http://' ) );
				    	}
			    	}			    
			      
					preg_match( '|\<categoryId\>(.+?)\</categoryId\>|', $product, $matches );
					$categoryId = @$matches[1];

			    	$import_goods[] = $id;
			        
			  
					if ( $this->is_coupon_xml ) {
						$price = ' '; 

					   	preg_match( '|\<date_end\>(.+?)\</date_end\>|', $product, $matches );
						$date_end = @$matches[1];
					   	preg_match( '|\<promocode\>(.+?)\</promocode\>|', $product, $matches );
						$promocode = @$matches[1];
					   	preg_match( '|\<discount\>(.+?)\</discount\>|', $product, $matches );
						$discount = @$matches[1];    
					 	preg_match( '|\<type_id\>(.+?)\</type_id\>|', $product, $matches );
						$type_id = @$matches[1];  
					   	preg_match( '|\<advcampaign_id\>(.+?)\</advcampaign_id\>|', $product, $matches );
						$advcampaign_id = @$matches[1];       
					   	preg_match( '|\<specie_id\>(.+?)\</specie_id\>|', $product, $matches );
						$specie_id = @$matches[1];       
			      
			      		$company = '';
						if ( isset( $this->advcampaign[$advcampaign_id] ) ) {
							$company = $this->advcampaign[$advcampaign_id]; 
						}
			   
			   
			   // <param name="(.+?)"\>(.+?)\</param\>
			   
			   
			/* url заменить gotolink на promolink
			   В карточке нужны поля:
			   date_start date_end types promocode discount
			   description
			*/  
						if ( '' !== $descr ) {
							$descr .= '<br>';
						}

						$descr .= "Завершение акции: {$date_end}<br>";
						$descr .= "Промокод: {$promocode}<br>";
						$descr .= "Скидка: {$discount}<br>";
	
						$product .= '<param name="Компания">' . $company . '</param>';
						$product .= '<param name="Тип">' . $this->type_str[$type_id] . '</param>';
						$product .= '<param name="Вид">' . $this->species_str[$specie_id] . '</param>';
					}
			   
			   		if ( ( isset( $model ) ) && ( $model !== '<![CDATA[]]>' ) ) {
			   			$product .= '<param name="Модель">' . $model . '</param>';
			   		}
					if ( ( isset( $sales_notes ) ) && ( $sales_notes !== '<![CDATA[]]>' ) ) {
						$product .= '<param name="Примечание">' . $sales_notes . '</param>';
					}

					//Блок замены из параметров
					$replace = self::$config->config('replace');
	

					if (!empty($replace))
					{
						$replace_raw = explode(',', str_replace(array(' ',),array(','), $replace));
						foreach ($replace_raw as $value)
						{
    						preg_match('/\[(.*)\]/', $value, $m);
    						$item_value = $m[1];
    						$search_string = explode(';', $item_value);

							$title = str_replace($search_string[0], $search_string[1], $title);
							$product = str_replace($search_string[0], $search_string[1], $product);
						}						
					}						
					//
					$product_obj = new Cpaimp_Product( [
						'id'			=> $id,
						'title'			=> $title,
						'description'	=> nl2br( $descr ),
						'url'			=> $url,
						'price'			=> $price,
						'currency'		=> $currency,
						'image'			=> $image,
						'category_id'	=> $categoryId,
					], CpaimpImport::parseParams( $product ) );
					$product_obj->save();
				}
				else {
					break;
				}
			}

			if ( empty( $import_goods ) ) {
				ImpDB()->mark_all_products_as_updated( Cpaimp_Importer::$vendor_id );
				ImpLogger()->log( 'XML file has no products!' );
			}
		}

		public function load_file_part($delimiter, $path = null) {
			if ( false === stripos( $this->content, $delimiter ) ) {
				return '';
			}

			$result = '';
			$new_buffer = '';
			while ( $row = fgets( $this->fh ) ) {
				if ( ( $p = mb_strpos( $row, $delimiter, 0, 'utf-8') ) !== false ) {
					$result .= mb_substr( $row, 0, $p + mb_strlen( $delimiter, 'utf-8' ), 'utf-8' );
					$new_buffer = mb_substr( $row, $p + mb_strlen( $delimiter, 'utf-8' ), mb_strlen( $row, 'utf-8' ), 'utf-8' );
					break;
				}
				else {
					$result .= $row;
				}
			}

			$result = $this->buffer . $result;
			$this->buffer = $new_buffer;
		  
			return $result;
		}

		public function finalize() {
			fclose( $this->fh );
			if ( $this->tmp_filepath ) {
				unlink( $this->tmp_filepath );
			}
		}
	}
}