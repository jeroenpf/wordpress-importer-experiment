<?php

namespace ImporterExperiment;

use ActionScheduler;
use ActionScheduler_Store;
use http\Client\Request;
use ImporterExperiment\PartialImporters\Author;
use ImporterExperiment\PartialImporters\WXRVersion;

class Admin {

	const EXPORT_FILE_OPTION = 'importer_experiment_wxr_file';


	/** @var Admin  */
	private static $admin = null;

	/**
	 * @var string
	 */
	private $plugin_file;

	/**
	 * @var Importer
	 */
	private $importer;

	public function init( $plugin_file ) {

		$this->plugin_file = $plugin_file;
		// Instantiate the importer
		$this->importer = Importer::instance();
		$this->importer->init();

		add_action( 'admin_menu', array( $this, 'setup_menu' ) );
		add_action( 'admin_init', array( $this, 'setup_admin' ) );
	}

	public function run() {

		$action = isset( $_GET['action'] ) ? $_GET['action'] : null;

		switch ( $action ) {

			case 'settings':
				$this->settings_page();
				break;

			case 'status':
				include __DIR__ . '/../partials/status.php';

				break;

			default:
				include __DIR__ . '/../partials/start.php';
				break;

		}

	}

	protected function settings_page() {

		$wxr_file = get_attached_file( get_option( self::EXPORT_FILE_OPTION ) );
		$this->importer->create_wxr_import( $wxr_file );

		// Get authors
		$indexer = new WXR_Indexer();
		$indexer->parse( $wxr_file, array( 'wp:author', 'wp:wxr_version' ) );

		$authors     = $this->get_authors_from_wxr( $indexer );
		$wxr_version = $this->get_wxr_version( $indexer );

		$can_fetch_attachments = $this->allow_fetch_attachments();
		$can_create_users      = $this->allow_create_users();

		include __DIR__ . '/../partials/settings.php';

	}

	protected function get_authors_from_wxr( WXR_Indexer $indexer ) {
		$authors = array();
		foreach ( $indexer->get_data( 'wp:author' ) as $author ) {
			$partial_importer = new Author( $this->importer );
			$partial_importer->process( $author );
			$author = $partial_importer->get_data();

			$authors[ $author['author_login'] ] = $partial_importer->get_data();
		}

		return $authors;
	}

	protected function get_wxr_version( WXR_Indexer $indexer ) {
		$wxr_version  = $indexer->get_data( 'wp:wxr_version' )->current();
		$wxr_importer = new WXRVersion( $this->importer );
		$wxr_importer->process( $wxr_version );
		return $wxr_importer->get_data()['version'];
	}

	/**
	 * This method maps authors. It is copied from the old version of the wordpress-importer
	 * and needs refactoring...
	 *
	 */
	protected function get_author_mapping() {
		if ( ! isset( $_POST['imported_authors'] ) ) {
			return;
		}

		$indexer = new WXR_Indexer();
		$indexer->parse( $this->importer->get_import_meta( 'file' ), array( 'wp:author', 'wp:wxr_version' ) );
		$authors           = $this->get_authors_from_wxr( $indexer );
		$wxr_version       = $this->get_wxr_version( $indexer );
		$create_users      = $this->allow_create_users();
		$author_mapping    = array();
		$processed_authors = array();

		foreach ( (array) $_POST['imported_authors'] as $i => $old_login ) {
			// Multisite adds strtolower to sanitize_user. Need to sanitize here to stop breakage in process_posts.
			$santized_old_login = sanitize_user( $old_login, true );
			$old_id             = isset( $authors[ $old_login ]['author_id'] ) ? (int) $authors[ $old_login ]['author_id'] : false;

			if ( ! empty( $_POST['user_map'][ $i ] ) ) {
				$user = get_userdata( (int) $_POST['user_map'][ $i ] );
				if ( isset( $user->ID ) ) {
					if ( $old_id ) {
						$processed_authors[ $old_id ] = $user->ID;
					}
					$author_mapping[ $santized_old_login ] = $user->ID;
				}
			} elseif ( $create_users ) {
				if ( ! empty( $_POST['user_new'][ $i ] ) ) {
					$user_id = wp_create_user( $_POST['user_new'][ $i ], wp_generate_password() );
				} elseif ( '1.0' !== $wxr_version ) {
					$user_data = array(
						'user_login'   => $old_login,
						'user_pass'    => wp_generate_password(),
						'user_email'   => isset( $authors[ $old_login ]['author_email'] ) ? $authors[ $old_login ]['author_email'] : '',
						'display_name' => $authors[ $old_login ]['author_display_name'],
						'first_name'   => isset( $authors[ $old_login ]['author_first_name'] ) ? $authors[ $old_login ]['author_first_name'] : '',
						'last_name'    => isset( $authors[ $old_login ]['author_last_name'] ) ? $authors[ $old_login ]['author_last_name'] : '',
					);
					$user_id   = wp_insert_user( $user_data );
				}

				if ( ! is_wp_error( $user_id ) ) {
					if ( $old_id ) {
						$processed_authors[ $old_id ] = $user_id;
					}
					$author_mapping[ $santized_old_login ] = $user_id;
				} else {
					printf( __( 'Failed to create new user for %s. Their posts will be attributed to the current user.', 'wordpress-importer' ), esc_html( $authors[ $old_login ]['author_display_name'] ) );
					if ( defined( 'IMPORT_DEBUG' ) && IMPORT_DEBUG ) {
						echo ' ' . $user_id->get_error_message();
					}
					echo '<br />';
				}
			}

			// failsafe: if the user_id was invalid, default to the current user
			if ( ! isset( $author_mapping[ $santized_old_login ] ) ) {
				if ( $old_id ) {
					$processed_authors[ $old_id ] = (int) get_current_user_id();
				}
				$author_mapping[ $santized_old_login ] = (int) get_current_user_id();
			}
		}

		foreach ( $author_mapping as $wxr => $new ) {
			$this->importer->set_mapping( 'author', $wxr, $new );
		}

		foreach ( $processed_authors as $wxr => $new ) {
			$this->importer->set_mapping( 'processed_author', $wxr, $new );
		}

	}

