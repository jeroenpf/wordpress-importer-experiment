<?php

namespace ImporterExperiment;


class WXR_Indexer {

	/**
	 * @var XmlParser
	 */
	protected $parser;

	protected $elements = array();

	protected $handle;

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

		$this->handle = fopen( $file, 'rb' );

		while ( ! feof( $this->handle ) ) {
			$data = fread( $this->handle, 4096 );
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

		// Get the byte position of the tag start of the tag.
		$current_pointer = ftell( $this->handle );
		$start           = $this->get_tag_start_byte( xml_get_current_byte_index( $this->parser ), $tag );

		// Set the file pointer back to where it was before we backtracked.
		fseek( $this->handle, $current_pointer );

		$this->elements[ $tag ][] = $start;
	}

	/**
	 * Find the opening tag byte position of the given tag.
	 *
	 * @param int $start_byte The byte offset at which to start check the tag.
	 * @param string $tag The tag name we are looking for.
	 *
	 * @return int Byte offset the tag starts at.
	 */
	protected function get_tag_start_byte( $start_byte, $tag ) {

		$search        = '<' . $tag;
		$search_length = mb_strlen( $search );
		fseek( $this->handle, $start_byte - $search_length );
		$chunk = fread( $this->handle, $search_length );

		return $chunk === $search
			? $start_byte - $search_length
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
