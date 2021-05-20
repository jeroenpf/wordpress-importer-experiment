<?php
namespace ImporterExperiment;

abstract class Partial_XML_Importer {
	public $namespaces = array();

	public function process_job( $state, $job ) {
		$f = fopen( $job['file'], 'r' );

		foreach ( $job['objects'] as $object ) {
			$start = strtok( $object, ':' );
			$end = strtok( ':' );
			fseek( $f, $start );

			$data = fread( $f, $end - $start );
			$xml = '<' . '?xml version="1.0" encoding="UTF-8" ?' . '>';
			$root = '<rss version="2.0"
				xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
				xmlns:content="http://purl.org/rss/1.0/modules/content/"
				xmlns:wfw="http://wellformedweb.org/CommentAPI/"
				xmlns:dc="http://purl.org/dc/elements/1.1/"
				xmlns:wp="http://wordpress.org/export/1.2/"
			><channel>';
			$root_end = '</channel></rss>';

			$state = $this->parse( $state, $xml . $root . $data . $root_end );
		}
		fclose( $f );

		return $state;
	}

	protected function simplexml( $data ) {
		$internal_errors = libxml_use_internal_errors(true);

		$dom = new \DOMDocument;
		$old_value = null;
		if ( function_exists( 'libxml_disable_entity_loader' ) ) {
			$old_value = libxml_disable_entity_loader( true );
		}
		$success = $dom->loadXML( $data );

		if ( ! is_null( $old_value ) ) {
			libxml_disable_entity_loader( $old_value );
		}

		if ( ! $success || isset( $dom->doctype ) ) {
			return new \WP_Error( 'SimpleXML_parse_error', __( 'There was an error when reading this WXR file', 'wordpress-importer' ), libxml_get_errors() );
		}

		$xml = simplexml_import_dom( $dom );
		$this->namespaces = $xml->getDocNamespaces();
		unset( $dom );

		return $xml;
	}

	abstract function parse( $state, $data );
}
