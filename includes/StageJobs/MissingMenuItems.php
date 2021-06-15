<?php

namespace ImporterExperiment\StageJobs;

use ImporterExperiment\Abstracts\StageJob;
use ImporterExperiment\PartialImporters\MenuItemTrait;
use ImporterExperiment\PartialImporters\PostTrait;

class MissingMenuItems extends StageJob {

	use MenuItemTrait, PostTrait;

	public function run() {

		$items = get_post_meta( $this->import->get_id(), 'missing_menu_item' );

		foreach ( $items as $item ) {
			error_log( sprintf( 'XXX Handling missing item %d', $item['post_id'] ) );
			$this->process_menu_item( $item, false );
		}

		$this->import->delete_meta( 'missing_menu_item' );
	}
}
