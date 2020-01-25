<?php
header('Content-type: text/html; charset=utf-8');
define('PARSING_IS_RUNNING', TRUE);

ignore_user_abort(true);
set_time_limit(36000);
define('DOING_CRON', true);

/**
 * Определение констант
 */
if (!defined('IM_PLUGIN_PATH')) {
	define('IM_PLUGIN_PATH', dirname(__FILE__));
}

CpaimpImport::check_access();

$path = IM_PLUGIN_PATH.'/downloads';
try{
	file_put_contents ($path.'/test.txt', 'Hello File');
	@unlink($path.'/test.txt');
}catch (ErrorException $e ){
	die("Не хватает прав на запись в каталог $path . Выставьте нужные права и попробуйте еще раз.");
}

restore_error_handler();

global $wpdb;  
list($x1,$x2)=explode('.',strrev($_SERVER['HTTP_HOST']));
$xdomain=$x1.'.'.$x2;
$ff=strrev($xdomain).date("_Y-m-d_H-i").'.csv';
 
$plugins_url = plugins_url();
$cur_path = plugin_basename(__FILE__);
$plugin_name = str_replace('get_direct.php','',$cur_path);
$link=$plugins_url.'/'.$plugin_name.'downloads/'.$ff;
$filename=$path.'/'.$ff;
//print "$ff=$link=$filename";


$fn = fopen($filename, 'w+');

$ress=array();
$headers=array();
 
function getPostmeta($id,$param)
 {
 global $wpdb;
 $rows=$wpdb->get_results(" SELECT * FROM $wpdb->postmeta WHERE post_id='$id' AND meta_key='$param' ");
 return (isset($rows[0]->meta_value)) ? $rows[0]->meta_value : '';
 } 
 
function getCategoryId($id)
 {
 global $wpdb;
 $rows=$wpdb->get_results(" SELECT * FROM $wpdb->term_relationships WHERE object_id='$id' AND term_taxonomy_id >10 ");
 return (isset($rows[0]->term_taxonomy_id)) ? $rows[0]->term_taxonomy_id : '';
 }  
 
function GetAttributeStr($name)
 {
 global $wpdb;
 $rows=$wpdb->get_results(" SELECT * FROM $wpdb->options WHERE option_name='_transient_wc_attribute_taxonomies' ");
 $strs=unserialize($rows[0]->option_value);
 $res='';
 foreach ($strs as $str)
  {
  if ($str->attribute_name==$name) $res=$str->attribute_label;
  }
 return $res;
 } 
 
function getCategoryPath($id)
 {
 $cat=''; // Главная/Украшения из камня/Браслеты/Рутиловый кварц
 global $wpdb;
 $rows=$wpdb->get_results(" SELECT * FROM $wpdb->term_relationships WHERE object_id='$id' AND term_taxonomy_id >10 ");
 $parent=$rows[0]->term_taxonomy_id;
 while ($parent>0)
  {
  $rows2=$wpdb->get_results(" SELECT * FROM $wpdb->terms WHERE term_id='".$parent."' ");
  $rows3=$wpdb->get_results(" SELECT * FROM $wpdb->term_taxonomy WHERE term_id='".$parent."' ");
  if (isset($rows2[0]->name)) $cat='/'.$rows2[0]->name.$cat;
  if (isset($rows3[0]->parent)) $parent=$rows3[0]->parent; else $parent=0;
  }
 return ($cat!=='') ? 'Главная'.$cat : '';
 }  
 
