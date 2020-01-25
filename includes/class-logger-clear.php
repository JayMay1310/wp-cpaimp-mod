<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Cpaimp_Logger_Clear' ) ) {

	class Cpaimp_Logger_Clear	{
		protected static $_instance = null;

		private $fh;
		private $enabled = true;

		private function __construct() { }
	    private function __clone() { }

	    public static function get_instance() {
	        if ( null === self::$_instance ) {
	            self::$_instance = new self();
	            self::$_instance->init();
	            register_shutdown_function( [self::$_instance, 'finalize'] );
	        }

	        return self::$_instance;
	    }

	    public function init() {
	    	$ds = DIRECTORY_SEPARATOR;
	    	$dir_path = IM_PLUGIN_PATH . "{$ds}log_clear";
	    	if ( ! is_dir( $dir_path ) ) {
	    		$this->enabled = mkdir( $dir_path );
	    	}

	    	if ( $this->enabled ) {
	    		$file_name = 'log-' . date( 'Y-m-d_H-i-s' ) . '.txt';
		    	$file_path = "{$dir_path}{$ds}{$file_name}";
		    	$this->fh = fopen( $file_path, 'a' );
	    	}
	    }

	    public function log($message) {
	    	if ( $this->fh ) {
	    		fwrite( $this->fh , $message . PHP_EOL );
	    	}
	    }

	    public function finalize() {
	    	if ( $this->fh ) {
	    		fclose( $this->fh );
	    	}
	    }
	}
}