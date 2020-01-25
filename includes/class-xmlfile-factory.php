<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Cpaimp_XMLFile_Factory' ) ) {

	class Cpaimp_XMLFile_Factory {

		public function get_file($filepath = '') {
			if ( $filepath ) {
				return new Cpaimp_XMLFile( $filepath );
			}

			if ( isset( $_GET['xml'] ) ) {
				return new Cpaimp_XMLFile( $_GET['xml'] );
			}

			$dh = opendir( Cpaimp_Importer::$import_path );
			while ( $filename = readdir( $dh ) ) {
				if ( false !== strpos( $filename, '.xml' ) ) {
					closedir( $dh );
					return new Cpaimp_XMLFile( Cpaimp_Importer::$import_path . $filename );
				}
			}
			closedir( $dh );

			throw new Exception( 'Error during XML file creation!', 20 );
		}
	}
}