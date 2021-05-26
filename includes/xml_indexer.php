<?php

namespace ImporterExperiment;


class WXR_Indexer {

	/**
	 * @var XmlParser
	 */
	protected $parser;

	protected $elements = array();

	protected $handle;

	protected $data        = '';
	protected $data_offset = 0;

	protected $allowed_tags = array( 'item', 'wp:category', 'wp:author', 'wp:term', 'wp:tag' );

	public function __construct() {

		$this->parser = xml_parser_create( 'UTF-8' );

		xml_set_object( $this->parser, $this );
		xml_set_element_handler( $this->parser, 'tag_open', 'tag_close' );
		xml_parser_set_option( $this->parser, XML_OPTION_CASE_FOLDING, 0 );
		xml_parser_set_option( $this->parser, XML_OPTION_SKIP_WHITE, 0 );
	}

	public function parse( $file ) {
		if ( ! is_readable( $file ) ) {
			throw new Exception( 'WXR file does not exist' );
		}

		$this->handle      = fopen( $file, 'rb' );
		$this->data_offset = 0;
		$chunk_size        = 4096;

		while ( ! feof( $this->handle ) ) {
			$data = fread( $this->handle, $chunk_size );
			xml_parse( $this->parser, $data, feof( $this->handle ) );
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

		// Get the byte position of the tag start of the tag.
		$current_pointer = ftell( $this->handle );
		$search          = '<' . $tag;
		$start           = $this->get_tag_start_byte( $p - mb_strlen( $search ), $search );

		// Set the file pointer back to where it was before we backtracked.
		fseek( $this->handle, $current_pointer );

		$this->elements[ $tag ][] = $start;
	}

	protected function get_tag_start_byte( $start_byte, $tag ) {

		$str_len = mb_strlen( $tag );

		fseek( $this->handle, $start_byte );
		$chunk = fread( $this->handle, $str_len );

		return $chunk === $tag
			? $start_byte
			: $this->get_tag_start_byte( $start_byte - 1, $tag );

	}

	protected function tag_close( $parser, $tag ) {

		if ( ! in_array( $tag, $this->allowed_tags, true ) ) {
			return;
		}

		$last_key                             = count( $this->elements[ $tag ] ) - 1;
		$this->elements[ $tag ][ $last_key ] .= ':' . xml_get_current_byte_index( $this->parser );
	}

}
