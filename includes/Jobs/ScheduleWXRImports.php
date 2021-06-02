<?php

namespace ImporterExperiment;

use ImporterExperiment\Abstracts\Job;

class ScheduleWXRImports extends Job {


	protected $stages = array(
		'authors'    => array(
			'type'       => 'wp:author',
			'depends_on' => 'initialization',
		),
		'categories' => array(
			'type'       => 'wp:category',
			'depends_on' => 'initialization',
		),
		'terms'      => array(
			'type'       => 'wp:term',
			'depends_on' => 'initialization',
		),
		'tags'       => array(
			'type'       => 'wp:tag',
			'depends_on' => 'initialization',

		),
		'posts'      => array(
			'type'       => 'item',
			'depends_on' => array( 'initialization', 'authors', 'categories', 'terms', 'tags' ),
		),
	);

	public function run( $job_meta, ImportStage $stage = null ) {

		// Offset
		$offset = isset( $job_meta['offset'] ) ? $job_meta['offset'] : 0;




	}
}
