<?php

namespace ImporterExperiment\PartialImporters;

use ImporterExperiment\Abstracts\PartialXMLImport;
use SimpleXMLElement;

class Tag extends PartialXMLImport {

	use TermMetaTrait;

	public function import() {

		$tags = apply_filters( 'wp_import_tags', array( $this->data ) );

		if ( empty( $tags ) ) {
			return;
		}

		foreach ( $tags as $tag ) {
			$this->import_tag( $tag );
		}
	}

	protected function import_tag( $tag ) {
		$existing_term_id = $this->get_existing_term_id( $tag['tag_slug'], 'post_tag' );
		if( $existing_term_id ) {
			$this->set_term_wxr_id( $existing_term_id, $tag );
			return;
		}

		$description = isset( $tag['tag_description'] ) ? $tag['tag_description'] : '';
		$args        = array(
			'slug'        => $tag['tag_slug'],
			'description' => wp_slash( $description ),
		);

		$id = wp_insert_term( wp_slash( $tag['tag_name'] ), 'post_tag', $args );
		if ( is_wp_error( $id ) ) {

			printf( __( 'Failed to import post tag %s', 'wordpress-importer' ), esc_html( $tag['tag_name'] ) );
			if ( defined( 'IMPORT_DEBUG' ) && IMPORT_DEBUG ) {
				echo ': ' . $id->get_error_message();
			}
			echo '<br />';
			return;
		}

		$this->process_term_meta( $tag, $id['term_id'] );
	}

	public function parse( SimpleXMLElement $xml ) {

		$tag = $xml->xpath( '/rss/channel/wp:tag' )[0];
		$t   = $tag->children( $this->namespaces['wp'] );

		$tag = array(
			'term_id'         => (int) $t->term_id,
			'tag_slug'        => (string) $t->tag_slug,
			'tag_name'        => (string) $t->tag_name,
			'tag_description' => (string) $t->tag_description,
		);

		foreach ( $t->termmeta as $meta ) {
			$tag['termmeta'][] = array(
				'key'   => (string) $meta->meta_key,
				'value' => (string) $meta->meta_value,
			);
		}

		return $tag;
	}
}
