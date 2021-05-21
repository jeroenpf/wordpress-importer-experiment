<?php

namespace ImporterExperiment\Abstracts;

use ImporterExperiment\ActionScheduler;
use ImporterExperiment\Interfaces\Scheduler as SchedulerInterface;

abstract class Scheduler implements SchedulerInterface {

	const DEFAULT_CLASS = ActionScheduler::class;

	/** @var Scheduler */
	private static $scheduler = null;

	abstract public function init();

	/**
	 * @return Scheduler
	 */
	public static function instance() {

		if ( empty( self::$scheduler ) ) {
			$class           = apply_filters( 'importer_experiment_scheduler_class', self::DEFAULT_CLASS );
			self::$scheduler = new $class();
		}

		return self::$scheduler;
	}



}
