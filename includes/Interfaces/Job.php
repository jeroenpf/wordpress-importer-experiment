<?php

namespace ImporterExperiment\Interfaces;

use ImporterExperiment\Importer;
use ImporterExperiment\ImportStage;

interface Job {

	public function __construct(Importer  $importer);

	public function run( $job_meta, ImportStage $stage = null);

}
