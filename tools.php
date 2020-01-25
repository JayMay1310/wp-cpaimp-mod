<?php
ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL);

//проверка на существование опции в настройках постоянных ссылок
function im_perma_check(){
	$opt=get_option('woocommerce_permalinks');
	if (empty($opt["category_base"])){
	?>
	<script type="text/javascript">
		$j = jQuery;
		$j().ready(function(){
			$j('.wrap > h2').parent().prev().after('<div class="update-nag">Чтобы избежать ошибок <a href="<?=admin_url()?>options-permalink.php">измените</a> значение опции "Постоянная ссылка рубрик" на непустое.</div>');
		});
	</script>
	<?php
	}
}
add_action('admin_head','im_perma_check');
//Рекурсивное получение родительских категорий
function ps_get_taxonomy_parents($term_id, array $terms = array())
{
	$obTerm = get_term($term_id, 'product_cat');
	if (!empty($obTerm->parent))
	{
		$terms = ps_get_taxonomy_parents($obTerm->parent, $terms);
	}
	$terms[] = $obTerm;
	return $terms;
}

function fixUrl($url) {
	return preg_replace('/(:?\?.+?)\?/', '$1&', $url);
}


/**
 * Will be used if future to replace cron.php
 */
class CpaimpImport
{
	static public function checkCurl()
	{
		return function_exists('curl_init');
	}


	static public function checkFileGetContentsCurl()
	{
		return ini_get('allow_url_fopen');
	}

	/**
	 * Проверка mime-типа файла. Добавлена для того, чтобы избежать проблем со скачанной html-страницей.
	 * Если возвращает TRUE — значит проблема имеет место.
	 * @static
	 * @param string $file Путь к загруженному файлу.
	 * @return bool
	 */
	static public function checkMimeType($file)
	{
		if (class_exists('finfo'))
		{
			$obFinfo = new finfo();
			//Предотвращение ошибки в ранних версиях php до 5.2
			if (!defined('FILEINFO_MIME_TYPE'))
			{
				define('FILEINFO_MIME_TYPE', 16);
			}
			$mimeType = $obFinfo->file($file, FILEINFO_MIME_TYPE);
		}
		else
		{
			//Если в системе нет ни mimt_content_type ни finfo расширения, то мы никак не можем проверить файл. Пропускаем проверку.
			return FALSE;
		}
		return stripos($mimeType, 'zip') === FALSE && $mimeType != 'application/octet-stream';
	}

