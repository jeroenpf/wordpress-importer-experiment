<?php

namespace ImporterExperiment;

use ImporterExperiment\Abstracts\Dispatcher;
use ImporterExperiment\Abstracts\Logger;
use ImporterExperiment\Loggers\CommentLogger;
use ImporterExperiment\StageJobs\InitializeImport;
use ImporterExperiment\StageJobRunner;

class Importer {

	const POST_TYPE = 'importer_import';

	/** @var Importer */
	private static $importer;

	/** @var Dispatcher */
	protected $dispatcher;

	/**
	 * @var Logger
	 */
	protected $logger;


	public function init() {
		// Register actions and taxonomies
		add_action( 'admin_init', array( $this, 'register_post_type' ) );

		// Load the scheduler
		$this->dispatcher = Dispatcher::instance();
		$this->dispatcher->init();

		$logger_class = apply_filters( 'wordpress_importer_logger_class', CommentLogger::class );
		$this->logger = new $logger_class();

		// JobRunner
		StageJobRunner::init();

	}

	/**
	 * @return Logger
	 */
	public function get_logger() {
		return $this->logger;
	}

	/**
	 * Create a new import instance for the given WXR file path.
	 *
	 * @param $wxr_file_path
	 *
	 * @return Import
	 *
	 * @throws ImporterException
	 */
	public function create_wxr_import( $wxr_file_path ) {
		return Import::create( $wxr_file_path, $this->dispatcher, $this->logger );
	}

	/**
	 * @param $post_id
	 *
	 * @return Import
	 *
	 * @throws ImporterException
	 */
	public function get_import_by_id( $post_id ) {
		return new Import( $post_id, $this->dispatcher, $this->logger );
	}


	public function register_post_type() {

		register_post_type(
			self::POST_TYPE,
			array(
				'public'       => false,
				'show_ui'      => false,
				'show_in_menu' => false,
				'show_in_rest' => false,
				'map_meta_cap' => true,
				'hierarchical' => false,
				'supports'     => array( 'title', 'editor', 'comments' ),
				'rewrite'      => false,
				'query_var'    => false,
				'can_export'   => false,
			)
		);

	}

	/**
	 * @return Importer
	 */
	public static function instance() {
		if ( empty( self::$importer ) ) {
			self::$importer = new self();
		}

		return self::$importer;
	}


}
