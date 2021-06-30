<?php

namespace ImporterExperiment\StageJobs;

use ImporterExperiment\Abstracts\StageJob;
use ImporterExperiment\ImporterException;
use ImporterExperiment\ImportStage;
use ImporterExperiment\Interfaces\PartialImport;
use ImporterExperiment\PartialImporters\Author;
use ImporterExperiment\PartialImporters\Category;
use ImporterExperiment\PartialImporters\Post;
use ImporterExperiment\PartialImporters\Tag;
use ImporterExperiment\PartialImporters\Term;



/**
 * Class WXRImport
 *
 * This stage job retrieves a set number of partial WXR objects from a stack
 * and processes them.
 *
 * If
 *
 * @package ImporterExperiment\Jobs
 */
class WXRImport extends StageJob {

	/**
	 * @var string[] A list of partial importer classnames.
	 */
	protected $default_partial_importers = array(
		'post'     => Post::class,
		'author'   => Author::class,
		'category' => Category::class,
		'term'     => Term::class,
		'tag'      => Tag::class,
	);

	/**
	 * @return bool
	 *
	 * @todo If the partial importer fails, in some cases we might want to retry.
	 *       For example, if the attachment could not be downloaded, we might want
	 *       to try again. In that case we would want to add the object back to the
	 *       stack and somehow keep record of how many attempts we did.
	 */
	public function run() {

		$file     = $this->import->get_meta( 'wxr_file' );
		$checksum = $this->import->get_meta( 'wxr_file_checksum' );

		if ( md5_file( $file ) !== $checksum ) {
			return false;
		}

		$objects = $this->get_objects_and_add_next_job();

		// There was no more object.
		if ( empty( $objects ) ) {
			return false;
		}

		$this->run_partial_importers( $objects );

	}

	/**
	 * @param array $objects
	 */
	protected function run_partial_importers( array $objects ) {
		$success_count          = 0;
		$failure_count          = 0;
		$partial_importer_class = $this->get_partial_importer_class();

		foreach ( $objects as $object ) {
			/** @var PartialImport $partial_importer */
			$partial_importer = new $partial_importer_class( $this->import );

			try {
				$partial_importer->process( $object );
				$partial_importer->import();
				$success_count++;
			} catch ( \Exception $e ) {
				$failure_count++;
				$this->import->get_logger()->error(
					sprintf( __( 'Object %s for %s importer could not be processed.' ), $object, $this->arguments['importer'] ),
					array(
						'exception' => $e,
					)
				);
			}
		}

		$this->update_counts( $success_count, $failure_count );
	}

	protected function update_counts( $success_count, $failure_count ) {

		if ( $success_count > 0 ) {
			$this->stage->increment_success_count( $success_count );
		}

		if ( $failure_count > 0 ) {
			$this->stage->increment_failed_count( $failure_count );
		}

	}

	/**
	 * @return string
	 */
	protected function get_partial_importer_class() {
		$importer = $this->arguments['importer'];

		if ( ! isset( $this->default_partial_importers[ $importer ] ) ) {
			throw new ImporterException( sprintf( __( 'Partial importer of type %s not implemented.' ), $importer ) );
		}

		$partial_importer_class = apply_filters( 'wordpress_importer_' . $importer . '_class', $this->default_partial_importers[ $importer ] );

		if ( ! class_exists( $partial_importer_class ) ) {
			throw new ImporterException( sprintf( __( 'Partial importer %s does not exist.' ), $partial_importer_class ) );
		}

		return $partial_importer_class;
	}


	public function get_objects_and_add_next_job() {

		$stage = ImportStage::get_or_create( $this->arguments['stage_name'], $this->import );

		$objects = $stage->get_meta( 'objects' );

		$objects_per_batch = $stage->get_meta( 'per_batch' );

		if ( ! count( $objects ) ) {
			return array();
		}

		$current_objects = array_splice( $objects, 0, $objects_per_batch );

		$stage->set_meta( 'objects', $objects );

		if ( ! count( $objects ) ) {
			return $current_objects;
		}

		$job_args = array(
			'importer'   => $this->arguments['importer'],
			'stage_name' => $this->arguments['stage_name'],
		);

		// Create a job to process the next object.
		$stage->add_job( static::class, $job_args );

		return $current_objects;

	}

}
