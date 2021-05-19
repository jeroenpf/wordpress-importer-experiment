<?php

namespace ImporterExperiment;


class Admin {

	const EXPORT_FILE_OPTION = 'importer_experiment_wxr_file';

	const TAXONOMY = 'importer_experiment';

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
		$indexer->parse( $file );

		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);

		if ( count( $terms ) ) {
			$term_id = $terms[0]->term_id;
			delete_term_meta( $term_id, 'job' );
			delete_term_meta( $term_id, 'file' );
			delete_term_meta( $term_id, 'file_checksum' );
		} else {
			$term    = wp_insert_term( 'jobs', self::TAXONOMY );
			$term_id = $term['term_id'];
		}

		add_term_meta( $term_id, 'file', $file );
		add_term_meta( $term_id, 'file_checksum', md5_file( $file ) );

		$total_items = 0;

		foreach ( $indexer->get_data( 'wp:author' ) as $item ) {

			$payload = array(
				'objects' => array( $item ),
				'job'     => 'author',
			);

			$total_items++;
			add_metadata( 'term', $term_id, 'job', $payload );
		}

		$batch_size = 100;
		$batch      = array();
		$item_count = $indexer->get_count( 'item' );
		// todo: attachment post types need to be added later because they need to run after other post types
		foreach ( $indexer->get_data( 'item' ) as $idx => $item ) {
			$batch[] = $item;
			$total_items++;
			if ( $idx === $item_count - 1 || count( $batch ) === $batch_size ) {
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

		update_term_meta( $term_id, 'total', $total_items );
		update_term_meta( $term_id, 'processed', 0 );

		echo 'Memory: ' . round( memory_get_peak_usage() / 1024 / 1024, 2 ) . "MB\n";

		require_once( __DIR__ . '/job_runner.php' );

		$runner = new Job_Runner();

		// Schedule the next job to be executed.
		$runner->schedule_next();

	}

	/**
	 * Get the import status
	 *
	 * @return array
	 */
	public function get_status() {

		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);

		if ( ! count( $terms ) ) {
			wp_send_json(
				array(
					'status' => 'uninitialized',
				)
			);
			exit();
		}

		$term = $terms[0];

		$total     = get_term_meta( $term->term_id, 'total', true );
		$processed = get_term_meta( $term->term_id, 'processed', true );

		wp_send_json(
			array(
				'status'    => 'running',
				'total'     => $total,
				'processed' => $processed,
			)
		);
		exit();
	}


}
