<?php

namespace ImporterExperiment;

use ImporterExperiment\Abstracts\Scheduler;
use ImporterExperiment\Jobs\InitializeImportJob;
use ImporterExperiment\Abstracts\JobRunner;

class Importer {

	const TAXONOMY = 'importer_experiment';

	/** @var Importer */
	private static $importer;

	/** @var Scheduler */
	protected $scheduler;

	/**
	 * @var \WP_Term
	 */
	protected $import_term;

	/**
	 * @var Abstracts\JobRunner|mixed
	 */
	private $job_runner;
	/**
	 * @var array|false|\WP_Error|\WP_Term|null
	 */
	private $mapping_term;

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

	public function create_wxr_import( $wxr_file_path ) {

		// Clean previous import
		$this->clean_previous_import();

		// Prepare import
		$this->prepare_import( $wxr_file_path );

	}

	public function initialize_wxr_import() {
		// Run the initialize import job (will parse the WXR and create jobs)
		$stage = ImportStage::create( 'initialization' );
		$stage->add_job( InitializeImportJob::class );
		$stage->release();
		$stage->schedule_jobs();
	}

	public function set_import_meta( $key, $value ) {
		$import_term = $this->get_import_term();
		update_term_meta( $import_term->term_id, $key, $value );
	}

	public function get_import_meta( $key ) {
		$import_term = $this->get_import_term();
		return get_term_meta( $import_term->term_id, $key, true );
	}

	public function set_mapping( $type, $wxr_id, $new_id ) {
		$term_name = sprintf( '%s_map_%s_to_%s', $type, $wxr_id, $new_id );
		$term      = wp_insert_term(
			$term_name,
			self::TAXONOMY,
			array(
				'parent' => $this->get_mapping_term()->term_id,
			)
		);

		add_term_meta( $term['term_id'], 'mapping_wxr_id', $wxr_id );
		add_term_meta( $term['term_id'], 'mapping_new_id', $new_id );
		add_term_meta( $term['term_id'], 'mapping_type', $type );
	}

	public function get_mapped_id( $type, $wxr_id ) {

		$meta_query_args = array(
			array(
				'key'     => 'mapping_wxr_id',
				'value'   => $wxr_id,
				'compare' => '=',
			),
			array(
				'key'     => 'mapping_type',
				'value'   => $type,
				'compare' => '=',
			),
		);

		$terms = get_terms(
			array(
				'hide_empty' => false,
				'taxonomy'   => self::TAXONOMY,
				'parent'     => $this->get_mapping_term()->term_id,
				'meta_query' => $meta_query_args,
			)
		);

		if ( ! count( $terms ) ) {
			return null;
		}

		return get_term_meta( $terms[0]->term_id, 'mapping_new_id', true );
	}


	public function get_mapping_term() {
		if ( empty( $this->mapping_term ) ) {
			$this->mapping_term = get_term_by( 'name', 'mappings', self::TAXONOMY );
		}

		return $this->mapping_term;
	}

	/**
	 * @return array|false|\WP_Error|\WP_Term|null
	 */
	protected function get_import_term() {
		if ( empty( $this->import_term ) ) {
			$this->import_term = get_term_by( 'name', 'import', self::TAXONOMY );
		}

		return $this->import_term;
	}


	protected function prepare_import( $wxr_file_path ) {
		$import_term   = wp_insert_term( 'import', self::TAXONOMY );
		$stages_term   = wp_insert_term( 'stages', self::TAXONOMY );
		$mappings_term = wp_insert_term( 'mappings', self::TAXONOMY );

		$this->set_import_meta( 'file', $wxr_file_path );
		$this->set_import_meta( 'file_checksum', md5_file( $wxr_file_path ) );
		$this->set_import_meta( 'status', ImportStage::STATUS_PENDING );
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
			'author_mapping',
			array(
				'type'   => 'array',
				'single' => true,
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'mapping_wxr_id',
			array(
				'type'   => 'string',
				'single' => true,
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'mapping_new_id',
			array(
				'type'   => 'string',
				'single' => true,
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'mapping_type',
			array(
				'type'   => 'array',
				'single' => true,
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'processed_authors',
			array(
				'type'   => 'array',
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
