<?php

namespace ImporterExperiment;

use ImporterExperiment\Abstracts\Scheduler;
use ImporterExperiment\Abstracts\JobRunner;
use WP_Term;

class ImportStage {

	const STATUS_PENDING   = 'pending';
	const STATUS_SCHEDULED = 'scheduled';
	const STATUS_RUNNING   = 'running';
	const STATUS_COMPLETED = 'completed';
	const STATUS_HOLD      = 'hold';

	/**
	 * Taxonomy of the import
	 *
	 * @var string
	 */
	protected $taxonomy;

	/**
	 * @var Importer
	 */
	protected $importer;
	/**
	 * @var WP_Term
	 */
	private $stage_term;

	/**
	 * Creates a new stage or updates existing stage.
	 *
	 * @param $name
	 * @param array $depends_on
	 *
	 * @return static
	 * @throws \Exception
	 */
	public static function create( $name ) {
		$importer = Importer::instance();
		// If stage does not exist, create it
		$stages = get_term_by( 'name', 'stages', $importer::TAXONOMY );

		if ( ! $stages instanceof WP_Term ) {
			throw new \Exception( 'Stages not set' );
		}

		$terms = get_terms(
			array(
				'parent'     => $stages->term_id,
				'hide_empty' => false,
				'taxonomy'   => $importer::TAXONOMY,
				'name'       => $name,
			)
		);

		if ( count( $terms ) ) {
			$stage_id = $terms[0]->term_id;
		} else {
			$stage = wp_insert_term(
				$name,
				$importer::TAXONOMY,
				array(
					'parent' => $stages->term_id,
				)
			);

			$stage_id = $stage['term_id'];
		}

		// A new stage should not be on hold until explicitly started.
		update_term_meta( $stage_id, 'status', self::STATUS_HOLD );

		return new static( get_term_by( 'id', $stage_id, $importer::TAXONOMY ) );
	}

	/**
	 * Get stage instance
	 *
	 * @param WP_Term $stage
	 */
	public function __construct( WP_Term $stage ) {

		$importer       = Importer::instance();
		$this->taxonomy = $importer::TAXONOMY;
		$this->importer = $importer;

		$this->stage_term = $stage;

	}

	/**
	 * Set the stage(s) the current stage depends on. The stage will not be executed until
	 * all the dependencies have completed.
	 *
	 * Dependencies can only be added if the stage already exists.
	 *
	 * @param string[] $dependencies A list of stage names the current stage depends on.
	 *
	 * @throws \Exception
	 */
	public function depends_on( $dependencies = array() ) {

		$terms = get_terms(
			array(
				'hide_empty' => false,
				'parent'     => $this->get_parent_id(),
				'name'       => $dependencies,
				'taxonomy'   => $this->taxonomy,
			)
		);

		$terms = array_map(
			static function( WP_Term $term ) {
				return $term->name;
			},
			$terms
		);

		$diff = array_diff( $dependencies, $terms );

		if ( count( $diff ) ) {
			throw new \Exception( sprintf( "The stages '%s' do not exist, dependencies can't be set.", implode( ', ', $diff ) ) );
		}

		update_term_meta( $this->stage_term->term_id, 'state_depends_on', $dependencies );
	}

	/**
	 * Set the status of the stage to pending.
	 */
	public function release() {
		$this->set_status( self::STATUS_PENDING );
	}

	public function start() {
		$this->set_status( self::STATUS_RUNNING );
	}

	public function complete() {
		$this->set_status( self::STATUS_COMPLETED );
	}

	public function set_status( $status ) {
		update_term_meta( $this->stage_term->term_id, 'status', $status );
	}

	public function get_status() {
		return get_term_meta( $this->stage_term->term_id, 'status', true );
	}

	public function dependencies_met() {

		$dependencies = get_term_meta( $this->stage_term->term_id, 'state_depends_on', true );

		if ( empty( $dependencies ) ) {
			return true;
		}

		// Get all completes dependencies.
		$terms = get_terms(
			array(
				'hide_empty' => false,
				'parent'     => $this->get_parent_id(),
				'name'       => $dependencies,
				'taxonomy'   => $this->taxonomy,
				'meta_key'   => 'status',
				'meta_value' => ImportStage::STATUS_COMPLETED,
			)
		);

		$terms = array_map(
			static function( WP_Term $term ) {
				return $term->name;
			},
			$terms
		);

		// Check that all dependencies are in the fetched completed dependencies.
		return 0 === count( array_diff( $dependencies, $terms ) );
	}

	/**
	 * Schedule all the jobs added to this stage.
	 *
	 * @todo set a limit on how many jobs will be scheduled each time
	 *       to prevent timeouts when scheduling too many jobs.
	 */
	public function schedule_jobs() {

		if ( ! $this->dependencies_met() ) {
			return;
		}

		$jobs      = get_terms(
			array(
				'hide_empty' => false,
				'parent'     => $this->get_id(),
				'taxonomy'   => $this->taxonomy,
				'meta_key'   => 'status',
				'meta_value' => ImportStage::STATUS_PENDING,
			)
		);
		$scheduler = Scheduler::instance();
		$runner    = JobRunner::instance();

		// If the stage has no jobs, complete it.
		if ( ! count( $jobs ) ) {
			$this->complete();
		}

		foreach ( $jobs as $job ) {

			$class = get_term_meta( $job->term_id, 'job_class', true );
			// Slashes can't be stored in meta so they were replaced with forward-slashes
			// and need to be converted back.
			$class = str_replace( '/', '\\', $class );
			update_term_meta( $job->term_id, 'status', ImportStage::STATUS_SCHEDULED );
			$args['stage_job'] = $job->term_id;
			$scheduler->schedule( $runner->get_hook_name(), $class, $args );
		}

	}


	/**
	 * @return int
	 */
	public function get_id() {
		return $this->stage_term->term_id;
	}

	public function get_parent_id() {
		return $this->stage_term->parent;
	}

	/**
	 * Add new job to the stage
	 *
	 * @param $class
	 * @param array $args
	 *
	 * @throws \Exception
	 */
	public function add_job( $class, $args = array() ) {
		// Do not allow adding a job if the stage is already completed

		// Meta data slashes get stripped so we need to convert them.
		$class = str_replace( '\\', '/', $class );

		$status = get_term_meta( $this->stage_term->term_id, 'status' );

		if ( self::STATUS_COMPLETED === $status ) {
			throw new \Exception( 'Adding a job to a completed stage is not allowed.' );
		}

		// Make a unique key for the job to prevent duplicates
		$job_key = md5( $class . serialize( $args ) );

		$existing_job = get_terms(
			array(
				'name'       => $job_key,
				'hide_empty' => false,
				'taxonomy'   => $this->taxonomy,
				'parent'     => $this->stage_term->term_id,
			)
		);

		if ( count( $existing_job ) ) {
			$job_id = $existing_job[0]->term_id;
		} else {
			$job    = wp_insert_term(
				$job_key,
				$this->taxonomy,
				array(
					'parent' => $this->stage_term->term_id,
				)
			);
			$job_id = $job['term_id'];
		}

		add_term_meta( $job_id, 'status', self::STATUS_PENDING );
		add_term_meta( $job_id, 'job_arguments', $args );
		add_term_meta( $job_id, 'job_class', $class );

	}


}
