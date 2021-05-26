<?php

namespace ImporterExperiment\Abstracts;

use ImporterExperiment\Exception;
use ImporterExperiment\Interfaces\PartialImport;
use ImporterExperiment\Importer;
use SimpleXMLElement;
use DOMDocument;

abstract class PartialXMLImport implements PartialImport {

	public $namespaces = array();

	/**
	 * @var Importer
	 */
	protected $importer;

	public function __construct( Importer $importer ) {
		$this->importer = $importer;
	}

	/**
	 * Parse the XML fragment into an array of data.
	 *
	 * @param SimpleXMLElement $xml
	 *
	 * @return array An array containing data needed for the import.
	 */
	abstract protected function parse( SimpleXMLElement $xml );

	abstract protected function import( array $data );

	/**
	 * Run the partial importer.
	 *
	 * @param string $object Object byte range eg. 1234:4567
	 */
	public function run( $object ) {

		// Get the XML fragment from the WXR.
		$handle = fopen( $this->importer->get_import_meta( 'file' ), 'rb' );
		$xml    = $this->get_object_xml( $object, $handle );
		fclose( $handle );

		// Format XML fragment and convert into an SimpleXMLElement.
		$xml = $this->format_xml( $xml );
		$xml = $this->to_simplexml_element( $xml );

		// Parse and import.
		$this->import( $this->parse( $xml ) );

	}

	/**
	 * @param $object
	 * @param $handle
	 *
	 * @return false|string
	 */
	protected function get_object_xml( $object, $handle ) {
		list($start, $end) = explode( ':', $object );
		fseek( $handle, $start );
		return fread( $handle, $end - $start );
	}


	/**
	 * @param $data
	 *
	 * @return SimpleXMLElement
	 */
	protected function to_simplexml_element( $data ) {
		libxml_use_internal_errors( true );

		$dom       = new DOMDocument;
		$old_value = null;
		if ( function_exists( 'libxml_disable_entity_loader' ) ) {
			$old_value = libxml_disable_entity_loader( true );
		}
		$success = $dom->loadXML( $data );

		if ( ! is_null( $old_value ) ) {
			libxml_disable_entity_loader( $old_value );
		}

		if ( ! $success || isset( $dom->doctype ) ) {

			echo $data;

			throw new Exception( libxml_get_last_error()->message );
		}

		$xml              = simplexml_import_dom( $dom );
		$this->namespaces = $xml->getDocNamespaces();
		unset( $dom );

		return $xml;
	}

	/**
	 * @param string $xml_fragment
	 *
	 * @return string
	 */
	protected function format_xml( $xml_fragment ) {

		$output = <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:wp="http://wordpress.org/export/1.2/"
>
	<channel>
	 %s
	</channel>
</rss>
XML;

		return sprintf( $output, $xml_fragment );

	}
}