function getTerms($id)
 {
 global $wpdb;
 $Terms=array();
 $rows=$wpdb->get_results(" SELECT * FROM $wpdb->term_relationships WHERE object_id='$id' AND term_taxonomy_id >10 ");
 foreach ($rows as $row)
  {
  $rows2=$wpdb->get_results(" SELECT * FROM $wpdb->terms WHERE term_id='".$row->term_taxonomy_id."'");
  $rows3=$wpdb->get_results(" SELECT * FROM $wpdb->term_taxonomy WHERE term_id='".$row->term_taxonomy_id."' ");

  if ((isset($rows2[0]->name)) and (isset($rows3[0]->taxonomy)) )
   $Terms[$rows3[0]->taxonomy]=$rows2[0]->name;
  }
 //print_r($Terms);
 return $Terms;
 }   
 
  $rows=$wpdb->get_results(" SELECT * FROM $wpdb->posts WHERE post_type = 'product' ");
  foreach ($rows as $row)
   {

  $res=array(
   'id' => $row->ID,
   'categoryId' => getCategoryId($row->ID),
   'category' => getCategoryPath($row->ID),
   'name' => $row->post_title,
   'description' => $row->post_content,
   'picture' => getPostmeta($row->ID,'image'),
   'price' => getPostmeta($row->ID,'_price'),
   'url' => getPostmeta($row->ID,'_product_url'),
   'urls' => $row->guid,
  );
  
  
  $attrs=unserialize(unserialize(getPostmeta($row->ID,'_product_attributes')));
  $terms=getTerms($row->ID);

  $num=1;
  foreach ($attrs as $attr)
   {
   if (substr($attr['name'],0,3)=='pa_')
    {
    $attr['value']=(isset($terms[$attr['name']]) ? $terms[$attr['name']] : '');
    $attr['name']=GetAttributeStr(substr($attr['name'],3));
    $num++;
    }
   $res[$attr['name']]=$attr['value'];
   }     
 
  foreach ($res as $key=>$value)
   {
   if (!in_array($key,$headers)) $headers[]=$key;
   }   
    
  $ress[]=$res; 

  
  //print_r($ress);   
   
   
  // exit;
   }

function cleartext($text)
 {
 $text=iconv("UTF-8", "windows-1251//IGNORE", $text);
 $text=htmlspecialchars_decode($text);
 $text=str_replace(';','_',$text);
 return $text;
 }   

foreach ($headers as $header) { fwrite($fn, cleartext($header).';'); } fwrite($fn,"\r\n");  
foreach ($ress as $res)
 {
 foreach ($headers as $header) { fwrite($fn, (isset($res[$header]) ? cleartext($res[$header]) : '').';'); } fwrite($fn,"\r\n"); 
 }

 

 
 
 
 
 
 
 
 
fclose($fn);

/* Распаковка архива */
/*
WP_Filesystem();
if ($status = unzip_file($path.'/archive.zip', $path) !== TRUE)
{
	die('Ошибка при распаковке архива. Данные об ошибке
	PclZip — Code: '.$status->get_error_code().'; Message: '.$status->get_error_message($status->get_error_code()));
}


$xmlfile = '';
$dh = opendir($path);
while ($file = readdir($dh)) {
	if (strpos($file, '.xml') !== false) {
		$xmlfile = $file;
		break;
	}
}
closedir($dh);

if (empty($xmlfile)) {
	echo 'Не удалось получить выгрузку.';
	exit;
}


$xmlFileFullPath = $path.'/'.$xmlfile;
//$f = fopen($xmlFileFullPath, 'r');


// load the document
$info_nocdata = simplexml_load_file($xmlFileFullPath, 'SimpleXMLElement', LIBXML_NOCDATA);
$info = simplexml_load_file($xmlFileFullPath);

// update
$info->shop->name = get_bloginfo();
$info->shop->company = get_bloginfo();
$info->shop->url = get_site_url();

$i = 0;

foreach($info->shop->offers->offer as $key => $value)
{
	global $wpdb;
	$post = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s", $info_nocdata->shop->offers->offer[$i]->name[0] ));
	if (!$post)
	{
		$i++;
		continue;
	}

	$post_id = get_post($post)->ID;
	$value->url = get_permalink($post_id);

	if(IM_Config::init()->get('ps_download_images')):
		$thumbnail_id = get_post_thumbnail_id($post_id);
		$image_full = wp_get_attachment_image_src($thumbnail_id, 'full');
		$image_thumbnail = wp_get_attachment_image_src($thumbnail_id);
		$value->thumbnail = $image_thumbnail[0];
		$value->picture = $image_full[0];
	endif;

	unset($value->original_picture);
	unset($value->attributes()->im_category_id);
	$i++;
}


// save the updated document
$info->asXML(IM_PLUGIN_PATH.'/direct.xml');
copy(IM_PLUGIN_PATH.'/direct.xml', IM_PLUGIN_PATH.'/downloads/direct.xml');

$plugins_url = plugins_url();
$cur_path = plugin_basename(__FILE__);
$plugin_name = str_replace('get_direct.php','',$cur_path);
echo $plugins_url.'/'.$plugin_name.'direct.xml';

*/

print '<a href="'.$link.'">download export file</a>';

?>
