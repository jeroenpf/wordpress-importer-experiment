<?php

namespace ImporterExperiment\Interfaces;

use SimpleXMLElement;

interface PartialImport {

	public function process( $object );
	public function import();

}
