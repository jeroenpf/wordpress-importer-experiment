<?php

namespace ImporterExperiment;

class Job_Runner {

	public function __construct() {

	}

	public function run() {

		// get the latest job
		$terms = get_terms(
			array(
				'taxonomy'   => 'importer_experiment',
				'hide_empty' => false,
			)
		);

		if ( ! count( $terms ) ) {
			return false;
		}

		/** @var \WP_Term $term */
		$term = $terms[0];

		$file     = get_term_meta( $term->term_id, 'file', true );
		$checksum = get_term_meta( $term->term_id, 'file_checksum', true );

		if ( md5_file( $file ) !== $checksum ) {
			return false;
		}

		global $wpdb;

		// We can't use the get_term_meta function because we need to get the meta_id for later use.
		$sql  = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}termmeta WHERE term_id = %d AND meta_key = %s ORDER BY meta_id ASC LIMIT 1", $term->term_id, 'job' );
		$jobs = $wpdb->get_results( $sql ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! count( $jobs ) ) {
			return false;
		}

		$job      = $jobs[0];
		$job_data = maybe_unserialize( $job->meta_value );

		// Todo: Execute the job


		// Delete the job (meta)
		delete_metadata_by_mid( 'term', $job->meta_id );

		$processed = get_term_meta( $term->term_id, 'processed', true ) ?: 0;
		update_term_meta( $term->term_id, 'processed', $processed + count($job_data['objects']) );

		// schedule next event if there is a next job.
		if ( get_term_meta( $term->term_id, 'job', true ) ) {
			$this->schedule_next();
		} else {
			// Todo: mark the import as done, do things that we need to do after all items have imported...
		}
	}

	public function schedule_next() {
		// Schedule next event if there are more jobs...

		_wp_batch_split_terms();

		wp_schedule_single_event(
			time(),
			'run_wordpress_importer'
		);

	}

}
