<?php


namespace ImporterExperiment\PartialImporters;

trait TermMetaTrait {

	protected function process_term_meta( $term, $term_id, $import_id ) {
		if ( ! isset( $term['termmeta'] ) ) {
			$term['termmeta'] = array();
		}

		/**
		 * Filters the metadata attached to an imported term.
		 *
		 * @since 0.6.2
		 *
		 * @param array $termmeta Array of term meta.
		 * @param int   $term_id  ID of the newly created term.
		 * @param array $term     Term data from the WXR import.
		 */
		$term['termmeta'] = apply_filters( 'wp_import_term_meta', $term['termmeta'], $term_id, $term );

		$this->set_import_meta($term_id, $term, $import_id);

		foreach ( $term['termmeta'] as $meta ) {
			/**
			 * Filters the meta key for an imported piece of term meta.
			 *
			 * @since 0.6.2
			 *
			 * @param string $meta_key Meta key.
			 * @param int    $term_id  ID of the newly created term.
			 * @param array  $term     Term data from the WXR import.
			 */
			$key = apply_filters( 'import_term_meta_key', $meta['key'], $term_id, $term );
			if ( ! $key ) {
				continue;
			}

			// Export gets meta straight from the DB so could have a serialized string
			$value = maybe_unserialize( $meta['value'] );

			add_term_meta( $term_id, wp_slash( $key ), wp_slash_strings_only( $value ) );

			/**
			 * Fires after term meta is imported.
			 *
			 * @since 0.6.2
			 *
			 * @param int    $term_id ID of the newly created term.
			 * @param string $key     Meta key.
			 * @param mixed  $value   Meta value.
			 */
			do_action( 'import_term_meta', $term_id, $key, $value );
		}
	}

	protected function get_existing_term_id( $slug, $taxonomy ) {
		$existing_term = term_exists( $slug, $taxonomy );
		if ( !$existing_term ) {
			return null;
		}

		return is_array( $existing_term ) ? $existing_term['term_id'] : $existing_term;
	}

	protected function set_import_meta($term_id, $term, $import_id) {

		add_term_meta($term_id, 'import_id', $term['term_id'], true);

		if ( isset( $term['term_id'] ) ) {
			add_term_meta($term_id, 'wxr_id', $term['term_id'], true);
		}
	}

}
