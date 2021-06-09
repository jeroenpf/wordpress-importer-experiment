<?php

namespace ImporterExperiment\Interfaces;


/**
 * Interface Dispatcher
 *
 * A dispatcher is responsible for dispatching a job to a queue.
 *
 * @package ImporterExperiment\Interfaces
 */
interface Dispatcher {

	/**
	 * Immediately dispatch a job with the given action hook and Stage Job Class.
	 *
	 * The job will run through the underlying queue worker as soon as possible.
	 *
	 * @param $hook
	 * @param $job_class
	 * @param array $args
	 *
	 * @return int ID of the job.
	 */
	public function dispatch( $hook, $job_class, $args = array() );


	/**
	 * Delete a job by hook-name from the queue.
	 *
	 * @param $hook
	 *
	 * @return mixed
	 */
	public function delete( $hook );

}
