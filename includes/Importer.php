<?php

namespace ImporterExperiment;

use ImporterExperiment\Abstracts\Scheduler;
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

	public function create_jobs_from_wxr( $wxr_file_path ) {

		// Unschedule existing actions
		$this->scheduler->unschedule( $this->job_runner->get_hook_name() );

		$indexer = new WXR_Indexer();
		$indexer->parse( $wxr_file_path );
		$this->indexer = $indexer;

		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);

		if ( count( $terms ) ) {
			$term_id = $terms[0]->term_id;
			delete_term_meta( $term_id, 'job' );
			delete_term_meta( $term_id, 'file' );
			delete_term_meta( $term_id, 'file_checksum' );
		} else {
			$term    = wp_insert_term( 'jobs', self::TAXONOMY );
			$term_id = $term['term_id'];
		}

		add_term_meta( $term_id, 'file', $wxr_file_path );
		add_term_meta( $term_id, 'file_checksum', md5_file( $wxr_file_path ) );

		$total_items = 0;

		$total_items += $this->batch( 'wp:author', $term_id );
		$total_items += $this->batch( 'wp:category', $term_id );
		$total_items += $this->batch( 'item', $term_id );

		update_term_meta( $term_id, 'total', $total_items );
		update_term_meta( $term_id, 'processed', 0 );
	}

	protected function batch( $type, $term_id, $batch_size = 100 ) {

		$batch      = array();
		$item_count = $this->indexer->get_count( 'item' );
		$job_count  = 0;

		foreach ( $this->indexer->get_data( $type ) as $idx => $item ) {
			$batch[] = $item;
			$job_count++;
			if ( $idx === $item_count - 1 || count( $batch ) === $batch_size ) {

				$term_meta_value = array(
					'importer' => $this->type_map[ $type ],
					'objects'  => $batch,
				);

				// Store the objects to process as term meta
				$meta_id = add_term_meta( $term_id, 'job', $term_meta_value );

				$job_data  = array( 'meta_id' => $meta_id );
				$job_class = apply_filters( 'importer_experiment_wxr_job', self::WXR_JOB_CLASS );
				$this->scheduler->schedule( $this->job_runner->get_hook_name(), $job_class, $job_data );

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
			'job',
			array(
				'type'   => 'array',
				'single' => false,
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'state',
			array(
				'type'   => 'array',
				'single' => false,
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
