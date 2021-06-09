<?php

namespace ImporterExperiment;

use ImporterExperiment\Interfaces\Logger;
use ImporterExperiment\Interfaces\StageJobRunner;
use ImporterExperiment\Interfaces\Dispatcher;
use ImporterExperiment\StageJobs\InitializeImport;
use WP_Comment;
use WP_Post;

class Import {


	const STATUS_PENDING = 'pending';
	const STATUS_RUNNING = 'running';
	const STATUS_DONE    = 'completed';

	/**
	 * @var Dispatcher
	 */
	protected $dispatcher;

	/**
	 * @var WP_Post
	 */
	protected $post;

	/**
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Create a new import and return the created instance.
	 *
	 * @param $wxr_file_path
	 * @param Dispatcher $dispatcher
	 * @param Logger $logger
	 *
	 * @return static
	 *
	 * @todo we probably don't need the scheduler so remove it (if possible).
	 */
	public static function create( $wxr_file_path, Dispatcher $dispatcher, Logger $logger ) {

		$post_id = wp_insert_post(
			array(
				'post_type'    => Importer::POST_TYPE,
				'post_title'   => sprintf( 'import-%s', wp_generate_uuid4() ),
				'post_content' => 'Import',
				'meta_input'   => array(
					'wxr_file'          => $wxr_file_path,
					'wxr_file_checksum' => md5_file( $wxr_file_path ),
					'status'            => self::STATUS_PENDING,
				),
			)
		);

		return new static( $post_id, $dispatcher, $logger );
	}


	/**
	 * Import constructor.
	 *
	 * @param int $post_id
	 * @param Dispatcher $dispatcher
	 * @param Logger $logger
	 */
	public function __construct( $post_id, Dispatcher $dispatcher, Logger $logger ) {
		$this->dispatcher = $dispatcher;
		$this->logger     = $logger;

		$this->logger->set_import( $this );

		$this->post = get_post( $post_id );

		if ( ! $this->post instanceof WP_Post ) {
			throw new ImporterException( __( 'Invalid post id', 'wordpress-importer' ) );
		}
	}

	/**
	 * Start the import.
	 */
	public function start() {
		// Run the initialize import job (will parse the WXR and create jobs)
		$stage = ImportStage::get_or_create( 'initialization', $this );
		$stage->add_job( InitializeImport::class );

		$stage->release();
		$stage->dispatch_jobs();

		$this->set_status( self::STATUS_RUNNING );
	}

	/**
	 * Returns the ID of the Post associated with the current import.
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->post->ID;
	}

	public function set_wxr_file() {

	}

	public function set_status( $status ) {

		if ( ! in_array(
			$status,
			array(
				self::STATUS_PENDING,
				self::STATUS_RUNNING,
				self::STATUS_DONE,
			),
			true
		) ) {
			throw new ImporterException( __( 'Invalid import status.', 'wordpress-importer' ) );
		}

		$this->set_meta( 'status', $status );
	}

	/**
	 * Set meta on the import.
	 *
	 * @param $key
	 * @param $value
	 */
	public function set_meta( $key, $value ) {
		update_post_meta( $this->post->ID, $key, $value );
	}

	/**
	 * Delete meta from the import.
	 *
	 * @param $key
	 */
	public function delete_meta( $key ) {
		delete_post_meta( $this->get_id(), $key );
	}

	/**
	 * Get meta from the import.
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function get_meta( $key = '' ) {
		return get_post_meta( $this->get_id(), $key, true );
	}


	/**
	 * @param array $include_statuses
	 *
	 * @return ImportStage[]
	 */
	public function get_stages( $include_statuses = array() ) {

		$args = array(
			'post_id' => $this->get_id(),
			'type'    => ImportStage::STAGE_COMMENT_TYPE,
			'orderby' => 'comment_ID',
			'order'   => 'ASC',
		);

		if ( ! empty( $include_statuses ) ) {
			$args = array_merge(
				$args,
				array(
					'meta_key'     => 'status',
					'meta_value'   => $include_statuses,
					'meta_compare' => 'IN',
				)
			);
		}

		$stages = get_comments( $args );

		return array_map(
			function( WP_Comment $stage ) {
				return new ImportStage( $stage, $this );
			},
			$stages
		);

	}

	/**
	 * Get an instance of the logger.
	 *
	 * @return Logger
	 */
	public function get_logger() {
		return $this->logger;
	}

}
