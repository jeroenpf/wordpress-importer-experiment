<?php

namespace ImporterExperiment\Jobs;

use ImporterExperiment\Abstracts\Job;

/**
 * Class WXRImportJob
 *
 * A WXRImportJob gets a byte-range stored as term meta and reads the given byte-range from the
 * WXR file and parses and imports only that part.
 *
 * @package ImporterExperiment\Jobs
 */
class WXRImportJob extends Job {

	/**
	 * @param $job_meta
	 *
	 * @return false
	 *
	 * @todo Error handling, checking if the meta exists, etc.
	 */
	public function run( $job_meta ) {
		$job_meta = get_metadata_by_mid( 'term', $job_meta['meta_id'] );

		// Todo: Execute the job
		$term_id  = (int) $job_meta->term_id;
		$file     = get_term_meta( $term_id, 'file', true );
		$checksum = get_term_meta( $term_id, 'file_checksum', true );

		if ( md5_file( $file ) !== $checksum ) {
			return false;
		}

		//      $state = get_term_meta( $term_id, 'state', true );
		//      if ( ! $state ) {
		//          $state = array();
		//      }

		$job_data                  = maybe_unserialize( $job_meta->meta_value );
		$job_data['file']          = $file;
		$job_data['file_checksum'] = $checksum;

		//$state = apply_filters( 'wordpress_importer_job_' . $job_data['importer'], $state, $job_data );
		//update_term_meta( $term_id, 'state', $state );

		// Delete the job (meta)
		delete_metadata_by_mid( 'term', $job_meta->meta_id );

		$processed = get_term_meta( $term_id, 'processed', true ) ?: 0;
		update_term_meta( $term_id, 'processed', $processed + count( $job_data['objects'] ) );
	}

}
