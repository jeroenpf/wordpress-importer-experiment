<?php

namespace ImporterExperiment\PartialImporters;

use ImporterExperiment\Import;

trait MenuItemTrait {

	/**
	 * Process a menu item.
	 *
	 * @param array $item
	 */
	protected function process_menu_item( array $item, $store_missing = true ) {

		// skip draft, orphaned menu items
		if ( 'draft' === $item['status'] ) {
			return;
		}

		$menu_slug = false;
		if ( isset( $item['terms'] ) ) {
			// loop through terms, assume first nav_menu term is correct menu
			foreach ( $item['terms'] as $term ) {
				if ( 'nav_menu' == $term['domain'] ) {
					$menu_slug = $term['slug'];
					break;
				}
			}
		}

		// no nav_menu term associated with this menu item
		if ( ! $menu_slug ) {
			$this->import->get_logger()->error( __( 'Menu item skipped due to missing menu slug', 'wordpress-importer' ) );
			return;
		}

		$menu_id = term_exists( $menu_slug, 'nav_menu' );
		if ( ! $menu_id ) {
			$msg = sprintf( __( 'Menu item skipped due to invalid menu slug: %s', 'wordpress-importer' ), esc_html( $menu_slug ) );
			$this->import->get_logger()->error( $msg );
			return;
		}

		$menu_id = is_array( $menu_id ) ? $menu_id['term_id'] : $menu_id;

		foreach ( $item['postmeta'] as $meta ) {
			${$meta['key']} = $meta['value'];
		}

		$_menu_item_object_id = $this->get_menu_object_id( $_menu_item_type, $_menu_item_object_id );

		if ( ! $_menu_item_object_id ) {

			// associated object is missing or not imported yet, we'll retry later
			if ( $store_missing ) {
				$this->import->get_logger()->notice( 'Menu item points to a missing object and will be retried later.' );
				$this->store_missing( $item );
			}

			return;
		}

		$orphaned_parent_id = null;
		$processed_parent   = $this->get_post_id_by_wxr_id( (int) $_menu_item_menu_item_parent );
		if ( $processed_parent ) {
			$_menu_item_menu_item_parent = $processed_parent;
		} elseif ( $_menu_item_menu_item_parent ) {
			$orphaned_parent_id          = (int) $_menu_item_menu_item_parent;
			$_menu_item_menu_item_parent = 0;
		}

		// wp_update_nav_menu_item expects CSS classes as a space separated string
		$_menu_item_classes = maybe_unserialize( $_menu_item_classes );
		if ( is_array( $_menu_item_classes ) ) {
			$_menu_item_classes = implode( ' ', $_menu_item_classes );
		}

		$args = array(
			'menu-item-object-id'   => $_menu_item_object_id,
			'menu-item-object'      => $_menu_item_object,
			'menu-item-parent-id'   => $_menu_item_menu_item_parent,
			'menu-item-position'    => (int) $item['menu_order'],
			'menu-item-type'        => $_menu_item_type,
			'menu-item-title'       => $item['post_title'],
			'menu-item-url'         => $_menu_item_url,
			'menu-item-description' => $item['post_content'],
			'menu-item-attr-title'  => $item['post_excerpt'],
			'menu-item-target'      => $_menu_item_target,
			'menu-item-classes'     => $_menu_item_classes,
			'menu-item-xfn'         => $_menu_item_xfn,
			'menu-item-status'      => $item['status'],
		);

		$id = wp_update_nav_menu_item( $menu_id, 0, $args );
		if ( $id && ! is_wp_error( $id ) ) {

			$this->set_post_meta( $id, (int) $item['post_id'], $orphaned_parent_id );
		}
	}

	protected function store_missing( $item ) {
		add_post_meta( $this->import->get_id(), 'missing_menu_item', $item );
	}

	protected function get_menu_object_id( $menu_type, $menu_item_object_id ) {

		if ( 'taxonomy' === $menu_type ) {
			$new_object_id = $this->get_term_id_by_wxr_id( $menu_item_object_id );
		}

		if ( 'post_type' === $menu_type ) {
			$new_object_id = $this->get_post_id_by_wxr_id( $menu_item_object_id );
		}

		if ( 'custom' !== $menu_type && ! $new_object_id ) {
			return null;
		}

		return $new_object_id ?: $menu_item_object_id;
	}

	protected function set_post_meta( $post_id, $wxr_id, $wxr_parent_id = null ) {
		add_post_meta( $post_id, 'import_id', $this->import->get_id(), true );
		add_post_meta( $post_id, 'wxr_id', $wxr_id, true );

		if ( $wxr_parent_id ) {
			add_post_meta( $post_id, 'orphaned_wxr_id', (int) $wxr_parent_id, true );
		}
	}

	protected function get_term_id_by_wxr_id( $wxr_term_id ) {
		$terms = get_terms(
			array(
				'fields'     => 'ids',
				'meta_query' => array(
					array(
						'key'   => 'wxr_id',
						'value' => $wxr_term_id,
					),
					array(
						'key'   => 'import_id',
						'value' => $this->import->get_id(),
					),
				),
			)
		);

		return count( $terms ) ? $terms[0] : null;
	}

	abstract protected function get_post_id_by_wxr_id( $wxr_id );

}
