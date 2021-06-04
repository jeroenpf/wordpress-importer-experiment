<?php

namespace ImporterExperiment\PartialImporters;

use ImporterExperiment\Abstracts\PartialXMLImport;
use SimpleXMLElement;

class Term extends PartialXMLImport {

	use TermMetaTrait;

	public function import() {
		$terms = apply_filters( 'wp_import_terms', array( $this->data ) );

		if ( empty( $terms ) ) {
			return;
		}
		foreach ( $terms as $term ) {
			$this->import_term( $term );
		}

	}

	protected function import_term( $term ) {
		$existing_term_id = $this->get_existing_term_id( $term['slug'], $term['term_taxonomy'] );
		if( $existing_term_id ) {
			$this->set_term_wxr_id($existing_term_id, $term);
			return;
		}

		if ( empty( $term['term_parent'] ) ) {
			$parent = 0;
		} else {
			$parent = term_exists( $term['term_parent'], $term['term_taxonomy'] );
			if ( is_array( $parent ) ) {
				$parent = $parent['term_id'];
			}
		}

		$description = isset( $term['term_description'] ) ? $term['term_description'] : '';
		$args        = array(
			'slug'        => $term['slug'],
			'description' => wp_slash( $description ),
			'parent'      => (int) $parent,
		);

		$id = wp_insert_term( wp_slash( $term['term_name'] ), $term['term_taxonomy'], $args );
		if ( is_wp_error( $id ) ) {
			printf( __( 'Failed to import %1$s %2$s', 'wordpress-importer' ), esc_html( $term['term_taxonomy'] ), esc_html( $term['term_name'] ) );
			if ( defined( 'IMPORT_DEBUG' ) && IMPORT_DEBUG ) {
				echo ': ' . $id->get_error_message();
			}
			echo '<br />';
			return;
		}

		$this->process_term_meta( $term, $id['term_id'] );
	}


	public function parse( SimpleXMLElement $xml ) {

		$term = $xml->xpath( '/rss/channel/wp:term' )[0];
		$t    = $term->children( $this->namespaces['wp'] );

		$term = array(
			'term_id'          => (int) $t->term_id,
			'term_taxonomy'    => (string) $t->term_taxonomy,
			'slug'             => (string) $t->term_slug,
			'term_parent'      => (string) $t->term_parent,
			'term_name'        => (string) $t->term_name,
			'term_description' => (string) $t->term_description,
		);

		foreach ( $t->termmeta as $meta ) {
			$term['termmeta'][] = array(
				'key'   => (string) $meta->meta_key,
				'value' => (string) $meta->meta_value,
			);
		}

		return $term;
	}

}
