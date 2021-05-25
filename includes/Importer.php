<?php

namespace ImporterExperiment;

use ImporterExperiment\Abstracts\Scheduler;
use ImporterExperiment\Jobs\InitializeImportJob;
use ImporterExperiment\Jobs\WXRImportJob;
use ImporterExperiment\Abstracts\JobRunner;

class Importer {


	const WXR_JOB_CLASS = WXRImportJob::class;

	const TAXONOMY = 'importer_experiment';

	protected $type_map = array(
		'wp:author'   => 'author',
		'item'        => 'post',
		'wp:category' => 'category',
		'wp:tag'      => 'tag',
		'wp:term'     => 'term',
	);

	/** @var Importer */
	private static $importer;

	/** @var Scheduler */
	protected $scheduler;

	/**
	 * @var WXR_Indexer
	 */
	private $indexer;
	/**
	 * @var Abstracts\JobRunner|mixed
	 */
	private $job_runner;

	public function init() {
		// Register actions and taxonomies
		add_action( 'admin_init', array( $this, 'register_taxonomy' ) );

		// Load the scheduler
		$this->scheduler = Scheduler::instance();
		$this->scheduler->init();

		// JobRunner
		$this->job_runner = JobRunner::instance();
		$this->job_runner->init();

	}

	public function import_from_wxr( $wxr_file_path ) {

		// Clean previous import
		$this->clean_previous_import();

		// Prepare import
		$this->prepare_import( $wxr_file_path );

		// Create the jobs
		$objects_count = $this->create_jobs( $wxr_file_path );

		// Start the import (will run as a job)
		$this->scheduler->schedule( $this->job_runner->get_hook_name(), InitializeImportJob::class );
	}

	protected function create_jobs( $wxr_file_path ) {
		$indexer = new WXR_Indexer();
		$indexer->parse( $wxr_file_path );
		$this->indexer = $indexer;

		$stages = array(
			'authors'    => array(
				'type' => 'wp:author',
			),
			'categories' => array(
				'type' => 'wp:category',
			),
			'terms'      => array(
				'type' => 'wp:term',
			),
			'posts'      => array(
				'type'       => 'item',
				'depends_on' => array( 'authors', 'categories', 'terms' ),
			),
		);

		$total_objects = 0;

		foreach ( $stages as $stage => $settings ) {
			$new_stage = ImportStage::create( $stage );
			if ( ! empty( $settings['depends_on'] ) ) {
				$new_stage->depends_on( $settings['depends_on'] );
			}
			$total_objects += $this->batch( $settings['type'], $new_stage );
			$new_stage->release();
		}

		return $total_objects;
	}

	protected function prepare_import( $wxr_file_path ) {
		$import_term = wp_insert_term( 'import', self::TAXONOMY );
		$stages_term = wp_insert_term( 'stages', self::TAXONOMY );
		add_term_meta( $import_term['term_id'], 'file', $wxr_file_path );
		add_term_meta( $import_term['term_id'], 'file_checksum', md5_file( $wxr_file_path ) );
	}

	protected function clean_previous_import() {
		// Unschedule existing actions
		$this->scheduler->unschedule( $this->job_runner->get_hook_name() );

		// Delete all terms in the taxonomy
		$terms = get_terms(
			array(
				'hide_empty' => false,
				'taxonomy'   => self::TAXONOMY,
			)
		);

		foreach ( $terms as $term ) {
			wp_delete_term( $term->term_id, self::TAXONOMY );
		}
	}

	protected function batch( $type, ImportStage $stage, $batch_size = 100 ) {

		$batch      = array();
		$item_count = $this->indexer->get_count( $type );
		$job_count  = 0;

		foreach ( $this->indexer->get_data( $type ) as $idx => $item ) {
			$batch[] = $item;
			$job_count++;
			if ( $idx === $item_count - 1 || count( $batch ) === $batch_size ) {

				$job_args = array(
					'importer' => $this->type_map[ $type ],
					'objects'  => $batch,
				);

				// Store the objects to process as term meta
				$job_class = apply_filters( 'importer_experiment_wxr_job', self::WXR_JOB_CLASS );
				$stage->add_job( $job_class, $job_args );

				$batch = array();
			}
		}

		return $job_count;
	}


	public function register_taxonomy() {
		$args = array(
			'hierarchical'      => true, // make it hierarchical (like categories)
			'show_ui'           => false,
			'show_admin_column' => false,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'importer-experiment' ),
		);
		register_taxonomy( self::TAXONOMY, array( 'user' ), $args );

		register_term_meta(
			self::TAXONOMY,
			'file',
			array(
				'type'   => 'string',
				'single' => true,
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'file_checksum',
			array(
				'type'   => 'string',
				'single' => true,
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'status',
			array(
				'type'   => 'array',
				'single' => true,
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'state_depends_on',
			array(
				'type'   => 'array',
				'single' => true,
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'job_arguments',
			array(
				'type'   => 'array',
				'single' => true,
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'job_class',
			array(
				'type'   => 'string',
				'single' => true,
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'state',
			array(
				'type'   => 'array',
				'single' => true,
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'total',
			array(
				'type'   => 'int',
				'single' => true,
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'processed',
			array(
				'type'   => 'int',
				'single' => true,
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
