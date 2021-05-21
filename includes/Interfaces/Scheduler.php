<?php

namespace ImporterExperiment\Interfaces;


interface Scheduler {

	public function schedule( $hook, $job_class, $args = array() );


	public function unschedule( $hook );

}
