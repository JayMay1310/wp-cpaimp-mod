<?php
/**
 * Страница опций выделена в отдельный контейнер, чтобы разгрузить файл плагина.
 * Применён паттерн SingleTon
 */
class IM_Options_Controller
{
	/**
	 * Класс wpdb
	 * @var wpdb
	 */
	protected $_wpdb;

	/**
	 * Закрытый конструктор
	 */
	protected function __construct()
	{
		global $wpdb;
		$this->_wpdb = $wpdb;
	}

	/**
	 * Функция для сохранения опций плагина
	 * @include templates/admin-options.php
	 * @return void
	 */
	public  function render()
	{
		$isUpdated = FALSE;
		$isDeleted = FALSE;
		$isError = FALSE;
		if (isset($_POST['action']))
		{
			switch($_POST['action'])
			{
				case 'update':
					foreach(array('ps_get_enable', 'ps_download_images','ps_url', 'ps_page','ps_row_limit','ps_limit','import_price','import_title','import_vendor','import_categorypid','import_categoryid','ps_delete_prod','ps_delete_cats','ps_offer') as $option)
					{
						IM_Config::init()->set($option, in_array($option, array('ps_download_images','ps_get_enable','ps_delete_prod','ps_delete_cats','ps_offer')) ?
									(isset($_POST[$option]) ? '1' : '0') :
									@$_POST[$option]
						);
					}
					$isUpdated = TRUE;
					break;
				case 'delete':
					ignore_user_abort( true );
					set_time_limit( 36000 );
					$type  = @$_POST['type'];
					$agree = @$_POST['agree'];
					if ( in_array( $type, array( 'all', 'products', 'categories', 'cats' ) ) && $agree ) {
						$vendor_id = 0;
						// "Сквозной импорт"
						if ( (bool)IM_Config::init()->get( 'ps_offer' ) ) {
							try {
								// Importer can be not initialized on this step so we call get_instance()
								$importer = Cpaimp_Importer::get_instance();
								$vendor_id = $importer->config( 'vendor_id' );
							}
							catch ( Exception $e ) {
								if ( 'all' !== $type ) {
									break;
								}
							}
						}

						if ( 'all' === $type ) {
							ImpDB()->clear_woo();
						}
						elseif ( 'products' === $type ) {
							ImpDB()->delete_all_products( $vendor_id );
						}
						elseif ( 'categories' === $type ) {
							ImpDB()->delete_all_terms( $vendor_id );
						}
						elseif ( 'cats' === $type ) {
							ImpDB()->delete_all_empty_terms( $vendor_id );
						}

						// if ( 'all' === $type ) {
						// 	ImpDB()->truncate_tables();
						// }



						// if ($type == 'all' || $type == 'products')
						// {
						// 	self::deleteProducts();
						// }
						// if ($type == 'all' || $type == 'categories')
						// {
						// 	self::deleteCategories();
						// }
						// if ($type == 'all' || $type == 'cats')
						// {
						// 	self::deleteEmptyCats();
						// }            
						$isDeleted = true;
					}
					else {
						$isError = true;
					}

					break;
					
			}
		}


		//$importer = Cpaimp_Importer::get_instance();
		$url = IM_Config::init()->get('ps_url');
		$get_enable = (int)IM_Config::init()->get('ps_get_enable');
		$ps_page = IM_Config::init()->get('ps_page');
		$dirname = basename(dirname(__FILE__));
		$categoriesNumber = $this->calcCategories();
		$productsNumber = $this->calcProducts();

		require_once(dirname(__FILE__).'/templates/admin-options.php');

	}
//*--------------------------------------------------------------------------------------------------------------------------------*//
	/**
	 * Количество категорий в базе
	 * @return integer
	 */
	public function calcCategories()
	{
		return $this->_wpdb->get_var("SELECT COUNT(*) FROM `{$this->_wpdb->term_taxonomy}` WHERE `taxonomy` = 'product_cat'");
	}

	/**
	 * Количество продуктов в базе
	 * @return integer
	 */
	public function calcProducts()
	{
		return $this->_wpdb->get_var("SELECT COUNT(*) FROM `{$this->_wpdb->posts}` WHERE `post_type` = 'product'");
	}

	public function deleteCategories()
	{
   //print "DELETE a,b,c FROM {$this->_wpdb->term_taxonomy} a LEFT JOIN {$this->_wpdb->term_relationships} b ON (a.term_taxonomy_id = b.term_taxonomy_id) LEFT JOIN {$this->_wpdb->terms} c ON (a.term_id = c.term_id) WHERE a.taxonomy = 'product_cat';";
		return $this->_wpdb->get_var("DELETE a,b,c FROM {$this->_wpdb->term_taxonomy} a LEFT JOIN {$this->_wpdb->term_relationships} b ON (a.term_taxonomy_id = b.term_taxonomy_id) LEFT JOIN {$this->_wpdb->terms} c ON (a.term_id = c.term_id) WHERE a.taxonomy = 'product_cat';");
	}
  //*-------------------------------------------------------------------------------------------------------------------------------*//
	public function deleteEmptyCats()
	{
  for ($i=0;$i<10;$i++)
  {
  $terms=array();
  global $wpdb;
  $addon='';
  if ((int)IM_Config::init()->get('ps_offer'))
   $addon =((isset($_GET['offer'])) ? "AND offer='".$_GET['offer']."'" : '');
  $rows=$wpdb->get_results(" SELECT * FROM $wpdb->term_taxonomy WHERE taxonomy = 'product_cat' $addon ");
  foreach ($rows as $row)
   {
   $rows2=$wpdb->get_results(" SELECT COUNT(*) as cc FROM so_term_relationships WHERE `term_taxonomy_id` = '".$row->term_id."' ");
   if ($rows2[0]->cc < 1)
    {
    $terms[]=$row->term_id;
    }
   }
  
  foreach ($terms as $term)
   {
   $rows2=$wpdb->get_results(" SELECT COUNT(*) as cc FROM so_term_taxonomy WHERE parent = '".$term."' ");
   if ($rows2[0]->cc ==0)
    {
    $wpdb->query(" DELETE FROM so_terms WHERE term_id='".$term."' ");
    $wpdb->query(" DELETE FROM so_term_taxonomy WHERE term_taxonomy_id='".$term."' ");
    $wpdb->query(" DELETE FROM so_term_relationships WHERE term_taxonomy_id='".$term."' ");    
    }
   }
  } 
   
  return true;
  }  
//*----------------------------------------------------------------------------------------------------------------------------*//
	public function deleteProducts()
	{
   //print_r("DELETE a,b,c FROM {$this->_wpdb->posts} a LEFT JOIN {$this->_wpdb->term_relationships} b ON (a.ID = b.object_id) LEFT JOIN {$this->_wpdb->postmeta} c ON (a.ID = c.post_id) WHERE a.post_type = 'product'");
		return $this->_wpdb->query("DELETE a,b,c FROM {$this->_wpdb->posts} a LEFT JOIN {$this->_wpdb->term_relationships} b ON (a.ID = b.object_id) LEFT JOIN {$this->_wpdb->postmeta} c ON (a.ID = c.post_id) WHERE a.post_type = 'product'");
	}

	/**
	 * Закрываем конструктор и прикручиваем паттерн Singleton.
	 * @static
	 * @return IM_Options_Controller
	 */
	public static function init()
	{
		static $self;
		if (!is_object($self))
		{
			$self = new self();
		}
		return $self;
	}
}
