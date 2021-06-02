<?php

namespace ImporterExperiment\Jobs;

use Exception;
use ImporterExperiment\Abstracts\Job;

class SortUrlMapping extends Job {


	/**
	 * This job sorts the attachment urls that need to be replaced by their length.
	 *
	 * We want to make sure the longest urls are processed first, in case
	 * one is a substring of another.
	 *
	 * @throws Exception
	 */
	public function run() {
		$remap = $this->import->get_meta( 'import_url_remap_from' );

		if ( empty( $remap ) ) {
			return;
		}

		uksort( $remap, array( $this, 'cmp_strlen' ) );

		$this->import->set_meta( 'import_url_remap_from', $remap );

		// Add a job to process the remapping of attachment urls.
		$this->get_stage()->add_job( AttachmentUrlMapJob::class );
	}

	protected function cmp_strlen( $a, $b ) {
		return strlen( $b ) - strlen( $a );
	}
}
