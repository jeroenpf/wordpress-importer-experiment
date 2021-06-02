<?php

namespace ImporterExperiment\Abstracts;

use ImporterExperiment\Import;
use ImporterExperiment\ImportStage;
use ImporterExperiment\Interfaces\Job as JobInterface;
use WP_Comment;

abstract class Job implements JobInterface {

	const STATUS_PENDING = 'pending';
	const STATUS_RUNNING = 'running';
	const STATUS_DONE    = 'completed';

	/**
	 * @var array
	 */
	protected $arguments = array();

	/**
	 * @var Import
	 */
	protected $import;

	/**
	 * @var WP_Comment
	 */
	protected $stage_job;

	public function __construct( Import $import, WP_Comment $stage_job ) {
		$this->import    = $import;
		$this->stage_job = $stage_job;
		$this->arguments = get_comment_meta( $stage_job->comment_ID, 'job_arguments', true );
	}

	public function set_status( $status ) {
		update_comment_meta( $this->stage_job->comment_ID, 'status', $status );
	}


	public function get_stage_job() {
		return $this->stage_job;
	}

	/**
	 * Get the current stage the job is running in.
	 *
	 * @return ImportStage
	 */
	public function get_stage() {
		return ImportStage::get_by_id( $this->stage_job->comment_parent, $this->import );
	}

}
