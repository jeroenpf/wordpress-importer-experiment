<?php

namespace ImporterExperiment\Abstracts;

use ImporterExperiment\Dispatchers\ActionSchedulerDispatcher;
use ImporterExperiment\Interfaces\Dispatcher as DispatcherInterface;

abstract class Dispatcher implements DispatcherInterface {

	const DEFAULT_CLASS = ActionSchedulerDispatcher::class;

	/** @var Dispatcher */
	private static $scheduler = null;

	abstract public function init();

	/**
	 * @return Dispatcher
	 */
	public static function instance() {

		if ( empty( self::$scheduler ) ) {
			$class           = apply_filters( 'importer_experiment_scheduler_class', self::DEFAULT_CLASS );
			self::$scheduler = new $class();
		}

		return self::$scheduler;
	}



}
