<?php

namespace ImporterExperiment\Abstracts;

use ImporterExperiment\Dispatchers\ActionSchedulerDispatcher;
use ImporterExperiment\Interfaces\Dispatcher as DispatcherInterface;

abstract class Dispatcher implements DispatcherInterface {

	const DEFAULT_CLASS = ActionSchedulerDispatcher::class;

	/** @var Dispatcher */
	private static $dispatcher = null;

	abstract public function init();

	/**
	 * @return Dispatcher
	 */
	public static function instance() {

		if ( empty( self::$dispatcher ) ) {
			$class            = apply_filters( 'wordpress_importer_dispatcher_class', self::DEFAULT_CLASS );
			self::$dispatcher = new $class();
		}

		return self::$dispatcher;
	}



}
