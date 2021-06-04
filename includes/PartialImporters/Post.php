<?php

namespace ImporterExperiment\PartialImporters;

// This file has dependencies on post.php and we need to make sure it is loaded.
require_once ABSPATH . '/wp-admin/includes/post.php';
require_once ABSPATH . '/wp-admin/includes/file.php';

use ImporterExperiment\Abstracts\PartialXMLImport;
use SimpleXMLElement;
use WP_Error;

class Post extends PartialXMLImport {

	const REMAP_COMMENT_TYPE = 'attachment_remap';

	protected $processed_authors = array();
	protected $author_mapping    = array();

	protected $timer = array();
	/**
	 * @var array
	 */
	private $url_remap;

	public function import() {
		$posts = apply_filters( 'wp_import_post', array( $this->data ) );

		$this->author_mapping    = $this->import->get_meta( 'author_mapping' );
		$this->processed_authors = $this->import->get_meta( 'processed_authors' );
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

		$processed_post = $this->get_post_id_by_wxr_id( $post['post_id'] );

		if ( $processed_post ) {
			return;
		}

		if ( 'auto-draft' === $post['status'] ) {
			return;
		}

		if ( 'nav_menu_item' === $post['post_type'] ) {
			//$this->process_menu_item( $post );
			// todo menu item
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
		$is_orphaned = false;

		if ( $post_exists && get_post_type( $post_exists ) == $post['post_type'] ) {
			printf( __( '%s &#8220;%s&#8221; already exists.', 'wordpress-importer' ), $post_type_object->labels->singular_name, esc_html( $post['post_title'] ) );
			echo '<br />';
			$comment_post_ID = $post_id = $post_exists;
			$this->set_post_wxr_id( (int) $post_exists, (int) $post['post_id'] );
		} else {
			$post_parent = (int) $post['post_parent'];

			if ( $post_parent ) {
				$processed_parent = $this->get_post_id_by_wxr_id( $post_parent );
				$post_parent      = $processed_parent ?: 0;
				$is_orphaned      = null === $processed_parent;
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

			if ( 'attachment' === $postdata['post_type'] ) {
				$comment_post_ID = $post_id = $this->handle_attachment( $postdata, $post );
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

		// Set the post to orphaned if a post with the $parent_id was not found.
		// Orphaned posts will be fixed later.
		if ( $is_orphaned ) {
			$this->set_post_orphaned( $post_id, $post_parent );
		}

		// map pre-import ID to local ID
		$this->set_post_wxr_id( (int) $post_id, (int) $post['post_id'] );

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
				$terms_to_set[ $taxonomy ][] = (int) $term_id;
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

		// Set the import ID as meta.
		$post['postmeta'][] = array(
			'key'   => 'import_id',
			'value' => $this->import->get_id(),
		);

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
						add_post_meta( $post_id, 'featured_image_wxr_id', (int) $value );
					}
				}
			}
		}
	}

	protected function handle_attachment( $postdata, $post ) {
		$remote_url = ! empty( $post['attachment_url'] ) ? $post['attachment_url'] : $post['guid'];

		// try to use _wp_attached file for upload folder placement to ensure the same location as the export site
		// e.g. location is 2003/05/image.jpg but the attachment post_date is 2010/09, see media_handle_upload()
		$postdata['upload_date'] = $post['post_date'];
		$postmeta                = isset( $post['postmeta'] ) ? $post['postmeta'] : array();

		foreach ( $postmeta as $meta ) {
			$data_regex = '%^[0-9]{4}/[0-9]{2}%';
			if ( '_wp_attached_file' === $meta['key'] && preg_match( $data_regex, $meta['value'], $matches ) ) {
				$postdata['upload_date'] = $matches[0];
				break;
			}
		}

		return $this->process_attachment( $postdata, $remote_url );
	}

