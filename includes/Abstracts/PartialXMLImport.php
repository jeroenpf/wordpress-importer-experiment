<?php

namespace ImporterExperiment\Abstracts;

use ImporterExperiment\Interfaces\PartialImport;
use ImporterExperiment\Importer;
use ImporterExperiment\PartialXMLReader;
use SimpleXMLElement;

abstract class PartialXMLImport extends PartialXMLReader implements PartialImport {

	public $namespaces = array();

	/**
	 * Array containing the parsed data.
	 *
	 * @var array Parsed data.
	 */
	protected $data = array();

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

	public function process( $object ) {
		$wxr_path   = $this->importer->get_import_meta( 'file' );
		$xml        = $this->object_to_simplexml( $object, $wxr_path );
		$this->data = $this->parse( $xml );
	}

	/**
	 * @return array
	 */
	public function get_data() {
		return $this->data;
	}
}
