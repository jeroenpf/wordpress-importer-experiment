<?php

namespace ImporterExperiment\StageJobs;

use ImporterExperiment\Abstracts\StageJob;

class FeaturedImageMap extends StageJob {


	public function run() {

		// Get posts with featured image and the new id
		// in most cases the new id and the existing ID are the same and nothing
		// needs to happen

		global $wpdb;

		// This query retrieves all the _thumbnail_id post meta for the current import and
		// joins the attachment by WXR id.
		$query = "SELECT pm.post_id, pm3.post_id as actual_attachment_id, pm.meta_value as wxr_attachment_id
			      FROM {$wpdb->postmeta} pm
				  JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id
				  JOIN {$wpdb->postmeta} pm3 ON pm3.meta_key = 'wxr_id' and pm3.meta_value = pm.meta_value
				  JOIN {$wpdb->postmeta} pm4 ON pm4.post_id = pm3.post_id and pm4.meta_key = 'import_id' and pm4.meta_value = %d
				  WHERE pm.meta_key = '_thumbnail_id' and pm2.meta_key = 'import_id' and pm2.meta_value = %d";

		$results = $wpdb->get_results( $wpdb->prepare( $query, $this->import->get_id(), $this->import->get_id() ) );
		foreach ( $results as $result ) {

			// If the ID of the attachment's post ID is the same as the wxr_id
			// nothing needs to be done. (it means the attachment ID was imported
			// using the same ID as in the WXR).
			if ( $result->actual_attachment_id === $result->wxr_attachment_id ) {
				continue;
			}

			// If the ID of the attachment is not the same as the WXR id, we need to update the _thumbnail_id
			// so that it points to the correct attachment. (the attachment was added with an ID different than
			// the id in the WXR).
			update_post_meta( $result->post_id, '_thumbnail_id', $result->actual_attachment_id, $result->wxr_attachment_id );
		}
	}
}
