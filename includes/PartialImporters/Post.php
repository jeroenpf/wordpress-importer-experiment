<?php

namespace ImporterExperiment\PartialImporters;

use ImporterExperiment\Abstracts\PartialXMLImport;
use SimpleXMLElement;

class Post extends PartialXMLImport {

	protected $processed_authors = array();
	protected $author_mapping    = array();

	public function import() {
		$posts = apply_filters( 'wp_import_post', array( $this->data ) );

		$this->author_mapping    = $this->importer->get_import_meta( 'author_mapping' );
		$this->processed_authors = $this->importer->get_import_meta( 'processed_authors' );

		foreach ( $posts as $post ) {
			$this->import_post( $post );
		}
	}

	/**
	 * @param $post
	 *
	 * @todo Error handling
	 * @todo Implement process_menu_item
	 */
	protected function import_post( $post ) {
		$post = apply_filters( 'wp_import_post_data_raw', $post );
		if ( ! post_type_exists( $post['post_type'] ) ) {
			printf(
				__( 'Failed to import &#8220;%1$s&#8221;: Invalid post type %2$s', 'wordpress-importer' ),
				esc_html( $post['post_title'] ),
				esc_html( $post['post_type'] )
			);
			echo '<br />';
			do_action( 'wp_import_post_exists', $post );
			return;
		}

		if ( empty( $post['post_id'] ) ) {
			// Todo throw an error or log something. A post id is mandatory.
			return;
		}

		$processed_post = $this->importer->get_mapped_id( 'processed_post', $post['post_id'] );

		if ( $processed_post ) {
			return;
		}

		if ( 'auto-draft' === $post['status'] ) {
			return;
		}

		if ( 'nav_menu_item' === $post['post_type'] ) {
			//$this->process_menu_item( $post );
			return;
		}

		$post_type_object = get_post_type_object( $post['post_type'] );

		$post_exists = post_exists( $post['post_title'], '', $post['post_date'] );

		/**
		 * Filter ID of the existing post corresponding to post currently importing.
		 *
		 * Return 0 to force the post to be imported. Filter the ID to be something else
		 * to override which existing post is mapped to the imported post.
		 *
		 * @see post_exists()
		 * @since 0.6.2
		 *
		 * @param int   $post_exists  Post ID, or 0 if post did not exist.
		 * @param array $post         The post array to be inserted.
		 */
		$post_exists = apply_filters( 'wp_import_existing_post', $post_exists, $post );

		if ( $post_exists && get_post_type( $post_exists ) == $post['post_type'] ) {
			printf( __( '%s &#8220;%s&#8221; already exists.', 'wordpress-importer' ), $post_type_object->labels->singular_name, esc_html( $post['post_title'] ) );
			echo '<br />';
			$comment_post_ID = $post_id = $post_exists;
			$this->importer->set_mapping( 'processed_post', (int) $post['post_id'], (int) $post_exists );
		} else {
			$post_parent = (int) $post['post_parent'];
			if ( $post_parent ) {

				$processed_parent = $this->importer->get_mapped_id( 'processed_post', $post_parent );

				// if we already know the parent, map it to the new local ID
				if ( $processed_parent ) {
					$post_parent = $processed_parent;
					// otherwise record the parent for later
				} else {
					$this->post_orphans[ (int) $post['post_id'] ] = $post_parent;
					$post_parent                                  = 0;
				}
			}

			// map the post author
			$author = sanitize_user( $post['post_author'], true );
			if ( isset( $this->author_mapping[ $author ] ) ) {
				$author = $this->author_mapping[ $author ];
			} else {
				$author = (int) get_current_user_id();
			}

			$postdata = array(
				'import_id'      => $post['post_id'],
				'post_author'    => $author,
				'post_date'      => $post['post_date'],
				'post_date_gmt'  => $post['post_date_gmt'],
				'post_content'   => $post['post_content'],
				'post_excerpt'   => $post['post_excerpt'],
				'post_title'     => $post['post_title'],
				'post_status'    => $post['status'],
				'post_name'      => $post['post_name'],
				'comment_status' => $post['comment_status'],
				'ping_status'    => $post['ping_status'],
				'guid'           => $post['guid'],
				'post_parent'    => $post_parent,
				'menu_order'     => $post['menu_order'],
				'post_type'      => $post['post_type'],
				'post_password'  => $post['post_password'],
			);

			$original_post_ID = $post['post_id'];
			$postdata         = apply_filters( 'wp_import_post_data_processed', $postdata, $post );

			$postdata = wp_slash( $postdata );

			if ( 'attachment' == $postdata['post_type'] ) {
				$remote_url = ! empty( $post['attachment_url'] ) ? $post['attachment_url'] : $post['guid'];

				// try to use _wp_attached file for upload folder placement to ensure the same location as the export site
				// e.g. location is 2003/05/image.jpg but the attachment post_date is 2010/09, see media_handle_upload()
				$postdata['upload_date'] = $post['post_date'];
				if ( isset( $post['postmeta'] ) ) {
					foreach ( $post['postmeta'] as $meta ) {
						if ( $meta['key'] == '_wp_attached_file' ) {
							if ( preg_match( '%^[0-9]{4}/[0-9]{2}%', $meta['value'], $matches ) ) {
								$postdata['upload_date'] = $matches[0];
							}
							break;
						}
					}
				}

				$comment_post_ID = $post_id = $this->process_attachment( $postdata, $remote_url );
			} else {
				$comment_post_ID = $post_id = wp_insert_post( $postdata, true );
				do_action( 'wp_import_insert_post', $post_id, $original_post_ID, $postdata, $post );
			}

			if ( is_wp_error( $post_id ) ) {
				printf(
					__( 'Failed to import %s &#8220;%s&#8221;', 'wordpress-importer' ),
					$post_type_object->labels->singular_name,
					esc_html( $post['post_title'] )
				);
				if ( defined( 'IMPORT_DEBUG' ) && IMPORT_DEBUG ) {
					echo ': ' . $post_id->get_error_message();
				}
				echo '<br />';
				return;
			}

			if ( true === (bool) $post['is_sticky'] ) {
				stick_post( $post_id );
			}
		}

		// map pre-import ID to local ID
		$this->processed_posts[ (int) $post['post_id'] ] = (int) $post_id;

		if ( ! isset( $post['terms'] ) ) {
			$post['terms'] = array();
		}

		$post['terms'] = apply_filters( 'wp_import_post_terms', $post['terms'], $post_id, $post );

		// add categories, tags and other terms
		if ( ! empty( $post['terms'] ) ) {
			$terms_to_set = array();
			foreach ( $post['terms'] as $term ) {
				// back compat with WXR 1.0 map 'tag' to 'post_tag'
				$taxonomy    = ( 'tag' === $term['domain'] ) ? 'post_tag' : $term['domain'];
				$term_exists = term_exists( $term['slug'], $taxonomy );
				$term_id     = is_array( $term_exists ) ? $term_exists['term_id'] : $term_exists;
				if ( ! $term_id ) {
					$t = wp_insert_term( $term['name'], $taxonomy, array( 'slug' => $term['slug'] ) );
					if ( ! is_wp_error( $t ) ) {
						$term_id = $t['term_id'];
						do_action( 'wp_import_insert_term', $t, $term, $post_id, $post );
					} else {
						printf( __( 'Failed to import %s %s', 'wordpress-importer' ), esc_html( $taxonomy ), esc_html( $term['name'] ) );
						if ( defined( 'IMPORT_DEBUG' ) && IMPORT_DEBUG ) {
							echo ': ' . $t->get_error_message();
						}
						echo '<br />';
						do_action( 'wp_import_insert_term_failed', $t, $term, $post_id, $post );
						continue;
					}
				}
				$terms_to_set[ $taxonomy ][] = intval( $term_id );
			}

			foreach ( $terms_to_set as $tax => $ids ) {
				$tt_ids = wp_set_post_terms( $post_id, $ids, $tax );
				do_action( 'wp_import_set_post_terms', $tt_ids, $ids, $tax, $post_id, $post );
			}
			unset( $post['terms'], $terms_to_set );
		}

		if ( ! isset( $post['comments'] ) ) {
			$post['comments'] = array();
		}

		$post['comments'] = apply_filters( 'wp_import_post_comments', $post['comments'], $post_id, $post );

		// add/update comments
		if ( ! empty( $post['comments'] ) ) {
			$num_comments      = 0;
			$inserted_comments = array();
			foreach ( $post['comments'] as $comment ) {
				$comment_id                                    = $comment['comment_id'];
				$newcomments[ $comment_id ]['comment_post_ID'] = $comment_post_ID;
				$newcomments[ $comment_id ]['comment_author']  = $comment['comment_author'];
				$newcomments[ $comment_id ]['comment_author_email'] = $comment['comment_author_email'];
				$newcomments[ $comment_id ]['comment_author_IP']    = $comment['comment_author_IP'];
				$newcomments[ $comment_id ]['comment_author_url']   = $comment['comment_author_url'];
				$newcomments[ $comment_id ]['comment_date']         = $comment['comment_date'];
				$newcomments[ $comment_id ]['comment_date_gmt']     = $comment['comment_date_gmt'];
				$newcomments[ $comment_id ]['comment_content']      = $comment['comment_content'];
				$newcomments[ $comment_id ]['comment_approved']     = $comment['comment_approved'];
				$newcomments[ $comment_id ]['comment_type']         = $comment['comment_type'];
				$newcomments[ $comment_id ]['comment_parent']       = $comment['comment_parent'];
				$newcomments[ $comment_id ]['commentmeta']          = isset( $comment['commentmeta'] ) ? $comment['commentmeta'] : array();
				if ( isset( $this->processed_authors[ $comment['comment_user_id'] ] ) ) {
					$newcomments[ $comment_id ]['user_id'] = $this->processed_authors[ $comment['comment_user_id'] ];
				}
			}
			ksort( $newcomments );

			foreach ( $newcomments as $key => $comment ) {
				// if this is a new post we can skip the comment_exists() check
				if ( ! $post_exists || ! comment_exists( $comment['comment_author'], $comment['comment_date'] ) ) {
					if ( isset( $inserted_comments[ $comment['comment_parent'] ] ) ) {
						$comment['comment_parent'] = $inserted_comments[ $comment['comment_parent'] ];
					}

					$comment_data = wp_slash( $comment );
					unset( $comment_data['commentmeta'] ); // Handled separately, wp_insert_comment() also expects `comment_meta`.
					$comment_data = wp_filter_comment( $comment_data );

					$inserted_comments[ $key ] = wp_insert_comment( $comment_data );

					do_action( 'wp_import_insert_comment', $inserted_comments[ $key ], $comment, $comment_post_ID, $post );

					foreach ( $comment['commentmeta'] as $meta ) {
						$value = maybe_unserialize( $meta['value'] );

						add_comment_meta( $inserted_comments[ $key ], wp_slash( $meta['key'] ), wp_slash_strings_only( $value ) );
					}

					$num_comments++;
				}
			}
			unset( $newcomments, $inserted_comments, $post['comments'] );
		}

		if ( ! isset( $post['postmeta'] ) ) {
			$post['postmeta'] = array();
		}

		$post['postmeta'] = apply_filters( 'wp_import_post_meta', $post['postmeta'], $post_id, $post );

		// add/update post meta
		if ( ! empty( $post['postmeta'] ) ) {
			foreach ( $post['postmeta'] as $meta ) {
				$key   = apply_filters( 'import_post_meta_key', $meta['key'], $post_id, $post );
				$value = false;

				if ( '_edit_last' === $key ) {
					if ( isset( $this->processed_authors[ (int) $meta['value'] ] ) ) {
						$value = $this->processed_authors[ (int) $meta['value'] ];
					} else {
						$key = false;
					}
				}

				if ( $key ) {
					// export gets meta straight from the DB so could have a serialized string
					if ( ! $value ) {
						$value = maybe_unserialize( $meta['value'] );
					}

					add_post_meta( $post_id, wp_slash( $key ), wp_slash_strings_only( $value ) );

					do_action( 'import_post_meta', $post_id, $key, $value );

					// if the post has a featured image, take note of this in case of remap
					if ( '_thumbnail_id' === $key ) {
						$this->featured_images[ $post_id ] = (int) $value;
					}
				}
			}
		}
	}