	static public function getFileFromUrl()
	{
		$url = IM_Config::init()->get('ps_url');
		if (!self::checkCurl())
		{
			$opts = array(
				'http'=>array(
					'method'=>"GET",
					'header'=>"Accept-language: en\r\n"
				)
			);
			$context = stream_context_create($opts);
			return file_get_contents($url, false, $context);
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$file = curl_redirect_exec($ch);
		curl_close($ch);
		return $file;
	}

	static public function parseParams($content)
	{
		preg_match_all('|\<param name="(.+?)"\>(.+?)\</param\>|', $content, $matches);
		$params = array();
		foreach($matches[1] as $key => $title)
		{
      $params[$title] = str_replace(']]>', '', str_replace('<![CDATA[', '', $matches[2][$key]));
      if ((isset($params[$title])) and ($params[$title]=='')) unset($params[$title]);
      if ((isset($params[$title])) and (substr($params[$title],0,1)==',')) $params[$title]=substr($params[$title],1);
    }
		$params['params_list'] = implode(',',$matches[1]);

		preg_match('|\<vendor\>(.+?)\</vendor\>|', $content, $matches);
		$params['vendor'] = '';
		if (!empty($matches[1]))
			$params['vendor'] = str_replace(']]>', '', str_replace('<![CDATA[', '', @$matches[1]));
		
    
    return $params;
	}

	static public function filterImport($content)
	{
		$stateVendor = $stateTitle = $statePrice = $stateCategoryid = $stateCategorypid = TRUE;

		//if ($titleVendor = IM_Config::init()->get('import_vendor'))
    if ( ((isset($_GET['FilterVendor'])) and ($titleVendor = $_GET['FilterVendor'])) or
     ($titleVendor = IM_Config::init()->get('import_vendor')) )
		 { 
     	preg_match('|\<vendor\>(.+?)\</vendor\>|', $content, $matches);
			$vendor = @$matches[1];
			$vendor = str_replace('<![CDATA[', '', $vendor);
			$vendor = str_replace(']]>', '', $vendor);
			if (strtolower($vendor) !== strtolower($titleVendor))
				$stateVendor = FALSE;
		}
		//if ($titleFilter = IM_Config::init()->get('import_title'))
    if ( ((isset($_GET['FilterTitle'])) and ($titleFilter = $_GET['FilterTitle'])) or
     ($titleFilter = IM_Config::init()->get('import_title')) )
		{
		 	preg_match('|\<name\>(.+?)\</name\>|', $content, $matches);
			$title = @$matches[1];
			$title = str_replace('<![CDATA[', '', $title);
			$title = str_replace(']]>', '', $title);
			if (stripos($title, $titleFilter) === FALSE)
				$stateTitle = FALSE;
    }
    
		//if ($priceFilter = IM_Config::init()->get('import_price'))
    if ( ((isset($_GET['FilterPrice'])) and ($priceFilter = $_GET['FilterPrice'])) or
     ($priceFilter = IM_Config::init()->get('import_price')) )
		{
			preg_match('|\<price\>(.+?)\</price\>|', $content, $matches);
			$price = @$matches[1];
			if (floatval($priceFilter) > floatval($price))
				$statePrice = FALSE;
		}

		
    if ( ((isset($_GET['categorypidFilter'])) and ($categorypidFilter = $_GET['categorypidFilter'])) or
     ($categorypidFilter = IM_Config::init()->get('import_categorypid')) )
		 {         
		 preg_match('|\<categoryId\>(.+?)\</categoryId\>|', $content, $matches);
		 $categoryid = @$matches[1];
		 $categoryid = str_replace('<![CDATA[', '', $categoryid);
		 $categoryid = str_replace(']]>', '', $categoryid);
     $categorypidFilter=explode(',', str_replace(array(' ',', ',';'),array(',',',',','),$categorypidFilter) );

		 /* Вот тут надо по категории найти ее родительскую ---- $categorypid=parentid->db($categoryid)*/
     $stateCategorypid = false;
     global $wpdb;
     $parent=$wpdb->get_row("SELECT * FROM $wpdb->terms WHERE  term_group = {$categoryid}")->term_id;
     $categorypid=$wpdb->get_row(" SELECT * FROM $wpdb->terms WHERE  term_id = '$parent' ")->term_group;
     $add=(isset($_GET['add'])) ? $_GET['add'] : ''; for ($i=0;$i<sizeof($categorypidFilter);$i++) $categorypidFilter[$i]=$categorypidFilter[$i].$add;
     while ($parent>0)
      {
      $categorypid=$wpdb->get_row(" SELECT * FROM $wpdb->terms WHERE  term_id = '$parent' ")->term_group;
      $rows=$wpdb->get_results(" SELECT * FROM $wpdb->term_taxonomy WHERE term_id='".$parent."' ");
      if (isset($rows[0])) $parent=$rows[0]->parent; else { /*$stateCategorypid=true;*/ $parent=0; }
     // print_r($categorypid.',');
      if (in_array($categorypid, $categorypidFilter))
		   $stateCategorypid=true;  
      }
		 }
  
   
    if ( ((isset($_GET['categoryidFilter'])) and ($categoryidFilter = $_GET['categoryidFilter'])) or
     ($categoryidFilter = IM_Config::init()->get('import_categoryid')) )
		 {      
      preg_match('|\<categoryId\>(.+?)\</categoryId\>|', $content, $matches);
			$categoryid = @$matches[1];
			$categoryid = str_replace('<![CDATA[', '', $categoryid);
			$categoryid = str_replace(']]>', '', $categoryid);
			$categoryidFilter=explode(',', str_replace(array(' ',', ',';'),array(',',',',','),$categoryidFilter) );
      $add=(isset($_GET['add'])) ? $_GET['add'] : ''; for ($i=0;$i<sizeof($categoryidFilter);$i++) $categoryidFilter[$i]=$categoryidFilter[$i].$add;
      if (!in_array($categoryid, $categoryidFilter))
		   $stateCategoryid=false;  
		}
        
         
    return ($stateTitle && $statePrice && $stateVendor && $stateCategoryid && $stateCategorypid);

	}

	/**
	 * Получение серверного пути к директории загрузки файлов
	 * @return mixed
	 */
	static function get_upload_path()
	{
		$dirData = wp_upload_dir();
		return $dirData['path'];
	}

	/**
	 * Проверка на возможность записи в директорию аплоада
	 * @return bool
	 */
	static function is_upload_directory_writeable()
	{
		return is_writable(self::get_upload_path());
	}

	/**
	 * @static
	 * Проверка разрешения на доступ к файлам cron.php и get-direct.php
	 */
	static function check_access()
	{
		if (PHP_SAPI === 'cli')
		{
			return;
		}
		$accessCode = IM_Config::init()->get('ps_access_code');
		$getEnable = (int)IM_Config::init()->get('ps_get_enable');
    //$ps_delete_prod = (int)IM_Config::init()->get('ps_delete_prod');

		$getCode = empty($_GET['code']) ? NULL : $_GET['code'];
		$postCode = empty($_POST['code']) ? NULL : $_POST['code'];

		if (!$postCode)
		{
			if (!$getCode)
			{
				die('Не найден код');
			}
			elseif (!$getEnable)
			{
				die('Возможность обновления базы GET-запросом выключена');
			}
			elseif ($getCode != $accessCode)
			{
				die('Проверьте правильность кода');
			}
		}
		elseif ($postCode != $accessCode)
		{
			die('Проверьте правильность кода');
		}
	}
}


/**
 * Создание или обновление таксономии.
 * При этом происходит связывание термов и таксономии
 * @param $item
 * @return
 */
function importTerm(array $category)
{     
	global $wpdb;
	$parentId = 0;
	if (!empty($category['parent_id'])) {
		$parentDbItem = get_category_by_outer_id($category['parent_id']);
		if ($parentDbItem)
			$parentId = $parentDbItem->term_id;
	}    

	// Если категория существует то обновляем её
	if (($dbItem = get_category_by_outer_id($category['id']))) {
		$termId = $dbItem->term_id;
		$args = array('parent' => $parentId);
		// Старые мета
		$original_name = get_post_meta($termId, 'original_name', $single = true);
		$original_slug = get_post_meta($termId, 'original_slug', $single = true);

		// Если оригинальное имя не было изменено
		// а новое отличается то можем переписать его
		if($dbItem->name == $original_name && $dbItem->name != $category['title']) {
			$args['name'] = $category['title'];
			update_post_meta($termId, 'original_name', $category['title']);
		}

		// Если оригинальный слаг не был изменен
		// а новоый отличается то можем переписать его
		if($dbItem->slug == $original_slug && $dbItem->slug != transliteration($category['title'])) {
			$args['slug'] = transliteration($category['title']);
			update_post_meta($termId, 'original_slug', transliteration($category['title']));
		}

		wp_update_term($dbItem->term_id, 'product_cat', $args);
		if (IM_Config::init()->get('ps_offer')) {$wpdb->query(" UPDATE $wpdb->term_taxonomy SET offer='".((isset($_GET['offer'])) ? $_GET['offer'] : '')."' WHERE 	term_id = '".$dbItem->term_id."' ");}
    //exit;    
  }
	// Если категории не существует то создаём её
	else { 
		$result = wp_insert_term($category['title'], 'product_cat', array(
			'parent'	=> $parentId,
			'slug'		=> transliteration($category['title'])
		));
    
		if (is_array($result)) {
			$termId = $result['term_id'];
		}
		elseif (is_object($result) && get_class($result) == 'WP_Error') {
			if (!empty($result->error_data['term_exists']))
				$termId = $result->error_data['term_exists'];
		}

		// Сохраняем слаги на слудующий раз
		update_post_meta($termId, 'original_name', $category['title']);
		update_post_meta($termId, 'original_slug', transliteration($category['title']));
		if (IM_Config::init()->get('ps_offer')) { $wpdb->query(" UPDATE $wpdb->term_taxonomy SET offer='".((isset($_GET['offer'])) ? $_GET['offer'] : '')."' WHERE 	term_id = '".$termId."' "); }
  //exit;
  }
	if ($termId) {
		$wpdb->query("UPDATE {$wpdb->terms} SET term_group = {$category['id']} WHERE term_id = $termId");

	}
//	var_dump($parentId, $termId); die;
}

/**
 * Импортирование продукта из xml
 * @param array $item
 * @param null $params
 * @return mixed
 */
function getSeoUrl($url)
  {
  $tr = array ("А"=>"A","Б"=>"B","В"=>"V","Г"=>"G","Д"=>"D","Е"=>"E","Ж"=>"J","З"=>"Z","И"=>"I","Й"=>"Y","К"=>"K","Л"=>"L",
	"М"=>"M","Н"=>"N","О"=>"O","П"=>"P","Р"=>"R","С"=>"S","Т"=>"T","У"=>"U","Ф"=>"F","Х"=>"H","Ц"=>"TS","Ч"=>"CH",
	"Ш"=>"SH","Щ"=>"SCH","Ъ"=>"","Ы"=>"YI","Ь"=>"","Э"=>"E","Ю"=>"YU","Я"=>"YA","а"=>"a","б"=>"b","в"=>"v","г"=>"g",
	"д"=>"d","е"=>"e","ж"=>"j","з"=>"z","и"=>"i","й"=>"y","к"=>"k","л"=>"l","м"=>"m","н"=>"n","о"=>"o","п"=>"p",
	"р"=>"r","с"=>"s","т"=>"t","у"=>"u","ф"=>"f","х"=>"h","ц"=>"ts","ч"=>"ch","ш"=>"sh","щ"=>"sch","ъ"=>"y","ы"=>"yi",
	"ь"=>"","э"=>"e","ю"=>"yu","я"=>"ya","."=>"-"," "=>"-","?"=>"-","/"=>"-","\\"=>"-","*"=>"-",":"=>"-","*"=>"-",
	">"=>"-","|"=>"-","'"=>"", "&quot;"=>"", "("=>"", ")"=>"", "ё"=>"e"); 
  $url = strtr($url,$tr);
  return $url;
  } 
 
function importPost(array $item, $params = NULL)
{  
 
	/*
	 * If connected woocommerce plugin - add posts to post type of woocommerce
	 */
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	if(!is_plugin_active('woocommerce/woocommerce.php')){
		echo "Для корректной работы этого плагина, необходимо установить Woocommerce плагин.";
		die;
	}
      	$product_params = $params;     
	global $wpdb;  
  
  $rd_args = array(
		'post_type' => 'product',
		'meta_key' => 'offer_id',
		'meta_value' => $item['id']
	);
	$q = new WP_Query( $rd_args );
	$obItem=@$q->posts[0];
	//$obItem = $wpdb->get_row("SELECT * FROM {$wpdb->posts} WHERE ID = {$item['id']}");
	$postId = null;
	if (!empty($obItem->ID))
	{
  
  	if ($obItem->post_status == 'trash')
			return;
		if (!$obItem->post_modified || $obItem->post_date != $obItem->post_modified)
		{
			$item['title'] = $obItem->post_title;
			$item['description'] = $obItem->post_content;
		}
		$params = array(
			'ID'				=> $obItem->ID,
			'post_title'		=> $item['title'],
			'post_content'		=> $item['description'],
			'post_type'			=> 'product',
			//'post_status'		=> 'publish',
			'post_name'			=> transliteration($item['title']),
      //'offer' => (isset($_GET['offer'])) ? $_GET['offer'] : '',
      //$wpdb->query(" UPDATE $wpdb->posts SET offer='".$params['offer']."' WHERE ID = '".$params['ID']."' ");
		);
		
    
    if (get_post_meta($obItem->ID, 'edited_by_user', TRUE))
		{
			unset($params['post_title']);
			unset($params['post_content']);
			unset($params['post_name']);
		}


    wp_update_post($params);

		//set Cpaimp offer_id
	
  	update_post_meta($obItem->ID, 'offer_id', $item['id']);
		
    	
		//set Cpaimp url
		if($item['url'])
			update_post_meta($obItem->ID, 'url', $item['url'], get_post_meta($obItem->ID, 'url', TRUE));

		//set Cpaimp price
		if($item['price']){
			update_post_meta($obItem->ID, '_regular_price', $item['price'], get_post_meta($obItem->ID, '_regular_price', TRUE));
			update_post_meta($obItem->ID, '_price', $item['price'], get_post_meta($obItem->ID, '_price', TRUE));
			update_post_meta($obItem->ID, '_visibility', 'visible', get_post_meta($obItem->ID, '_visibility', TRUE));
			update_post_meta($obItem->ID, '_stock_status', 'instock', get_post_meta($obItem->ID, '_stock_status', TRUE));
		} 
     

		$postId = $obItem->ID;

		$wpdb->query( $wpdb->prepare(
			"
	 		UPDATE $wpdb->term_relationships
			SET term_order = %d
			WHERE object_id = %d
			AND term_taxonomy_id = %d
			",
			0,
			$postId,
			5
		) );

  }
	else
	{
    
  	$postId = wp_insert_post(array(
			'post_title'		=> $item['title'],
			'post_content'		=> $item['description'],
			'post_type'			=> 'product',
			'post_status'		=> 'publish',
			'post_mime_type'	=> $item['id'],
			'comment_status'	=> 'closed',
			'post_name'			=> transliteration($item['title']),
      'offer' => (isset($_GET['offer'])) ? $_GET['offer'] : '',
		));   

    

		//set Cpaimp offer_id
			add_post_meta($postId, 'offer_id', $item['id']);

		//set Cpaimpn url
		if($item['url']) {
			add_post_meta( $postId, 'url', $item['url'], true );
			add_post_meta( $postId, '_product_url', $item['url'], true );
		}

		//set Cpaimp price
		if($item['price']){
			add_post_meta($postId, '_regular_price', $item['price'], TRUE);
			add_post_meta($postId, '_price', $item['price'], TRUE);
			add_post_meta($postId, '_visibility', 'visible', TRUE);
			add_post_meta($postId, '_stock_status', 'instock', TRUE);
		}

		add_post_meta($postId, '_wp_page_template', 'sidebar-page.php', TRUE);
    
		/*
		    $wpdb->query( $wpdb->prepare(
			"INSERT INTO $wpdb->term_relationships (object_id, term_taxonomy_id, term_order) VALUES (%d, %d, %d)",
			$postId, 5,	0	) );
      */
    $rows=$wpdb->get_results(" SELECT COUNT(*) as cc FROM $wpdb->term_relationships WHERE object_id='".$postId."' AND term_taxonomy_id='5' ");
    if ($rows[0]->cc == 0)     
      $wpdb->query( "INSERT INTO $wpdb->term_relationships SET object_id='".$postId."', term_taxonomy_id='5', term_order='0' ");      

	}
  
// Разобраться зачем post->offer
	if (IM_Config::init()->get('ps_offer')) { $wpdb->query(" UPDATE $wpdb->posts SET offer='".((isset($_GET['offer'])) ? $_GET['offer'] : '')."' WHERE 	ID = '".$postId."' "); }
    
  

	/**
	 * Подгрузка изображения
	 */
	if (!empty($item['image']))
	{
		download_image($item['image'], $postId);
	}

  if (isset(get_category_by_outer_id($item['category_id'])->term_id))
	wp_set_object_terms($postId, array(intval(get_category_by_outer_id($item['category_id'])->term_id)), 'product_cat');

  
	//add product params  
	$attributes = array();    
  
  if (($product_params['vendor']!=='') and ($product_params['vendor']!=='<![CDATA[]]>')) $product_params['Бренд']=$product_params['vendor'];
  $product_params['IDorig']=$item['id']; 
  
  if ((isset($_GET['offer'])) and ($_GET['offer']!=='')) $product_params['Offer']=$_GET['offer'];

  unset($product_params['params_list']);
	unset($product_params['vendor']);
	$position=0;
  
  foreach($product_params as $name => $value)
	{
  //$name=str_replace("'","&apos;",$name);
  $value=str_replace("'","&apos;",$value);  
  
  
  $wpdb->query( " DELETE FROM $wpdb->postmeta WHERE post_id='$postId' AND meta_key='_product_attributes' " );  
  
  if (in_array($name,array('Размер','Цвет','Тип','Компания','Вид','Бренд','vendor','Материал','Сезон')))
   {  
   $Tax_ids['post_id']=$postId;
   //print_r($attributes);   
     // добавление атрибута
   $attribute=array(
    'attribute_name'=>wc_sanitize_taxonomy_name( getSeoUrl($name) ),
    'attribute_label'=>$name,
    'attribute_type'=>'select',
    'attribute_orderby'=>'name',
    'attribute_public'=>'0'
    );
   $rows=$wpdb->get_results(" SELECT COUNT(*) as cc, attribute_id FROM ".$wpdb->prefix."woocommerce_attribute_taxonomies WHERE attribute_name = '".$attribute['attribute_name']."' ");
   if ($rows[0]->cc ==0)
    {
    $wpdb->insert( $wpdb->prefix . 'woocommerce_attribute_taxonomies', $attribute );
    $Tax_ids['attribute_id']=$wpdb->insert_id;
 
    $rows=$wpdb->get_results(" SELECT * FROM $wpdb->options WHERE option_name = '_transient_wc_attribute_taxonomies' ");
    $row=unserialize($rows[0]->option_value);
    $attribute['attribute_id']=$Tax_ids['attribute_id'];
    $row[]=(object)($attribute);
    $row=serialize($row);
    $wpdb->get_results(" UPDATE $wpdb->options SET option_value='".$row."' WHERE option_name = '_transient_wc_attribute_taxonomies' ");
    }
    else $Tax_ids['attribute_id']=$rows[0]->attribute_id;

    // добавление свойства атрибуту
   $attribute2=array();
   $attribute2['name']=$value;
   $attribute2['slug']=wc_sanitize_taxonomy_name( getSeoUrl($value) );
   $attribute2['term_group']='0';
   $rows=$wpdb->get_results(" SELECT COUNT(*) as cc, term_id FROM $wpdb->terms WHERE name = '".$attribute2['name']."' ");
   if ($rows[0]->cc ==0)
    {
    $wpdb->insert( $wpdb->terms, $attribute2 );
    $Tax_ids['term_id']=$wpdb->insert_id;
    }
    else $Tax_ids['term_id']=$rows[0]->term_id; 
   
   
   $attribute3=array();
   $attribute3['term_id']=$Tax_ids['term_id'];
   $attribute3['taxonomy']='pa_'.$attribute['attribute_name'];
  // $attribute3['count']='count+1';
   $rows=$wpdb->get_results(" SELECT COUNT(*) as cc, term_taxonomy_id FROM $wpdb->term_taxonomy WHERE term_id = '".$attribute3['term_id']."' ");
   if ($rows[0]->cc ==0)
    {
   // $attribute3['count']='1';
    $wpdb->insert( $wpdb->term_taxonomy, $attribute3 ); 
    $Tax_ids['term_taxonomy_id']=$wpdb->insert_id;
    }
    else
    {
    //$wpdb->get_results(" UPDATE term_taxonomy_id SET count=count+1 WHERE term_id = '".$attribute3['term_id']."' ");
    $Tax_ids['term_taxonomy_id']=$rows[0]->term_taxonomy_id; 
    }
        
   $attribute4=array();
   $attribute4['term_id']=$Tax_ids['term_id'];
   $attribute4['meta_key']='order_pa_'.$attribute['attribute_name'];
   $attribute4['meta_value']='0';
   $wpdb->insert( $wpdb->termmeta, $attribute4 ); 
 
 /*
   $wpdb->query( $wpdb->prepare(
			"INSERT INTO $wpdb->term_relationships (object_id, term_taxonomy_id, term_order) VALUES (%d, %d, %d)",
			$postId, $Tax_ids['term_taxonomy_id'], 0 ) );
     */
     
   $rows=$wpdb->get_results(" SELECT COUNT(*) as cc FROM $wpdb->term_relationships WHERE object_id='".$postId."' AND term_taxonomy_id='".$Tax_ids['term_taxonomy_id']."' ");
   if ($rows[0]->cc == 0) 
     $wpdb->query( "INSERT INTO $wpdb->term_relationships SET object_id='".$postId."', term_taxonomy_id='".$Tax_ids['term_taxonomy_id']."', term_order='0'");
     
   //print_r(unserialize('a:2:{s:9:"pa_razmer";a:6:{s:4:"name";s:9:"pa_razmer";s:5:"value";s:0:"";s:8:"position";s:1:"0";s:10:"is_visible";i:1;s:12:"is_variation";i:0;s:11:"is_taxonomy";i:1;}s:8:"pa_tsvet";a:6:{s:4:"name";s:8:"pa_tsvet";s:5:"value";s:0:"";s:8:"position";s:1:"1";s:10:"is_visible";i:1;s:12:"is_variation";i:0;s:11:"is_taxonomy";i:1;}}') );
   $attributes['pa_'.$attribute['attribute_name']] = array(
				//Make sure the 'name' is same as you have the attribute
				'name'         => 'pa_'.$attribute['attribute_name'],
				'value'        => '',
				'position'     => $position++,
				'is_visible'   => 1,
				'is_variation' => 0,
				'is_taxonomy'  => 1
			);

   
   //$rows=$wpdb->get_results(" SELECT COUNT(*) as cc FROM $wpdb->termmeta WHERE term_id = '".$attribute3['term_id']."' AND meta_key='order_pa_".$attribute['attribute_name']."' ");
   $GLOBALS['inse_attr'];
   if (!isset($GLOBALS['inse_attr'][$attribute3['term_id']])) $GLOBALS['inse_attr'][$attribute3['term_id']]=0;
   $query="UPDATE $wpdb->term_taxonomy SET count='".(++$GLOBALS['inse_attr'][$attribute3['term_id']])."' WHERE term_id = '".$attribute3['term_id']."' AND taxonomy='pa_".$attribute['attribute_name']."' ";
   $wpdb->query( $query );  

   //print_r($Tax_ids);   
   //exit;
   } 
   else
   {
   
   $attributes[htmlspecialchars(stripslashes($name))] = array(
				'name'         => htmlspecialchars(stripslashes($name)),
				'value'        => $value,
				'position'     => 1,
				'is_visible'   => ( (($name!=='IDorig') and ($name!=='Offer')) ? 1: 0 ),
				'is_variation' => 1,
				'is_taxonomy'  => 0
			); 
    
   }
    
   
	}
  

	set_product_attributes($postId, $attributes);

  //print_r($params);
  //print_r($attributes);  
  //exit;
}

function set_product_attributes($post_id, $attributes)
{

	//Add as post meta
	add_post_meta($post_id, '_product_attributes', serialize($attributes));

}

/**
 * Подгрузка изображения
 * @param $url
 * @param $postId
 * @todo добавить обходной вариант на тот случай, если загрузка не удалась — просто запоминать урл на картинку
 * @todo Вынести наконец всё в красивый класс и закончить рефакторинг — запланировано на 7.06.2012
 */
function download_image($url, $postId)
{
	if (!IM_Config::init()->get('ps_download_images'))
	{
		$currentValue = get_post_meta($postId, 'image', TRUE);
		update_post_meta($postId, 'image', $url, $currentValue);
		return;
	}
	if (!CpaimpImport::checkCurl())
	{
		$opts = array(
			'http'=>array(
				'method'=>"GET",
				'header'=>"Accept-language: en\r\n"
			)
		);
		$context = stream_context_create($opts);
		$fileContents = file_get_contents($url, false, $context);
	}
	else
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$fileContents = curl_redirect_exec($ch);
		curl_close($ch);
	}
	if (!$fileContents)
	{
		echo 'При попытке загрузить файл '.$url.' возникла ошибка: '.
			"Содержимое файла не получено. Файл был присоединён старым способом\n\r";
		$currentValue = get_post_meta($postId, 'image', TRUE);
		update_post_meta($postId, 'image', $url, $currentValue);
		return;
	}
	$localFilepath = dirname(__FILE__).'/downloads/'.basename($url);
	$f = fopen($localFilepath, 'w');
	fwrite($f, $fileContents);
	fclose($f);
	/**
	 * Удаление не пользовательских аттачментов
	 */
	foreach(get_children(array(
			'post_parent' => $postId,
			'post_status' => 'inherit',
			'post_type' => 'attachment',
			'post_mime_type' => 'image',
			'numberposts' => -1,
		)) as $attachment)
	{
		if (get_post_meta($attachment->ID, 'is_image_from_cpaimp', TRUE))
		{
			wp_delete_attachment($attachment->ID, TRUE);
		}
	}
	$state = insert_attachment($localFilepath,$postId, true);
	if (is_wp_error($state))
	{
		echo 'При попытке загрузить файл '.$url.' возникла ошибка: '.$state->get_error_message().
				". Файл был присоединён старым способом\n\r";
		$currentValue = get_post_meta($postId, 'image', TRUE);
		update_post_meta($postId, 'image', $url, $currentValue);
	}
	else
	{
		add_post_meta($state, 'is_image_from_cpaimp', TRUE);
	}
	if (is_file($localFilepath))
		unlink($localFilepath);
}

