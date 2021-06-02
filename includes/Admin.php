<?php

namespace ImporterExperiment;

use ActionScheduler;
use ActionScheduler_Store;
use ImporterExperiment\Abstracts\Scheduler;
use ImporterExperiment\PartialImporters\Author;

class Admin {

	const EXPORT_FILE_OPTION = 'importer_experiment_wxr_file';


	/** @var Admin  */
	protected static $admin = null;

	/**
	 * @var string
	 */
	protected $plugin_file;

	/**
	 * @var Importer
	 */
	protected $importer;

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
				$import = new Import( $_GET['import_id'], Scheduler::instance() );
				include __DIR__ . '/../partials/status.php';
				break;

			default:
				include __DIR__ . '/../partials/start.php';
				break;

		}

	}

	protected function settings_page() {

		$wxr_file = get_attached_file( get_option( self::EXPORT_FILE_OPTION ) );
		$import   = $this->importer->create_wxr_import( $wxr_file );

		// Get authors
		$indexer = new WXR_Indexer();
		$indexer->parse( $wxr_file, array( 'wp:author', 'wp:wxr_version', 'wp:base_site_url', 'wp:base_blog_url' ) );

		$authors       = $this->get_authors_from_wxr( $indexer, $import );
		$wxr_version   = $this->get_wxr_meta( 'wp:wxr_version', $indexer, $wxr_file );
		$base_url      = $this->get_wxr_meta( 'wp:base_site_url', $indexer, $wxr_file );
		$base_blog_url = $this->get_wxr_meta( 'wp:base_blog_url', $indexer, $wxr_file );

		$base_url = $base_url ?: '';
		$import->set_meta( 'wxr_version', $wxr_version );
		$import->set_meta( 'base_site_url', $base_url );
		$import->set_meta( 'base_blog_url', $base_blog_url ?: $base_url );

		$can_fetch_attachments = $this->allow_fetch_attachments();
		$can_create_users      = $this->allow_create_users();

		include __DIR__ . '/../partials/settings.php';
	}

	/**
	 * Get a list of authors from the WXR.
	 *
	 * @param WXR_Indexer $indexer
	 * @param Import $import
	 *
	 * @return array
	 */
	protected function get_authors_from_wxr( WXR_Indexer $indexer, Import $import ) {
		$authors = array();
		foreach ( $indexer->get_data( 'wp:author' ) as $author ) {
			$partial_importer = new Author( $import );
			$partial_importer->process( $author );
			$author = $partial_importer->get_data();

			$authors[ $author['author_login'] ] = $partial_importer->get_data();
		}

		return $authors;
	}

	protected function get_wxr_meta( $tag, WXR_Indexer $indexer, $wxr_file ) {

		$object         = $indexer->get_data( $tag )->current();
		$partial_reader = new PartialXMLReader();
		$xml            = $partial_reader->object_to_simplexml( $object, $wxr_file );
		$meta           = $xml->xpath( sprintf( '/rss/channel/%s', $tag ) );
		return isset( $meta[0] ) ? (string) $meta[0] : null;
	}

	/**
	 * This method maps authors. It is copied from the old version of the wordpress-importer
	 * and needs refactoring...
	 * @param Import $import
	 */
	protected function set_author_mapping( Import $import ) {
		if ( ! isset( $_POST['imported_authors'] ) ) {
			return;
		}

		$indexer = new WXR_Indexer();
		$indexer->parse( $import->get_meta( 'wxr_file' ), array( 'wp:author' ) );
		$authors           = $this->get_authors_from_wxr( $indexer, $import );
		$wxr_version       = $import->get_meta( 'wxr_version' );
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

		$import->set_meta( 'author_mapping', $author_mapping );
		$import->set_meta( 'processed_authors', $processed_authors );

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

		wp_send_json(
			array(
				'status'    => 'running',
				'total'     => 0,
				'processed' => 0,
			)
		);
		exit();
	}

	public function run_jobs() {

		set_time_limit( 0 );
		// Set the store

		apply_filters(
			'action_scheduler_store_class',
			function() {
				return ActionScheduler_Store::DEFAULT_CLASS;
			}
		);

		add_filter(
			'action_scheduler_queue_runner_time_limit',
			function() {
				return 30;
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

		$import = new Import( $_POST['import_id'], Scheduler::instance() );

		$response = array(
			'import' => array(
				'id'   => $import->get_id(),
				'meta' => $this->parse_meta( $import->get_meta() ),
			),
			'stages' => array(),
		);

		foreach ( $import->get_stages() as $stage ) {
			$response['stages'][] = array(
				'id'          => $stage->get_id(),
				'meta'        => array(
					'name'       => $stage->get_meta( 'name' ),
					'status'     => $stage->get_meta( 'status' ),
					'depends_on' => $stage->get_meta( 'state_depends_on' ),
				),
				'jobs'        => $this->format_jobs( $stage ),
				'total_jobs'  => $stage->get_jobs_count(),
				'active_jobs' => $stage->get_jobs_count( true ),
			);
		}

		wp_send_json( $response );

		exit();
	}

	protected function format_jobs( ImportStage $stage ) {

		$out = array();

		foreach ( $stage->get_jobs( array(), 10 ) as $job ) {
			$out[] = array(
				'id'   => $job->comment_ID,
				'name' => $job->comment_content,
				'meta' => $this->parse_meta( get_comment_meta( $job->comment_ID ) ),
			);
		}

		return $out;

	}

	protected function parse_meta( $metas ) {

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

			// Using VueJS and lodash while we are experimenting.
			wp_enqueue_script( 'vue', 'https://cdn.jsdelivr.net/npm/vue@2/dist/vue.js' );
			wp_enqueue_script( 'lodash', 'https://cdn.jsdelivr.net/npm/lodash@4.17.21/lodash.min.js' );
		}

		$this->register_actions();
	}

	protected function register_actions() {

		if ( ! empty( $_FILES ) && 'upload' === $_GET['action'] && 'importer-experiment' === $_GET['page'] ) {
			$this->upload();
			wp_safe_redirect( add_query_arg( array( 'action' => 'settings' ) ) );
		}

		if ( ! empty( $_POST ) && 'start-import' === $_GET['action'] && 'importer-experiment' === $_GET['page'] ) {

			$import = $this->importer->get_import_by_id( $_GET['import_id'] );
			$this->set_author_mapping( $import );
			$import->start();

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
