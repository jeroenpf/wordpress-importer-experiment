<?php

namespace ImporterExperiment\Abstracts;

use ImporterExperiment\Interfaces\StageJobRunner as JobRunnerInterface;

abstract class StageJobRunner implements JobRunnerInterface {


	const ACTION_HOOK = 'importer_experiment_run_stages';

}
