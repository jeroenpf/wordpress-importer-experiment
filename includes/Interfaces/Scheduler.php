<?php

namespace ImporterExperiment\Interfaces;


interface Scheduler {

	/**
	 * @param $hook
	 * @param $job_class
	 * @param array $args
	 *
	 * @return int ID of the job.
	 */
	public function schedule( $hook, $job_class, $args = array() );


	public function unschedule( $hook );

}