	/**
	 * @todo Implement fetch attachment option
	 *
	 * @param $post
	 * @param $remote_url
	 *
	 * @return array|int|WP_Error
	 */
	protected function process_attachment( $post, $url ) {
		//      if ( ! $this->fetch_attachments ) {
		//          return new WP_Error(
		//              'attachment_processing_error',
		//              __( 'Fetching attachments is not enabled', 'wordpress-importer' )
		//          );
		//      }

		$base_url = $this->import->get_meta( 'base_site_url' );

		// if the URL is absolute, but does not contain address, then upload it assuming base_site_url
		if ( preg_match( '|^/[\w\W]+$|', $url ) ) {
			$url = rtrim( $base_url, '/' ) . $url;
		}

		$upload = $this->fetch_remote_file( $url, $post );
		if ( is_wp_error( $upload ) ) {
			return $upload;
		}

		$info = wp_check_filetype( $upload['file'] );
		if ( $info ) {
			$post['post_mime_type'] = $info['type'];
		} else {
			return new WP_Error( 'attachment_processing_error', __( 'Invalid file type', 'wordpress-importer' ) );
		}

		$post['guid'] = $upload['url'];

		// as per wp-admin/includes/upload.php
		$post_id = wp_insert_attachment( $post, $upload['file'] );
		wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $upload['file'] ) );

		// remap resized image URLs, works by stripping the extension and remapping the URL stub.
		if ( preg_match( '!^image/!', $info['type'] ) ) {
			$parts = pathinfo( $url );
			$name  = basename( $parts['basename'], ".{$parts['extension']}" ); // PATHINFO_FILENAME in PHP 5.2

			$parts_new = pathinfo( $upload['url'] );
			$name_new  = basename( $parts_new['basename'], ".{$parts_new['extension']}" );

			$this->url_remap[ $parts['dirname'] . '/' . $name ] = $parts_new['dirname'] . '/' . $name_new;
		}

		$this->save_url_remap();

		return $post_id;
	}

	/**
	 * Adds url mappings from the current attachment.
	 *
	 * @param $post_id
	 */
	protected function save_url_remap() {

		if ( empty( $this->url_remap ) ) {
			return;
		}

		foreach ( $this->url_remap as $old => $new ) {
			wp_insert_comment(
				array(
					'comment_post_ID' => $this->import->get_id(),
					'comment_content' => $old,
					'comment_type'    => self::REMAP_COMMENT_TYPE,
					'comment_meta'    => array(
						'remap_to' => $new,
					),
				)
			);
		}
	}

	/**
	 * Attempt to download a remote file attachment
	 *
	 * @param string $url URL of item to fetch
	 * @param array $post Attachment details
	 * @return array|WP_Error Local file location details on success, WP_Error otherwise
	 */
	function fetch_remote_file( $url, $post ) {
		// Extract the file name from the URL.
		$file_name = basename( parse_url( $url, PHP_URL_PATH ) );

		if ( ! $file_name ) {
			$file_name = md5( $url );
		}

		$tmp_file_name = wp_tempnam( $file_name );
		if ( ! $tmp_file_name ) {
			return new WP_Error( 'import_no_file', __( 'Could not create temporary file.', 'wordpress-importer' ) );
		}

		// Fetch the remote URL and write it to the placeholder file.
		$remote_response = wp_safe_remote_get(
			$url,
			array(
				'timeout'  => 300,
				'stream'   => true,
				'filename' => $tmp_file_name,
				'headers'  => array(
					'Accept-Encoding' => 'identity',
				),
			)
		);

		if ( is_wp_error( $remote_response ) ) {
			@unlink( $tmp_file_name );
			return new WP_Error(
				'import_file_error',
				sprintf(
				/* translators: 1: The WordPress error message. 2: The WordPress error code. */
					__( 'Request failed due to an error: %1$s (%2$s)', 'wordpress-importer' ),
					esc_html( $remote_response->get_error_message() ),
					esc_html( $remote_response->get_error_code() )
				)
			);
		}

		$remote_response_code = (int) wp_remote_retrieve_response_code( $remote_response );

		// Make sure the fetch was successful.
		if ( 200 !== $remote_response_code ) {
			@unlink( $tmp_file_name );
			return new WP_Error(
				'import_file_error',
				sprintf(
				/* translators: 1: The HTTP error message. 2: The HTTP error code. */
					__( 'Remote server returned the following unexpected result: %1$s (%2$s)', 'wordpress-importer' ),
					get_status_header_desc( $remote_response_code ),
					esc_html( $remote_response_code )
				)
			);
		}

		$headers = wp_remote_retrieve_headers( $remote_response );

		// Request failed.
		if ( ! $headers ) {
			@unlink( $tmp_file_name );
			return new WP_Error( 'import_file_error', __( 'Remote server did not respond', 'wordpress-importer' ) );
		}

		$filesize = (int) filesize( $tmp_file_name );

		if ( 0 === $filesize ) {
			@unlink( $tmp_file_name );
			return new WP_Error( 'import_file_error', __( 'Zero size file downloaded', 'wordpress-importer' ) );
		}

		if ( ! isset( $headers['content-encoding'] ) && isset( $headers['content-length'] ) && $filesize !== (int) $headers['content-length'] ) {
			@unlink( $tmp_file_name );
			return new WP_Error( 'import_file_error', __( 'Downloaded file has incorrect size', 'wordpress-importer' ) );
		}

		$max_size = (int) $this->max_attachment_size();
		if ( ! empty( $max_size ) && $filesize > $max_size ) {
			@unlink( $tmp_file_name );
			return new WP_Error( 'import_file_error', sprintf( __( 'Remote file is too large, limit is %s', 'wordpress-importer' ), size_format( $max_size ) ) );
		}

		// Override file name with Content-Disposition header value.
		if ( ! empty( $headers['content-disposition'] ) ) {
			$file_name_from_disposition = $this->get_filename_from_disposition( (array) $headers['content-disposition'] );
			if ( $file_name_from_disposition ) {
				$file_name = $file_name_from_disposition;
			}
		}

		// Set file extension if missing.
		$file_ext = pathinfo( $file_name, PATHINFO_EXTENSION );
		if ( ! $file_ext && ! empty( $headers['content-type'] ) ) {
			$extension = $this->get_file_extension_by_mime_type( $headers['content-type'] );
			if ( $extension ) {
				$file_name = "{$file_name}.{$extension}";
			}
		}

		// Handle the upload like _wp_handle_upload() does.
		$wp_filetype     = wp_check_filetype_and_ext( $tmp_file_name, $file_name );
		$ext             = empty( $wp_filetype['ext'] ) ? '' : $wp_filetype['ext'];
		$type            = empty( $wp_filetype['type'] ) ? '' : $wp_filetype['type'];
		$proper_filename = empty( $wp_filetype['proper_filename'] ) ? '' : $wp_filetype['proper_filename'];

		// Check to see if wp_check_filetype_and_ext() determined the filename was incorrect.
		if ( $proper_filename ) {
			$file_name = $proper_filename;
		}

		if ( ( ! $type || ! $ext ) && ! current_user_can( 'unfiltered_upload' ) ) {
			return new WP_Error( 'import_file_error', __( 'Sorry, this file type is not permitted for security reasons.', 'wordpress-importer' ) );
		}

		$uploads = wp_upload_dir( $post['upload_date'] );
		if ( ! ( $uploads && false === $uploads['error'] ) ) {
			return new WP_Error( 'upload_dir_error', $uploads['error'] );
		}

		// Move the file to the uploads dir.
		$file_name     = wp_unique_filename( $uploads['path'], $file_name );
		$new_file      = $uploads['path'] . "/$file_name";
		$move_new_file = copy( $tmp_file_name, $new_file );

		if ( ! $move_new_file ) {
			@unlink( $tmp_file_name );
			return new WP_Error( 'import_file_error', __( 'The uploaded file could not be moved', 'wordpress-importer' ) );
		}

		// Set correct file permissions.
		$stat  = stat( dirname( $new_file ) );
		$perms = $stat['mode'] & 0000666;
		chmod( $new_file, $perms );

		$upload = array(
			'file'  => $new_file,
			'url'   => $uploads['url'] . "/$file_name",
			'type'  => $wp_filetype['type'],
			'error' => false,
		);

		// keep track of the old and new urls so we can substitute them later
		$this->url_remap[ $url ]          = $upload['url'];
		$this->url_remap[ $post['guid'] ] = $upload['url']; // r13735, really needed?
		// keep track of the destination if the remote url is redirected somewhere else
		if ( isset( $headers['x-final-location'] ) && $headers['x-final-location'] != $url ) {
			$this->url_remap[ $headers['x-final-location'] ] = $upload['url'];
		}

		return $upload;
	}

	/**
	 * @param $id
	 *
	 * @return int|null
	 */
	protected function get_post_id_by_wxr_id( $id ) {

		$posts = get_posts(
			array(
				'meta_key'   => 'wxr_id',
				'meta_value' => $id,
				'fields'     => 'ids',
			)
		);

		return count( $posts ) ? $posts[0] : null;

	}

	protected function set_post_wxr_id( $post_id, $wxr_id ) {
		add_post_meta( $post_id, 'wxr_id', $wxr_id );
	}

	protected function set_post_orphaned( $post_id, $wxr_parent_id ) {
		add_post_meta( $post_id, 'orphaned_wxr_id', $wxr_parent_id );
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

	protected function max_attachment_size() {
		return apply_filters( 'import_attachment_size_limit', 0 );
	}

	/**
	 * Parses filename from a Content-Disposition header value.
	 *
	 * As per RFC6266:
	 *
	 *     content-disposition = "Content-Disposition" ":"
	 *                            disposition-type *( ";" disposition-parm )
	 *
	 *     disposition-type    = "inline" | "attachment" | disp-ext-type
	 *                         ; case-insensitive
	 *     disp-ext-type       = token
	 *
	 *     disposition-parm    = filename-parm | disp-ext-parm
	 *
	 *     filename-parm       = "filename" "=" value
	 *                         | "filename*" "=" ext-value
	 *
	 *     disp-ext-parm       = token "=" value
	 *                         | ext-token "=" ext-value
	 *     ext-token           = <the characters in token, followed by "*">
	 *
	 * @since 0.7.0
	 *
	 * @see WP_REST_Attachments_Controller::get_filename_from_disposition()
	 *
	 * @link http://tools.ietf.org/html/rfc2388
	 * @link http://tools.ietf.org/html/rfc6266
	 *
	 * @param string[] $disposition_header List of Content-Disposition header values.
	 * @return string|null Filename if available, or null if not found.
	 */
	protected function get_filename_from_disposition( $disposition_header ) {
		// Get the filename.
		$filename = null;

		foreach ( $disposition_header as $value ) {
			$value = trim( $value );

			if ( strpos( $value, ';' ) === false ) {
				continue;
			}

			list( $type, $attr_parts ) = explode( ';', $value, 2 );

			$attr_parts = explode( ';', $attr_parts );
			$attributes = array();

			foreach ( $attr_parts as $part ) {
				if ( strpos( $part, '=' ) === false ) {
					continue;
				}

				list( $key, $value ) = explode( '=', $part, 2 );

				$attributes[ trim( $key ) ] = trim( $value );
			}

			if ( empty( $attributes['filename'] ) ) {
				continue;
			}

			$filename = trim( $attributes['filename'] );

			// Unquote quoted filename, but after trimming.
			if ( $filename[0] === '"' && $filename[ strlen( $filename ) - 1 ] === '"' ) {
				$filename = substr( $filename, 1, -1 );
			}
		}

		return $filename;
	}

	/**
	 * Retrieves file extension by mime type.
	 *
	 * @since 0.7.0
	 *
	 * @param string $mime_type Mime type to search extension for.
	 * @return string|null File extension if available, or null if not found.
	 */
	protected function get_file_extension_by_mime_type( $mime_type ) {

		$mime_types = wp_get_mime_types();
		$map        = array_flip( $mime_types );

		// Some types have multiple extensions, use only the first one.
		foreach ( $map as $type => $extensions ) {
			$map[ $type ] = strtok( $extensions, '|' );
		}

		return isset( $map[ $mime_type ] ) ? $map[ $mime_type ] : null;
	}

}
