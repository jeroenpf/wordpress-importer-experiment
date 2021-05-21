<?php

namespace ImporterExperiment\Interfaces;

interface JobRunner {

	public function run( $job_class, $job_meta );

}
