<?php

namespace ImporterExperiment\Abstracts;

use ImporterExperiment\Importer;
use ImporterExperiment\Interfaces\Job as JobInterface;

abstract class Job implements JobInterface {

	/**
	 * @var Importer
	 */
	protected $importer;

	public function __construct( Importer $importer ) {
		$this->importer = $importer;
	}

}
