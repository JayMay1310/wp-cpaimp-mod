<?php
/*
Plugin Name: wp-cpaimp
Version: 2.0.0
Author: Cpaimp
*/

date_default_timezone_set( 'Europe/Moscow' );

/**
 * Определение констант
 */
define('IM_PLUGIN_URL', get_bloginfo('url').'/wp-content/plugins/'.basename(dirname(__FILE__)).'/');
if (!defined('IM_PLUGIN_PATH')) {
	define('IM_PLUGIN_PATH', dirname(__FILE__));
}

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if(!is_plugin_active('woocommerce/woocommerce.php')){
	function my_admin_notice() {
		?>
		<div class="error">
			<p><?php _e('Плагин Cpaimp Affiliate Shop зависит от плагина Woocommerce! Пожалуйста установите плагин woocommerce!', 'error-woocommerce-require'); ?></p>
		</div>
	<?php
	}
	add_action( 'admin_notices', 'my_admin_notice' );
	function deactivate_plugin_conditional() {
		deactivate_plugins('wp-cpaimp/cpaimp.php');
		remove_menu_page('wp-cpaimp');
	}
	add_action( 'admin_init', 'deactivate_plugin_conditional' );
}
require_once(IM_PLUGIN_PATH.'/config.php');
register_activation_hook(__FILE__, array(IM_Config::init(), 'activate'));
register_deactivation_hook(__FILE__, array(IM_Config::init(), 'deactivate'));
require_once(IM_PLUGIN_PATH.'/options-controller.php');
require_once(IM_PLUGIN_PATH.'/tools.php');
require_once(IM_PLUGIN_PATH.'/posts.php');
/**
 * Вывод стилей
 */
add_action("wp_head", "psStyles", 100);

//*****************************************************************************************************

add_action("admin_menu", "psAdminPage", 8);

function psAdminPage()
{
	add_menu_page('Cpaimp', 'Cpaimp', 'edit_pages', 'wp-cpaimp', array(IM_Options_Controller::init(), 'render'));
	add_submenu_page(__FILE__, 'Настройки', 'Настройки', 'edit_pages', 'wp-cpaimp', array(IM_Options_Controller::init(), 'render'));
//	add_submenu_page('wp-cpaimp', 'Каталог', 'Каталог', 'edit_pages', 'edit.php?post_type=ps_catalog');
//	add_submenu_page('wp-cpaimp', 'Категории', 'Категории', 'edit_pages', 'edit-tags.php?taxonomy=ps_category');
}

function psStyles()
{
	echo '<style type="text/css">';
	echo '.products-list { width: 100%; border-spacing: 10px;} ';
	echo '.products-list TD { border: 1px solid #aaa; padding: 4px; vertical-align: top; width: '.round(100 / max(1, IM_Config::init()->get('ps_row_limit'))).'%; } ';
	echo '.products-price { font-weight: bold; } ';
	echo '.products-description { font-size: 0.9em; text-align: left; color: #777; }';
	echo '.product-table .product-image img { max-width: none; }';
	echo '.product-table tr, .product-table td{ border:0; }';
	echo '</style>';
}

/**
 * Скачивание yandex-direct
 */
add_action( 'wp_ajax_get_direct', 'get_direct' );
add_action( 'wp_ajax_nopriv_get_direct', 'get_direct' );
function get_direct()
{
	require_once(IM_PLUGIN_PATH.'/get_direct.php');
	die;
}

/**
 * Выкачка данных из выгрузки
 */
add_action( 'wp_ajax_parse_url', 'ajax_parse_url' );
add_action( 'wp_ajax_nopriv_parse_url', 'ajax_parse_url' );
function ajax_parse_url()
{
	require_once(IM_PLUGIN_PATH.'/cron.php');
	die;
}


require_once 'woocommerce.php';
Cpaimp_Woocommerce::init();





spl_autoload_register( function($class_name) {
	if ( false !== strstr( strtolower( $class_name ), 'cpaimp_' ) ) {
		$class = str_replace( ['cpaimp_', '_'], ['', '-'], strtolower( $class_name ) );
		$filename = "class-{$class}.php";
		$filepath = IM_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . $filename;
		if ( file_exists( $filepath ) ) {
			require_once $filepath;
		}
	}
} );

function ImpDB() {
	return Cpaimp_DB::get_instance();
}

function ImpLogger() {
	return Cpaimp_Logger::get_instance();
}
function ImpLoggerClear() {
	return Cpaimp_Logger_Clear::get_instance();
}

register_activation_hook( __FILE__, function() {
	ImpDB()->create_tables();
} );