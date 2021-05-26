<?php

namespace ImporterExperiment\PartialImporters;

use ImporterExperiment\Abstracts\PartialXMLImport;
use SimpleXMLElement;

class Author extends PartialXMLImport {

	protected function parse( SimpleXMLElement $xml ) {
		// TODO: Implement parse() method.

		echo "Author\n";

		return [];
	}

	protected function import( array $data ) {
		// TODO: Implement import() method.
	}
}
