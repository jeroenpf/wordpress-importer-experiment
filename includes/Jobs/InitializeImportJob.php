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

	public function run( $job_meta ) {
		// Get the WXR file path

		$this->importer->set_import_meta( 'status', ImportStage::STATUS_RUNNING );

		$wxr_file_path = $this->importer->get_import_meta( 'file' );
		$checksum      = $this->importer->get_import_meta( 'file_checksum' );

		if ( md5_file( $wxr_file_path ) !== $checksum ) {
			throw new Exception( 'Invalid WXR file.' );
		}

		$this->create_jobs( $wxr_file_path );
	}

	protected function create_jobs( $wxr_file_path ) {
		$indexer = new WXR_Indexer();
		$indexer->parse( $wxr_file_path );
		$this->indexer = $indexer;

		$stages = array(
			'authors'    => array(
				'type'       => 'wp:author',
				'depends_on' => 'initialization',
			),
			'categories' => array(
				'type'       => 'wp:category',
				'depends_on' => 'initialization',
			),
			'terms'      => array(
				'type'       => 'wp:term',
				'depends_on' => 'initialization',

			),
			'posts'      => array(
				'type'       => 'item',
				'depends_on' => array( 'initialization', 'authors', 'categories', 'terms' ),
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
}
