<?php

namespace ImporterExperiment\Jobs;

use ImporterExperiment\Abstracts\Job;
use ImporterExperiment\ImportStage;

class FinalizeImportJob extends Job {

	public function run( $job_meta, ImportStage $stage = null ) {

		$this->importer->set_import_meta( 'status', ImportStage::STATUS_COMPLETED );
	}

}