	/**
	 * Decide whether or not the importer is allowed to create users.
	 * Default is true, can be filtered via import_allow_create_users
	 *
	 * @return bool True if creating users is allowed
	 */
	protected function allow_create_users() {
		return apply_filters( 'import_allow_create_users', true );
	}

	/**
	 * Decide whether or not the importer should attempt to download attachment files.
	 * Default is true, can be filtered via import_allow_fetch_attachments. The choice
	 * made at the import options screen must also be true, false here hides that checkbox.
	 *
	 * @return bool True if downloading attachments is allowed
	 */
	protected function allow_fetch_attachments() {
		return apply_filters( 'import_allow_fetch_attachments', true );
	}

	/**
	 *
	 * @todo Error handling
	 */
	protected function upload() {
		check_admin_referer( 'import-upload' );
		$file = wp_import_handle_upload();

		update_option( self::EXPORT_FILE_OPTION, $file['id'] );
	}

	/**
	 * Get the import status
	 *
	 * @return array
	 */
	public function get_status() {

		$importer = Importer::instance();
		$terms    = get_terms(
			array(
				'taxonomy'   => $importer::TAXONOMY,
				'hide_empty' => false,
			)
		);

		if ( ! count( $terms ) ) {
			wp_send_json(
				array(
					'status' => 'uninitialized',
				)
			);
			exit();
		}

		$term = $terms[0];

		$total     = get_term_meta( $term->term_id, 'total', true );
		$processed = get_term_meta( $term->term_id, 'processed', true );

		wp_send_json(
			array(
				'status'    => 'running',
				'total'     => $total,
				'processed' => $processed,
			)
		);
		exit();
	}

	public function run_jobs() {

		// Set the store

		apply_filters(
			'action_scheduler_store_class',
			function() {
				return ActionScheduler_Store::DEFAULT_CLASS;
			}
		);

		$processed_actions = ActionScheduler::runner()->run( 'ImporterExperiment' );

		wp_send_json(
			array(
				'processed_actions' => $processed_actions,
			)
		);

		exit();
	}

	public function setup_menu() {
		add_management_page(
			'Importer Experiment',
			'Importer Experiment',
			'manage_options',
			'importer-experiment',
			array( $this, 'run' ),
			100
		);
	}

	public function get_debug() {

		wp_send_json( $this->get_terms_tree() );

		exit();
	}

	protected function get_terms_tree( $parent = 0 ) {

		$terms = get_terms(
			array(
				'hide_empty' => false,
				'taxonomy'   => Importer::TAXONOMY,
				'parent'     => $parent,
				'orderby'    => 'term_id',
			)
		);

		$out = array();

		foreach ( $terms as $term ) {
			$out[ $term->slug ] = array(
				'meta'     => $this->parse_term_meta( get_term_meta( $term->term_id, '', true ) ),
				'children' => $this->get_terms_tree( $term->term_id ),
				'name'     => $term->name,
				'id'       => $term->term_id,
			);
		}

		return $out;

	}

	protected function parse_term_meta( $metas ) {

		$out = array();

		foreach ( $metas as $key => $meta ) {

			$values = array();
			foreach ( $meta as $value ) {
				$values[] = maybe_unserialize( $value );
			}

			$out[ $key ] = 1 === count( $values ) ? $values[0] : $values;

		}

		return $out;
	}

	public function setup_admin() {

		add_action( 'wp_ajax_wordpress_importer_progress', array( $this, 'get_status' ) );
		add_action( 'wp_ajax_wordpress_importer_run_jobs', array( $this, 'run_jobs' ) );
		add_action( 'wp_ajax_wordpress_importer_get_debug', array( $this, 'get_debug' ) );

		if ( isset( $_GET['page'] ) && 'importer-experiment' === $_GET['page'] ) {
			wp_enqueue_script( 'substack-index-js', plugins_url( '/js/status.js', $this->plugin_file ) );
			wp_enqueue_style( 'substack-index-css', plugins_url( '/css/status.css', $this->plugin_file ) );
			wp_enqueue_script( 'vue', 'https://cdn.jsdelivr.net/npm/vue@2/dist/vue.js' );
		}

		$this->register_actions();
	}

	protected function register_actions() {

		if ( ! empty( $_FILES ) && 'upload' === $_GET['action'] && 'importer-experiment' === $_GET['page'] ) {
			$this->upload();
			wp_safe_redirect( add_query_arg( array( 'action' => 'settings' ) ) );
		}

		if ( ! empty( $_POST ) && 'start-import' === $_GET['action'] && 'importer-experiment' === $_GET['page'] ) {
			$this->get_author_mapping();
			$this->importer->initialize_wxr_import();
			wp_safe_redirect( add_query_arg( array( 'action' => 'status' ) ) );
		}

	}

	public static function instance() {
		if ( empty( self::$admin ) ) {
			self::$admin = new self();
		}

		return self::$admin;
	}

}
