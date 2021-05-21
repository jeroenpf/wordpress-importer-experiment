<?php

namespace ImporterExperiment;

use ActionScheduler;
use ActionScheduler_Store;

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

			case 'status':
				if ( ! empty( $_FILES ) ) {
					$this->upload();
				}

				// todo: we need to validate the WXR
				// todo: we need to possibly deal with encoding issues

				$file = get_attached_file( get_option( self::EXPORT_FILE_OPTION ) );
				$this->importer->create_jobs_from_wxr( $file );

				include __DIR__ . '/../partials/status.php';

				break;

			default:
				include __DIR__ . '/../partials/start.php';
				break;

		}

	}

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

	public function setup_admin() {

		add_action( 'wp_ajax_wordpress_importer_progress', array( $this, 'get_status' ) );

		add_action( 'wp_ajax_wordpress_importer_run_jobs', array( $this, 'run_jobs' ) );

		if ( isset( $_GET['page'] ) && 'importer-experiment' === $_GET['page'] ) {
			wp_enqueue_script( 'substack-index-js', plugins_url( '/js/status.js', $this->plugin_file ) );
			wp_enqueue_style( 'substack-index-css', plugins_url( '/css/status.css', $this->plugin_file ) );
		}
	}

	public static function instance() {
		if ( empty( self::$admin ) ) {
			self::$admin = new self();
		}

		return self::$admin;
	}

}
