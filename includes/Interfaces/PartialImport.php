<?php

namespace ImporterExperiment\Interfaces;

use ImporterExperiment\Import;
use SimpleXMLElement;

interface PartialImport {

	public function __construct( Import $import );
	public function process( $object );
	public function import();

}
