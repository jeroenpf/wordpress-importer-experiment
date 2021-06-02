<?php

namespace ImporterExperiment\Abstracts;

use ImporterExperiment\Interfaces\JobRunner as JobRunnerInterface;

abstract class JobRunner implements JobRunnerInterface {


	const ACTION_HOOK = 'importer_experiment_run_job';

}
