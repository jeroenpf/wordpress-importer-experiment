<?php

namespace ImporterExperiment;

use ActionScheduler;
use ActionScheduler_Store;

class Admin {

	const EXPORT_FILE_OPTION = 'importer_experiment_wxr_file';

	const TAXONOMY = 'importer_experiment';

	/**
	 * @var WXR_Indexer
	 */
	protected $indexer;

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

		$indexer = new WXR_Indexer();
		$indexer->parse( $file );

		$this->indexer = $indexer;

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

		$total_items += $this->batch( 'wp:author', $term_id );
		$total_items += $this->batch( 'wp:category', $term_id );
		$total_items += $this->batch( 'item', $term_id );

		update_term_meta( $term_id, 'total', $total_items );
		update_term_meta( $term_id, 'processed', 0 );

		echo 'Memory: ' . round( memory_get_peak_usage() / 1024 / 1024, 2 ) . "MB\n";

	}

	protected function batch( $type, $term_id, $batch_size = 100 ) {

		$batch      = array();
		$item_count = $this->indexer->get_count( 'item' );
		$job_count  = 0;

		foreach ( $this->indexer->get_data( $type ) as $idx => $item ) {
			$batch[] = $item;
			$job_count++;
			if ( $idx === $item_count - 1 || count( $batch ) === $batch_size ) {

				// Store the objects to process as term meta
				$meta_id = add_term_meta(
					$term_id,
					'job',
					array(
						'job'     => 'post',
						'objects' => $batch,
					)
				);

				$job_data = array(
					'type'    => $type,
					'meta_id' => $meta_id,
				);

				$this->create_job( $job_data );

				$batch = array();
			}
		}

		return $job_count;
	}

	protected function create_job( $payload ) {

		as_enqueue_async_action( 'wordpress_importer_experiment_run_job', $payload );
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

	public function run_jobs() {

		// Set the store


		apply_filters(
			'action_scheduler_store_class',
			function() {
				return ActionScheduler_Store::DEFAULT_CLASS;
			}
		);

		$processed_actions = ActionScheduler::runner()->run( 'ImporterExperiment' );

		wp_send_json(
			array(
				'processed_actions' => $processed_actions,
			)
		);

		exit();
	}


}
