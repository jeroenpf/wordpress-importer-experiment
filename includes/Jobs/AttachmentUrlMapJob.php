<?php

namespace ImporterExperiment\Jobs;

use ImporterExperiment\Abstracts\Job;
use ImporterExperiment\PartialImporters\Post;

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

		sleep( 5 );

		// A limit of attachments to handle per job execution to prevent timeouts on certain environments.
		$limit = apply_filters( 'importer_attachment_remap_limit', 100 );

		$remap_urls = $this->get_remap_urls( $limit );

		if ( empty( $remap_urls ) ) {
			return;
		}

		$this->remap_urls( $remap_urls );

		// If the number of fetched remap url is equal to the limit, we add another job to see if there
		// are more urls to process.
		if ( count( $remap_urls ) === $limit ) {
			$this->add_next_job();
		}
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
	 * Get url remapping.
	 *
	 * Fetches $limit number of url remap comments from the database.
	 *
	 * @param int $limit The number of url remapping to fetch.
	 *
	 * @return array An array of old => new urls.
	 */
	protected function get_remap_urls( $limit = 50 ) {

		global $wpdb;

		$query = "SELECT c.comment_ID, c.comment_content, cm.meta_value
					FROM {$wpdb->comments} AS c
					JOIN {$wpdb->commentmeta} as cm ON cm.comment_id = c.comment_ID
					WHERE c.comment_post_ID = %d AND cm.meta_key = 'remap_to' AND c.comment_type = %s
					ORDER BY LENGTH(c.comment_content) DESC
					LIMIT %d";

		$results = $wpdb->get_results( $wpdb->prepare( $query, $this->import->get_id(), Post::REMAP_COMMENT_TYPE, $limit ) );

		$remap_urls = array();

		foreach ( $results as $result ) {
			$remap_urls[ $result->comment_content ] = $result->meta_value;
			wp_delete_comment( $result->comment_ID, true );
		}

		return $remap_urls;
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
