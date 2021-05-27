<?php

namespace ImporterExperiment\PartialImporters;

use ImporterExperiment\Abstracts\PartialXMLImport;
use SimpleXMLElement;

class Author extends PartialXMLImport {

	protected function parse( SimpleXMLElement $xml ) {

		$author = $xml->xpath( '/rss/channel/wp:author' )[0];

		$a = $author->children( $this->namespaces['wp'] );

		return array(
			'author_id'           => (int) $a->author_id,
			'author_login'        => (string) $a->author_login,
			'author_email'        => (string) $a->author_email,
			'author_display_name' => (string) $a->author_display_name,
			'author_first_name'   => (string) $a->author_first_name,
			'author_last_name'    => (string) $a->author_last_name,
		);

	}

	public function import() {
		// TODO: Implement import() method.
	}
}
