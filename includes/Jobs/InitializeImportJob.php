<?php

namespace ImporterExperiment\Jobs;

use ImporterExperiment\Exception;
use ImporterExperiment\ImportStage;
use ImporterExperiment\Abstracts\Job;
use ImporterExperiment\WXR_Indexer;

/**
 * Class InitializeImportJob
 *
 * The initialize job initializes an import by registering the jobs and setting up
 * the import.
 *
 * @package ImporterExperiment\Jobs
 */
class InitializeImportJob extends Job {

	/**
	 * @var WXR_Indexer;
	 */
	protected $indexer;

	protected $type_map = array(
		'wp:author'   => 'author',
		'item'        => 'post',
		'wp:category' => 'category',
		'wp:tag'      => 'tag',
		'wp:term'     => 'term',
	);

	const WXR_JOB_CLASS = WXRImportJob::class;

	public function run() {

		$wxr_file_path = $this->import->get_meta( 'wxr_file' );
		$checksum      = $this->import->get_meta( 'wxr_file_checksum' );

		if ( md5_file( $wxr_file_path ) !== $checksum ) {
			throw new Exception( 'Invalid WXR file.' );
		}

		// Create stages jobs for partial imports of the WXR.
		$this->create_partial_import_jobs( $wxr_file_path );

		// Create stage and jobs that handle attachment related actions.
		$this->create_attachment_jobs();

		// Create the finalize job.
		$this->create_finalize_job();
	}

	protected function create_partial_import_jobs( $wxr_file_path ) {
		$indexer = new WXR_Indexer();
		$indexer->parse( $wxr_file_path );
		$this->indexer = $indexer;

		$stages = array(
			'authors'    => array(
				'type'       => 'wp:author',
				'depends_on' => 'initialization',
				'per_batch'  => 100,
			),
			'categories' => array(
				'type'       => 'wp:category',
				'depends_on' => 'initialization',
				'per_batch'  => 100,
			),
			'terms'      => array(
				'type'       => 'wp:term',
				'depends_on' => 'initialization',
				'per_batch'  => 100,
			),
			'tags'       => array(
				'type'       => 'wp:tag',
				'depends_on' => 'initialization',
				'per_batch'  => 100,

			),
			'posts'      => array(
				'type'       => 'item',
				'depends_on' => array( 'initialization', 'authors', 'categories', 'terms', 'tags' ),
				'per_batch'  => 1,
			),
		);

		$total_objects = 0;

		foreach ( $stages as $stage => $settings ) {
			$new_stage = ImportStage::get_or_create( $stage, $this->import );

			if ( ! empty( $settings['depends_on'] ) ) {
				$new_stage->depends_on( $settings['depends_on'] );
			}

			$new_stage->set_meta( 'objects', $this->indexer->get_data_raw( $settings['type'] ) );
			$new_stage->set_meta( 'per_batch', $settings['per_batch'] );

			$job_args = array(
				'importer'   => $this->type_map[ $settings['type'] ],
				'stage_name' => $stage,
			);

			// Create a job to start processing objects in the stage.
			$job_class = apply_filters( 'importer_experiment_wxr_job', self::WXR_JOB_CLASS );
			$new_stage->add_job( $job_class, $job_args );

			$new_stage->release();

			$total_objects += $this->indexer->get_count( $settings['type'] );
		}

		return $total_objects;
	}

	protected function create_attachment_jobs() {

		$stage = ImportStage::get_or_create( 'attachment_remapping', $this->import );

		$stage->add_job( AttachmentUrlMapJob::class );

		// Run after posts have imported.
		$stage->depends_on( array( 'posts' ) );

		$stage->release();

	}

	protected function create_finalize_job() {

		$stage = ImportStage::get_or_create( 'finalize', $this->import );
		$stage->set_final_stage( true );

		$stage->add_job( FinalizeImportJob::class );

		$stage->release();

	}
}
