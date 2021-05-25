<?php

namespace ImporterExperiment\Interfaces;

use ImporterExperiment\Importer;

interface Job {

	public function __construct(Importer  $importer);

	public function run( $job_meta );

}
