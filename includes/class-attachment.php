<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Cpaimp_Attachment' ) ) {

	class Cpaimp_Attachment {

		public $id;
		private $file_path;
		private $parent_post_id = 0;

		function __construct($file_path, $parent_post_id) {
			$this->file_path = $file_path;
			$this->parent_post_id = $parent_post_id;
		}

		public function insert() {
			require_once(ABSPATH . "wp-admin" . '/includes/image.php');
			require_once(ABSPATH . "wp-admin" . '/includes/file.php');
			require_once(ABSPATH . "wp-admin" . '/includes/media.php');

			$file = array(
				'name' => basename( $this->file_path ),
				'type' => 'image/jpeg',
				'tmp_name' => $this->file_path,
				'error' => 0
			);

			$img_id = media_handle_sideload( $file, $this->parent_post_id );
			if ( $img_id && ! is_wp_error( $img_id ) ) {
				$this->id = $img_id;
				// Used until post attachments deletion
				add_post_meta( $this->id, 'is_image_from_cpaimp', true );
				return true;
			}

			return false;
		}

		public function set_as_thumbnail() {
			if ( $this->id ) {
				update_post_meta( $this->parent_post_id, '_thumbnail_id', $this->id );
			}
		}
	}
}