/**
 * Вставка информации о изображении в базу
 * @param $image
 * @param $post_id
 * @param bool $setthumb
 * @return mixed
 */
function insert_attachment($image, $post_id, $setthumb = FALSE)
{
	require_once(ABSPATH . "wp-admin" . '/includes/image.php');
	require_once(ABSPATH . "wp-admin" . '/includes/file.php');
	require_once(ABSPATH . "wp-admin" . '/includes/media.php');

	$array = array( //array to mimic $_FILES
		'name' => basename($image), //isolates and outputs the file name from its absolute path
		'type' => 'image/jpeg', //yes, thats sloppy, see my text further down on this topic
		'tmp_name' => $image, //this field passes the actual path to the image
		'error' => 0, //normally, this is used to store an error, should the upload fail. but since this isnt actually an instance of $_FILES we can default it to zero here
		//'size' => filesize($image) //returns image filesize in bytes
	);
	$imageId = media_handle_sideload($array, $post_id);
	if ($setthumb)
		update_post_meta($post_id,'_thumbnail_id',$imageId);
	return $imageId;
}

function get_category_by_outer_id($outerId)
{
	global $wpdb;
	return $wpdb->get_row("SELECT * FROM {$wpdb->terms} WHERE  term_group = {$outerId}");
}

/*
	curl_exec which takes in account redirects

	Source http://stackoverflow.com/a/3890902/1194327
*/
function curl_redirect_exec($ch, $curlopt_header = false) {
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$data = curl_exec($ch);

	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if ($http_code == 301 || $http_code == 302) {
		list($header) = explode("\r\n\r\n", $data, 2);

		$matches = array();
		preg_match("/(Location:|URI:)[^(\n)]*/", $header, $matches);
		$url = trim(str_replace($matches[1], "", $matches[0]));

		$url_parsed = parse_url($url);
		if (isset($url_parsed)) {
			curl_setopt($ch, CURLOPT_URL, $url);
			return curl_redirect_exec($ch, $curlopt_header);
		}
	}

	if ($curlopt_header) {
		return $data;
	} else {
		list(, $body) = explode("\r\n\r\n", $data, 2);
		return $body;
	}
}
