<?php

namespace ImporterExperiment;

use ImporterExperiment\Abstracts\Job;
use ImporterExperiment\Abstracts\Scheduler;
use ImporterExperiment\Abstracts\JobRunner;
use WP_Comment;

class ImportStage {

	const STATUS_PENDING   = 'pending';
	const STATUS_SCHEDULED = 'scheduled';
	const STATUS_RUNNING   = 'running';
	const STATUS_COMPLETED = 'completed';
	const STATUS_HOLD      = 'hold';

	const STAGE_COMMENT_TYPE = 'import_stage';
	const JOB_COMMENT_TYPE   = 'import_stage_job';

	/**
	 * @var Import
	 */
	private $import;
	/**
	 * @var WP_Comment
	 */
	private $stage;

	/**
	 * Creates a new stage or updates existing stage.
	 *
	 * @param $name
	 * @param Import $import
	 *
	 * @return static
	 */
	public static function get_or_create( $name, Import $import ) {

		$stage_comments = get_comments(
			array(
				'type'       => self::STAGE_COMMENT_TYPE,
				'post_id'    => $import->get_id(),
				'meta_key'   => 'name',
				'meta_value' => $name,
			)
		);

		if ( empty( $stage_comments ) ) {
			$comment_id = wp_insert_comment(
				array(
					'comment_post_ID' => $import->get_id(),
					'comment_agent'   => 'wordpress-importer',
					'comment_content' => $name,
					'comment_type'    => self::STAGE_COMMENT_TYPE,
					'comment_meta'    => array(
						'name'   => $name, // Added for searching
						'status' => self::STATUS_HOLD,
					),
				)
			);

			$stage = get_comment( $comment_id );
		} else {
			$stage = $stage_comments[0];
		}

		return new static( $stage, $import );
	}

	public static function get_by_id( $id, Import $import ) {

		$stage = get_comment( $id );

		return new static( $stage, $import );

	}

	/**
	 * Get stage instance
	 *
	 * @param WP_Comment $stage
	 * @param Import $import
	 */
	public function __construct( WP_Comment $stage, Import $import ) {
		$this->import = $import;
		$this->stage  = $stage;
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

		$comments = get_comments(
			array(
				'type'    => self::STAGE_COMMENT_TYPE,
				'post_id' => $this->import->get_id(),
			)
		);

		$stages = array_map(
			static function( WP_Comment $comment ) {
				return $comment->comment_content;
			},
			$comments
		);

		$diff = array_diff( $dependencies, $stages );

		if ( count( $diff ) ) {
			throw new \Exception( sprintf( "The stages '%s' do not exist, dependencies can't be set.", implode( ', ', $diff ) ) );
		}

		update_comment_meta( $this->stage->comment_ID, 'state_depends_on', $dependencies );
	}

	/**
	 * Set the status of the stage to pending and schedule the jobs in the stage.
	 */
	public function release() {
		$this->set_status( self::STATUS_PENDING );
		$this->schedule_jobs();
	}

	public function set_status( $status ) {
		$this->set_meta( 'status', $status );
	}

	public function get_status() {
		return $this->get_meta( 'status' );
	}

	public function set_meta( $key, $value ) {
		return update_comment_meta( $this->stage->comment_ID, $key, $value );
	}

	public function get_meta( $key = '' ) {
		return get_comment_meta( $this->stage->comment_ID, $key, true );
	}

	public function set_final_stage( $is_final = true ) {
		$this->set_meta( 'final_stage', true );
	}

	public function dependencies_met() {

		$dependencies = $this->get_meta( 'state_depends_on' );

		// Check if this is a final stage and all non-final stages have completed.
		if ( $this->is_final_stage() && ! $this->can_run_final_stages() ) {
			return false;
		}

		if ( empty( $dependencies ) ) {
			return true;
		}

		$completed_stages = array_map(
			static function( ImportStage $stage ) {
				return $stage->get_name();
			},
			$this->import->get_stages( array( self::STATUS_COMPLETED ) )
		);

		// Check that all dependencies are in the fetched completed dependencies.
		return 0 === count( array_diff( $dependencies, $completed_stages ) );
	}

	public function get_name() {
		return $this->stage->comment_content;
	}

	public function is_final_stage() {
		return '1' === $this->get_meta( 'final_stage' );
	}


