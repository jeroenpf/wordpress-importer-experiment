<?php

namespace ImporterExperiment;

use ImporterExperiment\Abstracts\JobRunner as JobRunnerAbstract;
use ImporterExperiment\Abstracts\Scheduler;
use ImporterExperiment\Interfaces\Job;
use ImporterExperiment\Abstracts\Job as JobAbstract;
use WP_Comment;

class JobRunner extends JobRunnerAbstract {

	const ACTION_HOOK = 'importer_experiment_run_job';

	/**
	 * @var Job
	 */
	protected $job;

	/**
	 * @var Import
	 */
	protected $import;

	/**
	 * @var ImportStage
	 */
	protected $stage;

	/**
	 * JobRunner constructor.
	 *
	 * @param Job $job
	 * @param Import $import
	 * @param ImportStage $stage
	 */
	public function __construct( Job $job, Import $import, ImportStage $stage ) {
		$this->import = $import;
		$this->stage  = $stage;
		$this->job    = $job;
	}

	/**
	 * Runs the job and handles pre and post-execution logic.
	 */
	public function run() {

		// Handle pre-execution logic
		$this->pre_execute();

		// Run the job.
		$this->job->run();

		// Handle post execution logic.
		$this->post_execute();

		// Schedule next jobs.
		$this->schedule_next();
	}

	/**
	 * Actions to take before executing the job.
	 */
	protected function pre_execute() {
		// Set the job to running.
		$this->job->set_status( JobAbstract::STATUS_RUNNING );

		// Set the stage to running.
		$this->stage->set_status( ImportStage::STATUS_RUNNING );
	}

	/**
	 * Actions to take after execution the job.
	 *
	 */
	protected function post_execute() {

		$this->job->set_status( JobAbstract::STATUS_DONE );

		// If there are no more jobs left, the stage is complete.
		if ( ! $this->stage->has_jobs( true ) ) {
			$this->stage->set_status( ImportStage::STATUS_COMPLETED );
		}

	}

	/**
	 * Schedule jobs that are pending in stages that are ready to go.
	 *
	 * @param ImportStage|null $stage
	 */
	protected function schedule_next( ImportStage $stage = null ) {

		$active_stages = $this->import->get_stages(
			array(
				ImportStage::STATUS_RUNNING,
				ImportStage::STATUS_PENDING,
				ImportStage::STATUS_SCHEDULED,
			)
		);

		// Schedule jobs for active stages.
		foreach ( $active_stages as $active_stage ) {
			$active_stage->schedule_jobs();
		}

		// If there are no more active stages, mark the import as complete.
		if ( ! count( $active_stages ) ) {
			$this->import->set_status( Import::STATUS_DONE );
		}

	}

	/**
	 * Initialize the job runner
	 */
	public static function init() {
		$action = array( static::class, 'execute_job' );
		add_action( static::ACTION_HOOK, $action, 10, 2 );
	}

	/**
	 * Prepare the JobRunner and execute.
	 *
	 * @param string $job_class The classname of the job that needs to be executed.
	 * @param array $job_meta The meta associated with the job.
	 */
	public static function execute_job( $job_class, $job_meta ) {

		if ( ! class_exists( $job_class ) ) {
			throw new Exception( sprintf( __( 'Job class "%s" does not exist', 'wordpress-importer' ), $job_class ) );
		}

		$job = get_comment( $job_meta['stage_job'] );

		if ( ! $job ) {
			throw new Exception( sprintf( __( 'Could not find stage job %d', 'wordpress-importer' ), $job_meta['stage_job'] ) );
		}

		$import = new Import( $job->comment_post_ID, Scheduler::instance() );

		$stage_comment = get_comment( $job->comment_parent );

		if ( ! $stage_comment ) {
			throw new Exception( sprintf( __( 'Could not find stage %d', 'wordpress-importer' ), $job->comment_parent ) );
		}

		$stage = new ImportStage( $stage_comment, $import );

		/** @var Job $class */
		$job_instance = new $job_class( $import, $job );

		$runner = new static( $job_instance, $import, $stage );
		$runner->run();

	}
}
