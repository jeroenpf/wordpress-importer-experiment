<?php

namespace ImporterExperiment\StageJobs;

use ImporterExperiment\Abstracts\StageJob;


class OrphanedPosts extends StageJob {

	/**
	 * Attempt to associate posts and menu items with previously missing parents
	 *
	 * An imported post's parent may not have been imported when it was first created
	 * so try again. Similarly for child menu items and menu items which were missing
	 * the object (e.g. post) they represent in the menu
	 */
	public function run() {

		foreach ( $this->get_orphaned_posts() as $post ) {

			if ( ! $post->parent_id ) {
				$this->import->get_logger()->notice( sprintf( __( 'Backfilling the parent failed for orphaned post %d.' ), $post->post_id ) );
				continue;
			}

			if ( 'nav_menu_item' === $post->post_type ) {
				update_post_meta( $post->post_id, '_menu_item_menu_item_parent', (int) $post->parent_id );
			} else {
				global $wpdb;
				$wpdb->update( $wpdb->posts, array( 'post_parent' => $post->parent_id ), array( 'ID' => $post->post_id ), '%d', '%d' );
				clean_post_cache( $post->post_id );
			}
		}
	}

	protected function get_orphaned_posts() {
		global $wpdb;

		$sql = "select p.ID as post_id, p2.ID as parent_id, p.post_type from {$wpdb->posts} as p
				JOIN {$wpdb->postmeta} as pm on p.ID = pm.post_id
				JOIN {$wpdb->postmeta}  as pm2 on p.ID = pm2.post_id
				LEFT JOIN {$wpdb->posts} as p2 ON p2.ID = pm.meta_value
				where pm.meta_key = 'orphaned_wxr_id'
				  and pm2.meta_key = 'import_id'
				  and pm2.meta_value = %d";

		return $wpdb->get_results( $wpdb->prepare( $sql, $this->import->get_id() ) );

	}

}
