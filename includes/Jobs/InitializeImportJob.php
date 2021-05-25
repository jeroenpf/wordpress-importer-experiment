<?php

namespace ImporterExperiment\Jobs;

use ImporterExperiment\Interfaces\Job;

class InitializeImportJob implements Job {


	public function run( $job_meta ) {
		var_dump("Initializinggg");
	}
}
