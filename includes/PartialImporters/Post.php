<?php

namespace ImporterExperiment\PartialImporters;

// This file has dependencies on post.php and we need to make sure it is loaded.
require_once ABSPATH . '/wp-admin/includes/post.php';
require_once ABSPATH . '/wp-admin/includes/file.php';
require_once ABSPATH . '/wp-admin/includes/image.php';

use ImporterExperiment\Abstracts\Logger;
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

	private $is_orphaned = false;

	protected $is_new_post = false;

	/**
	 * @var Logger
	 */
	protected $logger;

	public function import() {
		$posts = apply_filters( 'wp_import_post', array( $this->data ) );

		$this->author_mapping    = $this->import->get_meta( 'author_mapping' );
		$this->processed_authors = $this->import->get_meta( 'processed_authors' );

		$this->logger = $this->import->get_logger();

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
			$msg = __( 'Failed to import &#8220;%1$s&#8221;: Invalid post type %2$s', 'wordpress-importer' );
			$this->logger->error( sprintf( $msg, $post['post_title'], $post['post_type'] ) );
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

		$post_id = $this->handle_post( $post );

		if ( is_wp_error( $post_id ) ) {
			$post_type_object = get_post_type_object( $post['post_type'] );
			$msg              = __( 'Failed to import %s &#8220;%s&#8221;', 'wordpress-importer' );
			$msg              = sprintf( $msg, $post_type_object->labels->singular_name, esc_html( $post['post_title'] ) );
			$context          = defined( 'IMPORT_DEBUG' ) && IMPORT_DEBUG
				? array( 'error_message', $post_id->get_error_message() )
				: array();

			$this->logger->error( $msg, $context );
			return;
		}

		$post_id = (int) $post_id;

		// Set meta-data on the post that is used for processing later on.
		$this->set_post_import_meta( $post, $post_id );

		// Add terms to the post (if any).
		$this->handle_post_terms( $post, $post_id );

		// Add comments to the post (if any).
		$this->handle_comments( $post, $post_id );

		// Add postmeta to the post (if any).
		$this->handle_post_meta( $post, $post_id );
	}

	/**
	 * Return a post id or error if creating the post was unsuccessful.
	 *
	 * Will create a post or return the ID of an existing post.
	 *
	 * @param $post
	 *
	 * @return int|WP_Error
	 */
	protected function handle_post( $post ) {

		$existsing_post_id = $this->get_existing_post_id( $post );
		$post_id           = $existsing_post_id ?: $this->handle_new_post( $post );

		// Stick the post.
		if ( true === (bool) $post['is_sticky'] ) {
			stick_post( $post_id );
		}

		// If the post is existing and of type attachment, we need to
		// make sure the urls will be remapped later.
		if ( $existsing_post_id && 'attachment' === $post['post_type'] ) {
			$this->set_existing_post_attachment_url_mapping( $post, $post_id );
		}

		return $post_id;
	}

	/**
	 * Set the mapping for an existing attachment so that it will be processed
	 * during the remapping phase.
	 *
	 * @param $post
	 * @param $post_id
	 */
	protected function set_existing_post_attachment_url_mapping( $post, $post_id ) {
		$attachment_url = $this->sanitize_attachment_url( $post );
		$attachment     = get_post( $post_id, 'ARRAY_A' );
		$this->register_url_mapping( $attachment_url, $attachment, $post['guid'] );
	}

	/**
	 * @param $post
	 *
	 * @return mixed|void|null
	 */
	protected function get_existing_post_id( $post ) {
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

		$exists = $post_exists && get_post_type( $post_exists ) === $post['post_type']
			? $post_exists
			: null;

		// Log it.
		if ( $exists ) {
			$post_type_object = get_post_type_object( $post['post_type'] );
			$msg              = sprintf( __( '%s &#8220;%s&#8221; already exists.', 'wordpress-importer' ), $post_type_object->labels->singular_name, esc_html( $post['post_title'] ) );
			$this->logger->notice( $msg );
		}

		return $exists;
	}

	/**
	 * @param $post
	 *
	 * @return int|WP_Error
	 */
	protected function handle_new_post( $post ) {

		$this->is_new_post = true;

		$postdata = $this->get_post_data( $post );

		return 'attachment' === $postdata['post_type']
			? $this->handle_attachment( $postdata, $post )
			: $this->add_new_post( $postdata, $post );
	}

	/**
	 * @param $postdata
	 * @param $post
	 *
	 * @return int|WP_Error
	 */
	protected function add_new_post( $postdata, $post ) {
		$post_id = wp_insert_post( $postdata, true );
		do_action( 'wp_import_insert_post', $post_id, $post['post_id'], $postdata, $post );

		return $post_id;
	}

	protected function get_post_data( $post ) {

		$data = array(
			'import_id'      => $post['post_id'],
			'post_author'    => $this->get_author_id( $post ),
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
			'post_parent'    => $this->get_post_parent( $post ),
			'menu_order'     => $post['menu_order'],
			'post_type'      => $post['post_type'],
			'post_password'  => $post['post_password'],
		);

		$postdata = apply_filters( 'wp_import_post_data_processed', $data, $post );

		return wp_slash( $postdata );
	}

	protected function get_post_parent( $post ) {

		$post_parent = (int) $post['post_parent'];

		if ( $post_parent ) {
			$processed_parent  = $this->get_post_id_by_wxr_id( $post_parent );
			$post_parent       = $processed_parent ?: 0;
			$this->is_orphaned = null === $processed_parent;
		}

		return $post_parent;

	}

	protected function handle_post_terms( $post, $post_id ) {
		if ( ! isset( $post['terms'] ) ) {
			$post['terms'] = array();
		}

		$post['terms'] = apply_filters( 'wp_import_post_terms', $post['terms'], $post_id, $post );

		if ( empty( $post['terms'] ) ) {
			return;
		}

		// add categories, tags and other terms

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
					$msg     = sprintf( __( 'Failed to import %s %s', 'wordpress-importer' ), esc_html( $taxonomy ), esc_html( $term['name'] ) );
					$context = defined( 'IMPORT_DEBUG' ) && IMPORT_DEBUG
						? array( 'error_message' => $t->get_error_message() )
						: array();

					$this->logger->error( $msg, $context );
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
		unset( $terms_to_set );

	}

	protected function handle_comments( $post, $post_id ) {
		if ( ! isset( $post['comments'] ) ) {
			$post['comments'] = array();
		}

		$post['comments'] = apply_filters( 'wp_import_post_comments', $post['comments'], $post_id, $post );

		if ( empty( $post['comments'] ) ) {
			return;
		}

		// add/update comments
		$inserted_comments = array();
		foreach ( $post['comments'] as $comment ) {
			$comment_id                                    = $comment['comment_id'];
			$newcomments[ $comment_id ]['comment_post_ID'] = $post_id;
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
			if ( ! $this->is_new_post || ! comment_exists( $comment['comment_author'], $comment['comment_date'] ) ) {
				if ( isset( $inserted_comments[ $comment['comment_parent'] ] ) ) {
					$comment['comment_parent'] = $inserted_comments[ $comment['comment_parent'] ];
				}

				$comment_data = wp_slash( $comment );
				unset( $comment_data['commentmeta'] ); // Handled separately, wp_insert_comment() also expects `comment_meta`.
				$comment_data = wp_filter_comment( $comment_data );

				$inserted_comments[ $key ] = wp_insert_comment( $comment_data );

				do_action( 'wp_import_insert_comment', $inserted_comments[ $key ], $comment, $post_id, $post );

				foreach ( $comment['commentmeta'] as $meta ) {
					$value = maybe_unserialize( $meta['value'] );

					add_comment_meta( $inserted_comments[ $key ], wp_slash( $meta['key'] ), wp_slash_strings_only( $value ) );
				}
			}
		}
		unset( $newcomments, $inserted_comments, $post['comments'] );

	}

	protected function handle_post_meta( $post, $post_id ) {
		if ( ! isset( $post['postmeta'] ) ) {
			$post['postmeta'] = array();
		}

		$post['postmeta'] = apply_filters( 'wp_import_post_meta', $post['postmeta'], $post_id, $post );

		// add/update post meta
		if ( empty( $post['postmeta'] ) ) {
			return;
		}

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

	/**
	 * @param $post Post array from the WXR.
	 */
	protected function get_author_id( $post ) {
		// map the post author
		$author = sanitize_user( $post['post_author'], true );

		$id = (int) get_current_user_id();

		if ( isset( $this->author_mapping[ $author ] ) ) {
			$id = $this->author_mapping[ $author ];
		}

		return $id;
	}

	protected function handle_attachment( $postdata, $post ) {
		$remote_url = $this->sanitize_attachment_url( $post );

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
	 * @param $post
	 *
	 * @return string
	 *
	 * @todo Return null if we could not get url, probably log an error if an attachment post
	 *       has no url that we can use.
	 */
	protected function sanitize_attachment_url( $post ) {
		$url = ! empty( $post['attachment_url'] ) ? $post['attachment_url'] : $post['guid'];

		// if the URL is absolute, but does not contain address, then upload it assuming base_site_url
		if ( preg_match( '|^/[\w\W]+$|', $url ) ) {
			$base_url = $this->import->get_meta( 'base_site_url' );
			$url      = rtrim( $base_url, '/' ) . $url;
		}

		return $url;
	}

	/**
	 * @todo Implement fetch attachment option
	 *
	 * @param $post
	 * @param $remote_url
	 *
	 * @return int|WP_Error
	 */
	protected function process_attachment( $post, $url ) {
		//      if ( ! $this->fetch_attachments ) {
		//          return new WP_Error(
		//              'attachment_processing_error',
		//              __( 'Fetching attachments is not enabled', 'wordpress-importer' )
		//          );
		//      }

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

		$wxr_guid     = $post['guid'];
		$post['guid'] = $upload['url'];

		// as per wp-admin/includes/upload.php
		$post_id = wp_insert_attachment( $post, $upload['file'] );
		wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $upload['file'] ) );

		$this->register_url_mapping( $url, $post, $wxr_guid );

		return $post_id;
	}

	/**
	 * Register and saves the URL mapping for attachments.
	 *
	 * @param $original_url
	 * @param $post
	 * @param null $wxr_guid
	 */
	protected function register_url_mapping( $original_url, $post, $wxr_guid = null ) {
		// keep track of the old and new urls so we can substitute them later
		$this->url_remap[ $original_url ] = $post['guid'];

		if ( $wxr_guid ) {
			$this->url_remap[ $wxr_guid ] = $post['guid']; // r13735, really needed?
		}

		// remap resized image URLs, works by stripping the extension and remapping the URL stub.
			$this->remap_resized_images( $post, $original_url );

		// If we have a guid from the WXR, we also want to add it to the list of
		// image url remappings, stripping the extension.
		if ( $wxr_guid && $wxr_guid !== $original_url ) {
			$this->remap_resized_images( $post, $wxr_guid );
		}

		$this->save_url_remap();
	}

	/**
	 * Remap resized image URLs, works by stripping the extension and remapping the URL stub.
	 * @param $post
	 * @param $original_url
	 */
	protected function remap_resized_images( $post, $original_url ) {
		if ( preg_match( '!^image/!', $post['post_mime_type'] ) ) {
			$parts = pathinfo( $original_url );
			$name  = basename( $parts['basename'], ".{$parts['extension']}" ); // PATHINFO_FILENAME in PHP 5.2

			$parts_new = pathinfo( $post['guid'] );
			$name_new  = basename( $parts_new['basename'], ".{$parts_new['extension']}" );

			$this->url_remap[ $parts['dirname'] . '/' . $name ] = $parts_new['dirname'] . '/' . $name_new;
		}
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
				'fields'     => 'ids',
				'meta_query' => array(
					array(
						'key'   => 'wxr_id',
						'value' => $id,
					),
					array(
						'key'   => 'import_id',
						'value' => $this->import->get_id(),
					),
				),
			)
		);

		return count( $posts ) ? $posts[0] : null;

	}

	protected function set_post_import_meta( $post, $post_id ) {
		$wxr_id = (int) $post['post_id'];

		add_post_meta( $post_id, 'import_id', $this->import->get_id(), true );
		add_post_meta( $post_id, 'wxr_id', $wxr_id, true );

		if ( $this->is_orphaned ) {
			add_post_meta( $post_id, 'orphaned_wxr_id', (int) $post['post_parent'], true );
		}

		$this->save_url_remap();
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