	/**
	 * Verify that all non-final stages have completed.
	 *
	 * If all non-final stages have completed, final stages are allowed to run.
	 *
	 * @return bool
	 */
	protected function can_run_final_stages() {

		$args = array(
			'post_id'    => $this->import->get_id(),
			'type'       => static::STAGE_COMMENT_TYPE,
			'count'      => true,
			'meta_query' => array(
				array(
					'relation' => 'OR',
					array(
						'key'     => 'final_stage',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'   => 'final_stage',
						'value' => false,
					),
				),
				array(
					'key'     => 'status',
					'value'   => self::STATUS_COMPLETED,
					'compare' => '!=',
				),
			),

		);

		return get_comments( $args ) <= 0;
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

		if ( ! $this->has_jobs() ) {
			$this->set_status( self::STATUS_COMPLETED );
			return;
		}

		$jobs = $this->get_jobs();

		$scheduler = Scheduler::instance();

		foreach ( $jobs as $job ) {

			$class = get_comment_meta( $job->comment_ID, 'job_class', true );
			// Slashes can't be stored in meta so they were replaced with forward-slashes
			// and need to be converted back.
			$class             = str_replace( '/', '\\', $class );
			$args['stage_job'] = $job->comment_ID;

			$id = $scheduler->schedule( JobRunner::ACTION_HOOK, $class, $args );
			update_comment_meta( $job->comment_ID, 'status', self::STATUS_SCHEDULED );
			update_comment_meta( $job->comment_ID, 'job_id', $id );
		}

	}

	/**
	 * @param array $statuses
	 *
	 * @return WP_Comment[]
	 */
	public function get_jobs( $statuses = array( Job::STATUS_PENDING ), $limit = 0 ) {

		$args = array(
			'parent' => $this->get_id(),
			'type'   => self::JOB_COMMENT_TYPE,
		);

		if ( $limit ) {
			$args['number'] = $limit;
		}

		if ( ! empty( $statuses ) ) {
			$args = array_merge(
				$args,
				array(
					'meta_key'   => 'status',
					'meta_value' => $statuses,
				)
			);
		}

		/** @var WP_Comment[] $jobs */
		return get_comments( $args );
	}

	/**
	 * Does the stage have jobs?
	 *
	 * @param bool $ignore_completed
	 *
	 * @return bool
	 */
	public function has_jobs( $ignore_completed = false ) {

		return $this->get_jobs_count( $ignore_completed ) > 0;
	}

	/**
	 * @param false $ignore_completed
	 *
	 * @return int
	 */
	public function get_jobs_count( $ignore_completed = false ) {
		$args = array(
			'type'   => self::JOB_COMMENT_TYPE,
			'parent' => $this->get_id(),
			'count'  => true,
		);

		if ( $ignore_completed ) {
			$args = array_merge(
				$args,
				array(
					'meta_key'     => 'status',
					'meta_value'   => self::STATUS_COMPLETED,
					'meta_compare' => '!=',
				)
			);
		}

		return get_comments( $args );
	}

	/**
	 * @return int
	 */
	public function get_id() {
		return $this->stage->comment_ID;
	}

	/**
	 * Add new job to the stage and returns the id of the job (comment).
	 *
	 * @param $class
	 * @param array $args
	 *
	 * @return int
	 *
	 * @throws \Exception
	 */
	public function add_job( $class, $args = array() ) {
		// Do not allow adding a job if the stage is already completed

		// Meta data slashes get stripped so we need to convert them.
		$class = str_replace( '\\', '/', $class );

		if ( self::STATUS_COMPLETED === $this->get_status() ) {
			throw new Exception( 'Adding a job to a completed stage is not allowed.' );
		}

		// Make a unique key for the job to prevent duplicates
		$job_key = wp_generate_uuid4();

		$job_id = wp_insert_comment(
			array(
				'comment_post_ID' => $this->import->get_id(),
				'comment_parent'  => $this->get_id(),
				'comment_type'    => self::JOB_COMMENT_TYPE,
				'comment_agent'   => 'wordpress-importer',
				'comment_content' => $job_key,
				'comment_meta'    => array(
					'job_arguments' => $args,
					'status'        => self::STATUS_PENDING,
					'job_class'     => $class,
				),
			)
		);

		return $job_id;
	}


}
