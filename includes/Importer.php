<?php

namespace ImporterExperiment;

use ImporterExperiment\Abstracts\Scheduler;
use ImporterExperiment\Jobs\InitializeImportJob;
use ImporterExperiment\JobRunner;

class Importer {

	const POST_TYPE = 'importer_import';

	/** @var Importer */
	private static $importer;

	/** @var Scheduler */
	protected $scheduler;


	public function init() {
		// Register actions and taxonomies
		add_action( 'admin_init', array( $this, 'register_post_type' ) );

		// Load the scheduler
		$this->scheduler = Scheduler::instance();
		$this->scheduler->init();

		// JobRunner
		JobRunner::init();

	}

	/**
	 * Create a new import instance for the given WXR file path.
	 *
	 * @param $wxr_file_path
	 *
	 * @return Import
	 *
	 * @throws Exception
	 */
	public function create_wxr_import( $wxr_file_path ) {
		return Import::create( $wxr_file_path, $this->scheduler );
	}

	/**
	 * @param $post_id
	 *
	 * @return Import
	 *
	 * @throws Exception
	 */
	public function get_import_by_id( $post_id ) {
		return new Import( $post_id, $this->scheduler );
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
