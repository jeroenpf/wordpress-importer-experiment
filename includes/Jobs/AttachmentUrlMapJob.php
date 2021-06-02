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
class AttachmentUrlMapJob extends Job {


	/**
	 * Remap urls of attachments added during the current import.
	 *
	 * post_content still contains original URLs that need to be replaced with the new attachment urls.
	 *
	 * @throws \Exception
	 */
	public function run() {

		// A limit of attachments to handle per job execution to prevent timeouts on certain environments.
		$limit = apply_filters( 'importer_attachment_remap_limit', 250 );

		$remap = $this->import->get_meta( 'import_url_remap_from' );

		if ( empty( $remap ) ) {
			return;
		}

		$this->remap_urls( array_splice( $remap, 0, $limit ) );

		if ( ! count( $remap ) ) {
			$this->import->delete_meta( 'import_url_remap_from' );
			return;
		}

		$this->import->set_meta( 'import_url_remap_from', $remap );
		$this->add_next_job();
	}

	/**
	 * Remap urls in the post content from original to new urls.
	 *
	 * @param array $map An array which keys are the original URL and the values are the urls to remap to.
	 */
	protected function remap_urls( $map ) {
		global $wpdb;

		foreach ( $map as $from => $to ) {

			// remap urls in post_content
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)", $from, $to ) );

			// remap enclosure urls
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_key='enclosure'", $from, $to ) );
		}
	}

	/**
	 * If there are more attachment URLs to remap, schedule another job.
	 *
	 * @throws \Exception
	 */
	protected function add_next_job() {
		$this->get_stage()->add_job( static::class );
	}
}
