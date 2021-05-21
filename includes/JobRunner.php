<?php

namespace ImporterExperiment;

use ImporterExperiment\Abstracts\JobRunner as JobRunnerAbstract;
use ImporterExperiment\Interfaces\Job;

class JobRunner extends JobRunnerAbstract {

	const ACTION_HOOK = 'importer_experiment_run_job';

	public function run( $job_class, $job_meta ) {

		// Todo: We should probably obtain the state here and pass it to the jobs;
		// Each job could manipulate the state, if needed.
		// Possibly a State class that allows easy interaction with the state could be helpful.

		/** @var Job $class */
		$class = new $job_class();

		$class->run( $job_meta );
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
