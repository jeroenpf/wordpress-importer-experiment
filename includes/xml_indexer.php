<?php

namespace ImporterExperiment;


class WXR_Indexer {

	/**
	 * @var XmlParser
	 */
	protected $parser;

	protected $elements = array();

	protected $handle;

	protected $data = '';
	protected $data_offset = 0;

	protected $allowed_tags = array( 'item', 'wp:category', 'wp:author', 'wp:term' );

	public function __construct() {

		$this->parser = xml_parser_create( 'UTF-8' );

		xml_set_object( $this->parser, $this );
		xml_set_element_handler( $this->parser, 'tag_open', 'tag_close' );
		xml_parser_set_option( $this->parser, XML_OPTION_CASE_FOLDING, 0 );
		xml_parser_set_option( $this->parser, XML_OPTION_SKIP_WHITE, 0 );
	}

	public function parse( $file ) {
		$this->handle = fopen( $file, 'rb' );
		$this->data_offset = 0;

		while ( ! feof( $this->handle ) ) {
			$this->data = fread( $this->handle, 4096 );
			$this->data_offset += strlen( $this->data );
			xml_parse( $this->parser, $this->data, feof( $this->handle ) );
		}

	}

	public function get_data( $type ) {

		if ( empty( $this->elements[ $type ] ) ) {
			$this->elements[ $type ] = array();
		}

		foreach ( $this->elements[ $type ] as $element ) {
			yield $element;
		}
	}

	public function get_count( $type ) {

		return isset( $this->elements[ $type ] ) ? count( $this->elements[ $type ] ) : 0;

	}

	protected function tag_open( $parser, $tag, $attributes ) {

		if ( ! in_array( $tag, $this->allowed_tags, true ) ) {
			return;
		}
		$p = xml_get_current_byte_index( $this->parser );

		// Backtrack to find the real tag start.
		$r = strrpos( $this->data, '<' . $tag, $p - $this->data_offset );

		$this->elements[ $tag ][] = $r;
	}

	protected function tag_close( $parser, $tag ) {

		if ( ! in_array( $tag, $this->allowed_tags, true ) ) {
			return;
		}

		$p        = xml_get_current_byte_index( $this->parser );
		$last_key = count( $this->elements[ $tag ] ) - 1;

		$this->elements[ $tag ][ $last_key ] .= ':' . $p;
	}

}
