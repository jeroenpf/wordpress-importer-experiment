<?php

namespace ImporterExperiment;

use ImporterExperiment\Abstracts\StageJobRunner as JobRunnerAbstract;
use ImporterExperiment\Abstracts\Dispatcher;
use ImporterExperiment\Interfaces\StageJob;
use ImporterExperiment\Abstracts\StageJob as JobAbstract;

/**
 * Class StageJobRunner
 *
 * The Stage Job Runner runs jobs in dependant stages.
 *
 * It registers an action hook that will run a specific job and schedule
 * more if there are any.
 *
 * Scheduling subsequent jobs depends on the state of each stage. If a stage depends
 * on another that has not completed yet, jobs in that stage will not be scheduled.
 * All pending jobs in a stage that has all its dependencies met, will be scheduled.
 *
 * @package ImporterExperiment
 */
class StageJobRunner extends JobRunnerAbstract {

	/**
	 * @var StageJob
	 */
	protected $stage_job;

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
	 * @param StageJob $stage_job
	 * @param Import $import
	 * @param ImportStage $stage
	 */
	public function __construct( StageJob $stage_job, Import $import, ImportStage $stage ) {
		$this->import    = $import;
		$this->stage     = $stage;
		$this->stage_job = $stage_job;
	}

	/**
	 * Runs the job and handles pre and post-execution logic.
	 */
	public function run() {

		// Handle pre-execution logic
		$this->pre_execute();

		// Run the job.
		$this->stage_job->run();

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
		$this->stage_job->set_status( JobAbstract::STATUS_RUNNING );

		// Set the stage to running.
		$this->stage->set_status( ImportStage::STATUS_RUNNING );
	}

	/**
	 * Actions to take after execution the job.
	 *
	 */
	protected function post_execute() {

		$this->stage_job->set_status( JobAbstract::STATUS_DONE );

		// If there are no more jobs left, the stage is complete.
		if ( ! $this->stage->has_jobs( true ) ) {
			$this->stage->set_status( ImportStage::STATUS_COMPLETED );
		}

		// As a final step, delete the job.
		$this->stage_job->delete();
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
			$active_stage->dispatch_jobs();
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
			throw new ImporterException( sprintf( __( 'Job class "%s" does not exist', 'wordpress-importer' ), $job_class ) );
		}

		$job = get_comment( $job_meta['stage_job'] );

		if ( ! $job ) {
			throw new ImporterException( sprintf( __( 'Could not find stage job %d', 'wordpress-importer' ), $job_meta['stage_job'] ) );
		}

		$import = Importer::instance()->get_import_by_id( $job->comment_post_ID );

		$stage_comment = get_comment( $job->comment_parent );

		if ( ! $stage_comment ) {
			throw new ImporterException( sprintf( __( 'Could not find stage %d', 'wordpress-importer' ), $job->comment_parent ) );
		}

		$stage = new ImportStage( $stage_comment, $import );

		/** @var StageJob $class */
		$stage_job = new $job_class( $import, $job );

		$runner = new static( $stage_job, $import, $stage );
		$runner->run();

	}
}
