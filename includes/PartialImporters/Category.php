<?php

namespace ImporterExperiment\PartialImporters;

use ImporterExperiment\Abstracts\PartialXMLImport;
use SimpleXMLElement;

class Category extends PartialXMLImport {

	use TermMetaTrait;

	protected function import( array $data ) {

		$categories = apply_filters( 'wp_import_categories', array( $data ) );

		if ( empty( $categories ) ) {
			return;
		}

		foreach ( $categories as $category ) {
			$this->import_category( $category );
		}
	}

	protected function import_category( $category ) {
		// if the category already exists leave it alone
		$term_id = term_exists( $category['category_nicename'], 'category' );
		if ( $term_id ) {
			//          if ( is_array( $term_id ) ) {
			//              $term_id = $term_id['term_id'];
			//          }
			//          if ( isset( $category['term_id'] ) ) {
			//              $this->processed_terms[ (int) $category['term_id'] ] = (int) $term_id;
			//          }
			return;
		}

		$parent = empty( $category['category_parent'] )
			? 0
			: category_exists( $category['category_parent'] );

		$description = isset( $category['category_description'] )
			? $category['category_description']
			: '';

		$data = array(
			'category_nicename'    => $category['category_nicename'],
			'category_parent'      => $parent,
			'cat_name'             => wp_slash( $category['cat_name'] ),
			'category_description' => wp_slash( $description ),
		);

		$id = wp_insert_category( $data );

		if ( is_wp_error( $id ) || $id <= 0 ) {
			printf( __( 'Failed to import category %s', 'wordpress-importer' ), esc_html( $category['category_nicename'] ) );
			if ( defined( 'IMPORT_DEBUG' ) && IMPORT_DEBUG ) {
				echo ': ' . $id->get_error_message();
			}
			echo '<br />';
			return;
		}

		//          if ( isset( $category['term_id'] ) ) {
		//              $this->processed_terms[ (int) $category['term_id'] ] = $id;
		//          }

		$this->process_term_meta( $category, $id );
	}

	public function parse( SimpleXMLElement $xml ) {

		$cat = $xml->xpath( '/rss/channel/wp:category' )[0];
		$t   = $cat->children( $this->namespaces['wp'] );

		$category = array(
			'term_id'              => (int) $t->term_id,
			'category_nicename'    => (string) $t->category_nicename,
			'category_parent'      => (string) $t->category_parent,
			'cat_name'             => (string) $t->cat_name,
			'category_description' => (string) $t->category_description,
		);

		foreach ( $t->termmeta as $meta ) {
			$category['termmeta'][] = array(
				'key'   => (string) $meta->meta_key,
				'value' => (string) $meta->meta_value,
			);
		}

		return $category;
	}
}
