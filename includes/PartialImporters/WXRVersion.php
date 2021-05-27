<?php

namespace ImporterExperiment\PartialImporters;

use ImporterExperiment\Abstracts\PartialXMLImport;
use SimpleXMLElement;

class WXRVersion extends PartialXMLImport {

	protected function parse( SimpleXMLElement $xml ) {

		$version = $xml->xpath( '/rss/channel/wp:wxr_version' )[0];

		return array(
			'version' => (string) $version,
		);

	}

	public function import() {
		// TODO: Implement import() method.
	}
}
