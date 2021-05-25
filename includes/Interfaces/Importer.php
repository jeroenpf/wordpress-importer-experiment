<?php

namespace ImporterExperiment\Interfaces;

interface Importer {

	public function parse();
	public function run( array $objects );

}
