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


CpaimpImport::check_access();


$importer = Cpaimp_Importer::get_instance();

$importer->start();



die();



