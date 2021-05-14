<?php

// Play around with the XML parser features of PHP

class WXR_Reader {

	/**
	 * @var XmlParser
	 */
	protected $parser;

	protected $elements = [];

	protected $depth = 0;

	protected $current_element;

	protected $handle;

	protected $allowed_tags = ['item', 'wp:category'];

	public function __construct(){

		$this->parser = xml_parser_create( 'UTF-8' );

		xml_set_object( $this->parser, $this );
		xml_set_element_handler( $this->parser, 'tag_open', 'tag_close' );
		xml_set_character_data_handler( $this->parser, 'cdata' );
		xml_set_default_handler( $this->parser, 'text');
		xml_parser_set_option( $this->parser, XML_OPTION_CASE_FOLDING, 0 );
		xml_parser_set_option( $this->parser, XML_OPTION_SKIP_WHITE, 0 );
	}

	public function parse( $file = 'big_export.xml' ) {
		$this->handle = fopen($file, 'rb' );

		while( !feof($this->handle) ) {
			$data = fread($this->handle, 4096);
			xml_parse($this->parser, $data, feof($this->handle));
		}
	}

	public function get_data( $type = 'item' ) {


		if( empty($this->elements[$type])) {
			return;
		}

		foreach($this->elements[$type] as $element) {
			list($start, $end) = explode(":", $element);
			fseek($this->handle, $start);
			$length = $end - $start;
			yield fread($this->handle, $length);
		}

	}

	protected function text( $parser, $data ) {
		var_dump($data);

	}

	protected function tag_open($parser, $tag, $attributes) {

		if( ! in_array( $tag, $this->allowed_tags, true ) ) {
			return;
		}

		$this->current_element = $tag;
		$this->elements[$tag][] = xml_get_current_byte_index($this->parser);
	}

	protected function tag_close($parser, $tag) {

		if( ! in_array( $tag, $this->allowed_tags, true ) ) {
			return;
		}

		$last_key = count($this->elements[$tag]) - 1;
		$this->elements[$tag][$last_key] .=  ':' . xml_get_current_byte_index($this->parser);
	}

	protected function cdata($parser, $cdata) {
		var_dump($cdata);
	}



}
$start = microtime(true);

$parser = new WXR_Reader();

$parser->parse();

foreach($parser->get_data('item') as $author) {
	var_dump($author);
	break;
}



echo "Memory: " . round( memory_get_peak_usage() / 1024 / 1024, 2 ) . "MB\n";

$end = microtime( true );
echo "Time: " . round( $end - $start, 2 ) . "s\n";
