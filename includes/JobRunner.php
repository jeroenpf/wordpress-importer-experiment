<?php

namespace ImporterExperiment;

use ImporterExperiment\Abstracts\JobRunner as JobRunnerAbstract;
use ImporterExperiment\Abstracts\Scheduler;
use ImporterExperiment\Interfaces\Job;
use ImporterExperiment\Jobs\FinalizeImportJob;

class JobRunner extends JobRunnerAbstract {

	const ACTION_HOOK = 'importer_experiment_run_job';

	public function run( $job_class, $job_meta ) {

		// Todo: We should probably obtain the state here and pass it to the jobs;
		// Each job could manipulate the state, if needed.
		// Possibly a State class that allows easy interaction with the state could be helpful.

		$stage     = null;
		$stage_job = null;

		$importer = Importer::instance();

		if ( ! empty( $job_meta['stage_job'] ) ) {
			$stage_job = get_term_by( 'id', $job_meta['stage_job'], $importer::TAXONOMY );
			$stage     = get_term_by( 'id', $stage_job->parent, $importer::TAXONOMY );
			$stage     = new ImportStage( $stage );
		}

		/** @var Job $class */
		$class = new $job_class( $importer );

		$this->pre_execute( $stage_job, $stage );

		$class->run( $job_meta );

		$this->post_execute( $stage_job, $stage );

		$this->schedule_next( $stage );
	}

	protected function pre_execute( \WP_Term $stage_job = null, ImportStage $stage = null ) {

		if ( $stage_job ) {
			update_term_meta( $stage_job->term_id, 'status', ImportStage::STATUS_RUNNING );
			$stage->set_status( ImportStage::STATUS_RUNNING );
		}

		// Update the status of the stage.
	}

	protected function post_execute( \WP_Term $stage_job = null, ImportStage $stage = null ) {

		if ( $stage_job ) {
			$importer = Importer::instance();
			update_term_meta( $stage_job->term_id, 'status', ImportStage::STATUS_COMPLETED );
			$jobs = get_terms(
				array(
					'hide_empty'   => false,
					'parent'       => $stage->get_id(),
					'meta_key'     => 'status',
					'meta_value'   => ImportStage::STATUS_COMPLETED,
					'meta_compare' => '!=',
					'taxonomy'     => $importer::TAXONOMY,
					'fields'       => 'ids',
				)
			); // todo Use wp_count_terms instead ?

			// If there are no more jobs left, the stage is complete.
			if ( ! count( $jobs ) ) {
				$stage->complete();
			}
		}
	}

	protected function schedule_next( ImportStage $stage = null ) {

		// Todo check if import is marked complete and return.

		$importer = Importer::instance();

		$import        = get_term_by( 'name', 'import', $importer::TAXONOMY );
		$import_status = get_term_meta( $import->term_id, 'status', true );

		// Get all stages that have not completed and that are not on hold.
		$stages_term = get_term_by( 'name', 'stages', $importer::TAXONOMY );
		$stages      = get_terms(
			array(
				'hide_empty'   => false,
				'taxonomy'     => $importer::TAXONOMY,
				'parent'       => $stages_term->term_id,
				'meta_key'     => 'status',
				'meta_value'   => array( ImportStage::STATUS_COMPLETED, ImportStage::STATUS_HOLD ),
				'meta_compare' => 'NOT IN',
			)
		);

		// Schedule jobs for active stages.
		foreach ( $stages as $active_stage ) {
			$active_stage = new ImportStage( $active_stage );
			$active_stage->schedule_jobs();
		}

		// If we executed a stage job and there are no more stages, schedule the final job.
		if ( $stage && ! count( $stages ) ) {
			$scheduler = Scheduler::instance();
			$scheduler->schedule( $this->get_hook_name(), FinalizeImportJob::class );
		}

	}

	public function init() {
		add_action(
			self::ACTION_HOOK,
			array( $this, 'run' ),
			10,
			2
		);
	}
}
