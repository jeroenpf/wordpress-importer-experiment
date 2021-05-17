<?php

namespace ImporterExperiment;


class Admin {

	const EXPORT_FILE_OPTION = 'importer_experiment_wxr_file';

	public function run() {

		$action = isset( $_GET['action'] ) ? $_GET['action'] : null;

		switch ( $action ) {

			case 'status':
				// If post, handle upload

				/**
				 * todo Make sure that the uploaded WXR file is the same as the one used in the jobs...
				 */

				if ( ! empty( $_FILES ) ) {
					$this->upload();
				}

				$this->create_jobs();

				include __DIR__ . '/../partials/status.php';

				break;

			default:
				include __DIR__ . '/../partials/start.php';
				break;

		}

	}

	/**
	 * todo Validate uploaded file and make sure it is a WXR.
	 */
	protected function upload() {
		check_admin_referer( 'import-upload' );
		$file = wp_import_handle_upload();

		update_option( self::EXPORT_FILE_OPTION, $file['id'] );
	}

	/**
	 * todo validate that the uploaded file is a WXR
	 */
	protected function create_jobs() {
		$file = get_attached_file( get_option( self::EXPORT_FILE_OPTION ) );
		require_once( __DIR__ . '/xml_indexer.php' );
		$indexer = new WXR_Indexer();
		$start   = microtime( true );
		$indexer->parse( $file );
		$end = microtime( true );
		echo 'Time: ' . round( $end - $start, 2 ) . "s\n";

		$taxonomy = 'importer_experiment';

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( count( $terms ) ) {
			$term_id = $terms[0]->term_id;
			delete_term_meta( $term_id, 'job' );
			delete_term_meta( $term_id, 'file' );
			delete_term_meta( $term_id, 'file_checksum' );
		} else {
			$term    = wp_insert_term( 'jobs', $taxonomy );
			$term_id = $term['term_id'];
		}

		add_term_meta( $term_id, 'file', $file );
		add_term_meta( $term_id, 'file_checksum', md5_file( $file ) );

		foreach ( $indexer->get_data( 'wp:author' ) as $item ) {

			$payload = array(
				'objects' => $item,
				'job'     => 'author',
			);

			add_metadata( 'term', $term_id, 'job', $payload );
		}

		$batch_size = 100;
		$batch      = array();
		foreach ( $indexer->get_data( 'item' ) as $idx => $item ) {
			$batch[] = $item;
			if ( $idx === $indexer->get_count( 'item' ) - 1 || ( 0 === $idx % $batch_size && count( $batch ) ) ) {
				add_term_meta(
					$term_id,
					'job',
					array(
						'job'     => 'post',
						'objects' => $batch,
					)
				);
				$batch = array();
			}
		}

		echo 'Memory: ' . round( memory_get_peak_usage() / 1024 / 1024, 2 ) . "MB\n";

	}


}
