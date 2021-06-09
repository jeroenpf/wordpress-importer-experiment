<?php

namespace ImporterExperiment\Dispatchers;

use ImporterExperiment\Abstracts\Dispatcher as DispatcherAbstract;

/**
 * Class SyncDispatcher
 *
 * The SyncDispatcher does not schedule jobs in a queue system but
 * executes dispatched jobs immediately.
 *
 * @package ImporterExperiment\Dispatchers
 */
class SyncDispatcher extends DispatcherAbstract {


	public function init() {
		// Nothing to initialize.
	}

	public function dispatch( $hook, $job_class, $args = array() ) {
		do_action( $hook, $job_class, $args );
	}

	public function delete( $hook ) {
		// TODO: Implement delete() method.
	}
}
