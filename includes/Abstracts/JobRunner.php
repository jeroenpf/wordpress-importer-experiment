<?php

namespace ImporterExperiment\Abstracts;

use ImporterExperiment\Interfaces\JobRunner as JobRunnerInterface;

abstract class JobRunner implements JobRunnerInterface {


	const JOB_RUNNER_CLASS = \ImporterExperiment\JobRunner::class;

	const ACTION_HOOK = 'importer_experiment_run_job';

	/**
	 * @var JobRunner
	 */
	private static $runner;

	abstract public function init();

	public function get_hook_name() {
		return static::ACTION_HOOK;
	}

	public static function instance() {
		if ( empty( self::$runner ) ) {
			$class        = apply_filters( 'importer_experiment_job_runner_class', self::JOB_RUNNER_CLASS );
			self::$runner = new $class();
		}

		return self::$runner;
	}
}
