<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Cpaimp_Importer' ) ) {
	class Cpaimp_Importer {

		protected static $_instance = null;
		private $config = [];
		public static $import_path;
		public static $vendor = '';
		public static $vendor_id;

		private function __construct() { }
	    private function __clone() { }

	    public static function get_instance() {
	        if ( null === self::$_instance ) {
	            self::$_instance = new self();
	            self::$_instance->init_config();
	            self::$_instance->init_vendor();
	        }

	        return self::$_instance;
	    }

	    public function init_config() {
	    	if ( isset( $_GET['cfg'] ) ) {
	    		$config_path = IM_PLUGIN_PATH . DIRECTORY_SEPARATOR . $_GET['cfg'];
	    		if ( file_exists( $config_path ) ) {
	    			$config = require( $config_path );

	    			if ( isset( $config['path'] ) ) {
	    				self::$import_path = IM_PLUGIN_PATH . '/import/' . $config['path'];
	    				unset( $config['path'] );
	    			}

	    			foreach ( array_keys( $config ) as $key ) {
	    				$this->config[$key] = isset( $_GET[$key] ) ? $_GET[$key] : $config[$key];
	    			}
	    		}
			}
	    }

	    public function init_vendor() {

	    	if ( ! $this->config( 'offer' ) ) {
	    		throw new Exception( 'Vendor is empty!', 30 );
	    	}

	    	self::$vendor = $this->config( 'offer' );

	    	self::$vendor_id = ImpDB()->get_vendor_id_by_slug( self::$vendor );
	    	if ( ! self::$vendor_id ) {
	    		self::$vendor_id = ImpDB()->add_vendor( self::$vendor );
	    	}
	    	$this->config['vendor_id'] = self::$vendor_id;
	    }

	    public function config($key = '') {
	    	if ( $key && array_key_exists( $key, $this->config ) ) {
	    		return $this->config[$key];
	    	}

	    	return '';
	    }

	    public function start() {
	    	$time_start = time();

	    	ImpLogger()->log( 'Started at: ' . date( 'Y-m-d H:i:s', $time_start ) );
	    	ImpLogger()->log( 'Parameters: ' . print_r( $this->config, true ) );

	    	$xmlfile_factory = new Cpaimp_XMLFile_Factory();
	    	try {
				$xml_file = $xmlfile_factory->get_file();

	    		$xml_file->process_coupon_xml();
	    		$xml_file->parse_categories();	    		

	    		$time_category = time();

	    		ImpLogger()->log( 'Categories time: ' . ( $time_category - $time_start ) . 's' );
	    		ImpLogger()->log( 'Products started at: ' . date( 'Y-m-d H:i:s', $time_category ) );

	    		$xml_file->process_species();
	    		$xml_file->parse_goods();

	    		$xml_file->finalize();
	    		unset( $xml_file );

	    		$time_products = time();
	    		ImpLogger()->log( 'Products time: ' . ( $time_products - $time_category ) . 's' );

	    		ImpDB()->delete_old_products( self::$vendor_id );
	    		ImpDB()->delete_old_terms( self::$vendor_id );	    		
	    		ImpDB()->delete_empty_attributes( self::$vendor_id );

	    		// Do we need to delete empty categories after import
	    		if ($this->config('ps_delete_cats')) {
	    			ImpDB()->delete_all_empty_terms( self::$vendor_id );
	    		}
	    	}
	    	catch (Exception $e) {
	    		ImpLogger()->log( '[' . $e->getCode() . '] ' . $e->getMessage() );
	    	}

	    	$time_end = time();

	    	ImpLogger()->log( 'Finished at: ' . date( 'Y-m-d H:i:s', $time_end ) );
	    	ImpLogger()->log( 'Elapsed time: ' . ( $time_end - $time_start ) . 's' );
	    }
	}
}