	protected function add_remap_featured_image( $post_id, $value ) {

	}

	protected function parse( SimpleXMLElement $xml ) {

		$item = $xml->channel->item;

		$post = array(
			'post_title' => (string) $item->title,
			'guid'       => (string) $item->guid,
		);

		$dc                  = $item->children( 'http://purl.org/dc/elements/1.1/' );
		$post['post_author'] = (string) $dc->creator;

		$content              = $item->children( 'http://purl.org/rss/1.0/modules/content/' );
		$excerpt              = $item->children( $this->namespaces['excerpt'] );
		$post['post_content'] = (string) $content->encoded;
		$post['post_excerpt'] = (string) $excerpt->encoded;

		$wp                     = $item->children( $this->namespaces['wp'] );
		$post['post_id']        = (int) $wp->post_id;
		$post['post_date']      = (string) $wp->post_date;
		$post['post_date_gmt']  = (string) $wp->post_date_gmt;
		$post['comment_status'] = (string) $wp->comment_status;
		$post['ping_status']    = (string) $wp->ping_status;
		$post['post_name']      = (string) $wp->post_name;
		$post['status']         = (string) $wp->status;
		$post['post_parent']    = (int) $wp->post_parent;
		$post['menu_order']     = (int) $wp->menu_order;
		$post['post_type']      = (string) $wp->post_type;
		$post['post_password']  = (string) $wp->post_password;
		$post['is_sticky']      = (int) $wp->is_sticky;

		if ( isset( $wp->attachment_url ) ) {
			$post['attachment_url'] = (string) $wp->attachment_url;
		}

		foreach ( $item->category as $c ) {
			$att = $c->attributes();
			if ( isset( $att['nicename'] ) ) {
				$post['terms'][] = array(
					'name'   => (string) $c,
					'slug'   => (string) $att['nicename'],
					'domain' => (string) $att['domain'],
				);
			}
		}

		foreach ( $wp->postmeta as $meta ) {
			$post['postmeta'][] = array(
				'key'   => (string) $meta->meta_key,
				'value' => (string) $meta->meta_value,
			);
		}

		foreach ( $wp->comment as $comment ) {
			$meta = array();
			if ( isset( $comment->commentmeta ) ) {
				foreach ( $comment->commentmeta as $m ) {
					$meta[] = array(
						'key'   => (string) $m->meta_key,
						'value' => (string) $m->meta_value,
					);
				}
			}

			$post['comments'][] = array(
				'comment_id'           => (int) $comment->comment_id,
				'comment_author'       => (string) $comment->comment_author,
				'comment_author_email' => (string) $comment->comment_author_email,
				'comment_author_IP'    => (string) $comment->comment_author_IP,
				'comment_author_url'   => (string) $comment->comment_author_url,
				'comment_date'         => (string) $comment->comment_date,
				'comment_date_gmt'     => (string) $comment->comment_date_gmt,
				'comment_content'      => (string) $comment->comment_content,
				'comment_approved'     => (string) $comment->comment_approved,
				'comment_type'         => (string) $comment->comment_type,
				'comment_parent'       => (string) $comment->comment_parent,
				'comment_user_id'      => (int) $comment->comment_user_id,
				'commentmeta'          => $meta,
			);
		}

		return $post;
	}
}
