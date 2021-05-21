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
		if ( ! is_readable( $file ) ) {
			exit;
		}

		$this->handle = fopen( $file, 'rb' );
		$this->data_offset = 0;
		$chunk_size = 4096;

		while ( ! feof( $this->handle ) ) {
			$data = fread( $this->handle, $chunk_size );
			// Allow backtracking for finding the tag start beyond the 4k data piece.
			$this->data = substr( $this->data, $chunk_size ) . $data;
			xml_parse( $this->parser, $data, feof( $this->handle ) );
			$this->data_offset += strlen( $data );
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
		$r = strrpos( $this->data, '<' . $tag, - ( strlen( $this->data ) - ( $p - $this->data_offset ) ) );

		$this->elements[ $tag ][] = $r + $this->data_offset;
	}

	protected function tag_close( $parser, $tag ) {

		if ( ! in_array( $tag, $this->allowed_tags, true ) ) {
			return;
		}

		$last_key                             = count( $this->elements[ $tag ] ) - 1;
		$this->elements[ $tag ][ $last_key ] .= ':' . xml_get_current_byte_index( $this->parser );
	}

}
