<?php

namespace ImporterExperiment\Jobs;

use ImporterExperiment\Abstracts\Job;
use ImporterExperiment\ImportStage;

class FinalizeImportJob extends Job {

	public function run( $job_meta ) {

		$this->importer->set_import_meta( 'status', ImportStage::STATUS_COMPLETED );
	}

}
