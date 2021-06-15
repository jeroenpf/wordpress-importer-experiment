<?php

namespace ImporterExperiment\PartialImporters;

trait PostTrait {

	/**
	 * Get a post by meta wxr_id and import_id.
	 *
	 * If the post exists, it has already been processed during the current import run.
	 *
	 * @param $id
	 *
	 * @return int|null
	 */
	protected function get_post_id_by_wxr_id( $id ) {

		$posts = get_posts(
			array(
				'fields'     => 'ids',
				'meta_query' => array(
					array(
						'key'   => 'wxr_id',
						'value' => $id,
					),
					array(
						'key'   => 'import_id',
						'value' => $this->import->get_id(),
					),
				),
			)
		);

		return count( $posts ) ? $posts[0] : null;

	}

}
