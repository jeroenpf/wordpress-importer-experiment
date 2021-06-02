<?php

namespace ImporterExperiment\Interfaces;

use ImporterExperiment\Import;
use WP_Comment;

interface Job {

	public function __construct( Import $import, WP_Comment $stage_job);

	public function run();

	/**
	 * @return WP_Comment
	 */
	public function get_stage_job();

	/**
	 * Set the status of a job.
	 *
	 * @param $status
	 *
	 * @return null
	 */
	public function set_status( $status );

}
