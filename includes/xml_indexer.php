<?php

namespace ImporterExperiment;


class WXR_Indexer {

	/**
	 * @var XmlParser
	 */
	protected $parser;

	protected $elements = array();

	protected $depth = 0;

	protected $current_element;

	protected $handle;

	protected $allowed_tags = array( 'item', 'wp:category', 'wp:author', 'wp:term' );

	protected $time_reading = 0;
	protected $time_parsing = 0;

	public function __construct() {

		$this->parser = xml_parser_create( 'UTF-8' );

		xml_set_object( $this->parser, $this );
		xml_set_element_handler( $this->parser, 'tag_open', 'tag_close' );
		xml_set_character_data_handler( $this->parser, 'cdata' );
		xml_set_default_handler( $this->parser, 'text' );
		xml_parser_set_option( $this->parser, XML_OPTION_CASE_FOLDING, 0 );
		xml_parser_set_option( $this->parser, XML_OPTION_SKIP_WHITE, 0 );
	}

	public function parse( $file = 'big_export.xml' ) {
		$this->handle = fopen( $file, 'rb' );

		while ( ! feof( $this->handle ) ) {
			$start               = microtime( true );
			$data                = fread( $this->handle, 4096 );
			$this->time_reading += microtime( true ) - $start;

			$start = microtime( true );
			xml_parse( $this->parser, $data, feof( $this->handle ) );
			$this->time_parsing += microtime( true ) - $start;
		}

		echo 'Parsing: ' . round( $this->time_parsing, 2 ) . "s\n";
		echo 'Reading: ' . round( $this->time_reading, 2 ) . "s\n";

	}

	public function get_data( $type ) {

		if ( empty( $this->elements[ $type ] ) ) {
			return;
		}

		foreach ( $this->elements[ $type ] as $element ) {
			yield $element;
		}

		//      foreach ( $this->elements[ $type ] as $element ) {
		//          list($start, $end) = explode( ':', $element );
		//          fseek( $this->handle, $start );
		//          $length = $end - $start;
		//          yield fread( $this->handle, $length );
		//      }

	}

	public function get_count( $type ) {

		return isset( $this->elements[ $type ] ) ? count( $this->elements[ $type ] ) : 0;

	}

	protected function text( $parser, $data ) {

	}

	protected function tag_open( $parser, $tag, $attributes ) {

		if ( ! in_array( $tag, $this->allowed_tags, true ) ) {
			return;
		}

		$this->current_element    = $tag;
		$this->elements[ $tag ][] = xml_get_current_byte_index( $this->parser );
	}

	protected function tag_close( $parser, $tag ) {

		if ( ! in_array( $tag, $this->allowed_tags, true ) ) {
			return;
		}

		$last_key                             = count( $this->elements[ $tag ] ) - 1;
		$this->elements[ $tag ][ $last_key ] .= ':' . xml_get_current_byte_index( $this->parser );
	}

	protected function cdata( $parser, $cdata ) {
	}



}
