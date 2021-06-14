<?php

namespace ImporterExperiment;
use SimpleXMLElement;
use DOMDocument;

class PartialXMLReader {

	/**
	 * The namespaces found in the XML.
	 *
	 * @var array $namespaces
	 */
	protected $namespaces;

	/**
	 * Convert an XML element represented by the given byte-range into a SimpleXMLElement
	 *
	 * @param string $object   A colon delimited byte range representing the object in
	 *                         the WXR XML file. ( e.g 1234:6789 ).
	 * @param string $wxr_path The filepath of the WXR file.
	 *
	 * @return SimpleXMLElement
	 */
	public function object_to_simplexml( $object, $wxr_path ) {
		// Get the XML fragment from the WXR.

		$xml = $this->get_object_xml( $object, $wxr_path );

		// Format XML fragment and convert into an SimpleXMLElement.
		$xml = $this->format_xml( $xml );

		return $this->to_simplexml_element( $xml );
	}

	/**
	 * Get the XML string for the object (byte range).
	 *
	 * @param string $object   A colon delimited byte range representing the object in
	 *                         the WXR XML file. ( e.g 1234:6789 ).
	 * @param string $wxr_path The filepath of the WXR file.
	 *
	 * @return false|string
	 */
	protected function get_object_xml( $object, $wxr_path ) {
		$handle = fopen( $wxr_path, 'rb' );

		list($start, $end) = explode( ':', $object );
		fseek( $handle, $start );
		$output = fread( $handle, $end - $start );
		fclose( $handle );

		return $output;
	}


	/**
	 * Convert the XML string into a SimpleXMLElement.
	 *
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

			throw new ImporterException( libxml_get_last_error()->message );
		}

		$xml              = simplexml_import_dom( $dom );
		$this->namespaces = $xml->getDocNamespaces();
		unset( $dom );

		return $xml;
	}

	/**
	 * Format the partial XML string into a valid XML string.
	 *
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
