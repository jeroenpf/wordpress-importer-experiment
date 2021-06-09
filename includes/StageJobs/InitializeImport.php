<?php

namespace ImporterExperiment\StageJobs;

use ImporterExperiment\ImporterException;
use ImporterExperiment\ImportStage;
use ImporterExperiment\Abstracts\StageJob;
use ImporterExperiment\WXR_Indexer;

/**
 * Class InitializeImportJob
 *
 * The initialize job initializes an import by registering the jobs and setting up
 * the import.
 *
 * @package ImporterExperiment\Jobs
 */
class InitializeImport extends StageJob {

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

	const WXR_JOB_CLASS = WXRImport::class;

	public function run() {

		$wxr_file_path = $this->import->get_meta( 'wxr_file' );
		$checksum      = $this->import->get_meta( 'wxr_file_checksum' );

		if ( md5_file( $wxr_file_path ) !== $checksum ) {
			throw new ImporterException( 'Invalid WXR file.' );
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
				'depends_on' => array( 'initialization', 'categories', 'terms', 'tags' ),
				'per_batch'  => 2,
			),
		);

		$empty_stages = array();

		foreach ( $stages as $stage_name => $settings ) {
			$count = $this->indexer->get_count( $settings['type'] );
			if ( ! $count ) {
				$empty_stages[ $stage_name ] = true;
				continue;
			}

			$stage = ImportStage::get_or_create( $stage_name, $this->import );

			if ( ! empty( $settings['depends_on'] ) ) {
				$stage->depends_on( array_intersect_key( $settings['depends_on'], $empty_stages ) );
			}

			$stage->set_meta( 'objects', $this->indexer->get_data_raw( $settings['type'] ) );
			$stage->set_meta( 'per_batch', $settings['per_batch'] );

			$job_args = array(
				'importer'   => $this->type_map[ $settings['type'] ],
				'stage_name' => $stage_name,
			);

			// Create a job to start processing objects in the stage.
			$job_class = apply_filters( 'importer_experiment_wxr_job', self::WXR_JOB_CLASS );
			$stage->add_job( $job_class, $job_args );

			$stage->release();

			$stage->increment_total_count( $this->indexer->get_count( $settings['type'] ) );
		}
	}


	/**
	 * @param ImportStage[] $stages
	 */
	protected function release_stages( $stages ) {
		foreach ( $stages as $stage ) {
			$stage->release();
		}
	}

	/**
	 * @throws \Exception
	 *
	 * @todo only schedule when the posts stage exists.
	 */
	protected function create_attachment_jobs() {

		$stage = ImportStage::get_or_create( 'attachment_remapping', $this->import );

		$stage->add_job( AttachmentUrlMap::class );

		// Run after posts have imported.
		$stage->depends_on( array( 'posts' ) );

		$stage->release();

	}

	protected function create_finalize_job() {

		$stage = ImportStage::get_or_create( 'finalize', $this->import );
		$stage->set_final_stage( true );

		$stage->add_job( FinalizeImport::class );

		$stage->release();

	}
}
