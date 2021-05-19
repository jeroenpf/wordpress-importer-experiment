<?php

namespace ImporterExperiment;

class XML_Parser {


	/** @var \SimpleXMLElement  */
	protected $xml;

	public function __construct( $xml ) {
		$this->xml = simplexml_load_string( $xml );


		var_dump($this->xml);
	}


}
