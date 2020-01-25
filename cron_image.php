<?php

header( 'Content-type: text/html; charset=utf-8' );

ignore_user_abort( true );
set_time_limit( 50000 );

ini_set( 'display_errors', 1 );
ini_set( 'error_reporting', E_ALL );

define( 'PARSING_IS_RUNNING', true );
define( 'DOING_CRON', true );
if ( ! defined( 'IM_PLUGIN_PATH' ) ) {
  define( 'IM_PLUGIN_PATH', dirname( __FILE__ ) );
}

$path_parts = pathinfo($_SERVER['SCRIPT_FILENAME']); 
chdir($path_parts['dirname']);

require_once '../../../wp-load.php'; 
require_once 'config.php';


    $args     = array( 'post_type' => 'product', 'numberposts' => -1);
    $products = get_posts( $args );
    foreach($products as $key => $value) {
        $image_url = get_post_meta($value->ID, 'image', true);

		$url = get_post_meta($value->ID, 'url', true);
		if (empty($url))
		{
			continue;
		}

        if (empty($image_url))
		{
			continue;
		}
		//Проверка на загрузку изображений
		$check_download_image = get_post_meta($value->ID, 'check_download_image', true);
		if ($check_download_image == 1)
		{
			continue;
		}

		$image_url = str_replace("http://tvoe.ru/", "https://tvoe.ru/", $image_url);
		
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $image_url );
		curl_setopt( $ch, CURLOPT_HEADER, false );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);
		$fileContents = curl_redirect_exec( $ch );
		curl_close( $ch );
			

		if ( ! $fileContents ) {
			//ImpLogger()->log( 'При попытке загрузить файл ' . $this->params['image'] . ' возникла ошибка: ' .
			//	"Содержимое файла не получено. Файл был присоединён старым способом" );

			update_post_meta( $value->ID, 'image', $image_url );
			return;
		}

			$localFilepath = dirname( __FILE__ ) . '/downloads/' . basename( $image_url );
			
			if ( $fileContents && ( $f = fopen( $localFilepath, 'w' ) ) && fwrite( $f, $fileContents ) ) {
				fclose( $f );

				// Удаление не пользовательских аттачментов
				$children = get_children( [
					'post_parent' => $value->ID,
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

				$attachment = new Cpaimp_Attachment( $localFilepath, $value->ID );
				if ( $attachment->insert() ) {
					$attachment->set_as_thumbnail();
				}
				else {
					ImpLogger()->log( 'Ошибка при установки миниатюры продукта' . $value->ID . 'Изображение' . $image_url );
					update_post_meta( $value->ID, 'image', $image_url );
				}

				if ( is_file( $localFilepath ) ) {
					unlink( $localFilepath );
				}

				update_post_meta( $value->ID, 'check_download_image', 1, false );
			}
			else {
				ImpLogger()->log( '[FILE: ' . $image_url . '] Unable to write file to filesystem or file content is empty ' . $value->ID );
				ImpLogger()->log( '$localFilepath: ' . $localFilepath );
			}
                          
    }



