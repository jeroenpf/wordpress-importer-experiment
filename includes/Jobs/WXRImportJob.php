<?php

namespace ImporterExperiment\Jobs;

use ImporterExperiment\Abstracts\Job;
use ImporterExperiment\Exception;
use ImporterExperiment\ImportStage;
use ImporterExperiment\Interfaces\PartialImport;
use ImporterExperiment\PartialImporters\Author;
use ImporterExperiment\PartialImporters\Category;
use ImporterExperiment\PartialImporters\Post;
use ImporterExperiment\PartialImporters\Tag;
use ImporterExperiment\PartialImporters\Term;

/**
 * Class WXRImportJob
 *
 * A WXRImportJob gets a byte-range stored as term meta and reads the given byte-range from the
 * WXR file and parses and imports only that part.
 *
 * @package ImporterExperiment\Jobs
 */
class WXRImportJob extends Job {


	const MAX_OBJECTS_PER_JOB = 15;

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
	 * @param $job_meta
	 * @param ImportStage|null $stage
	 *
	 * @return false
	 *
	 * @throws \Exception
	 * @todo Error handling, checking if the meta exists, etc.
	 */
	public function run( $job_meta, ImportStage $stage = null ) {

		$job_meta = get_term_meta( $job_meta['stage_job'], 'job_arguments', true );

		$file     = $this->importer->get_import_meta( 'file' );
		$checksum = $this->importer->get_import_meta( 'file_checksum' );

		if ( md5_file( $file ) !== $checksum ) {
			return false;
		}

		$importer = $job_meta['importer'];

		if ( ! isset( $this->default_partial_importers[ $importer ] ) ) {
			throw new Exception( sprintf( __( 'Partial importer of type %s not implemented.' ), $importer ) );
		}

		$partial_importer_class = apply_filters( 'wordpress_importer_' . $importer . '_class', $this->default_partial_importers[ $importer ] );

		// To prevent timeout, large object batches will be split into new jobs.
		$objects = $job_meta['objects'];

		if ( count( $objects ) > self::MAX_OBJECTS_PER_JOB ) {
			$objects = array_splice( $job_meta['objects'], -self::MAX_OBJECTS_PER_JOB );
			$this->split_into_smaller_batches( $job_meta, $stage );
		}

		foreach ( $objects as $object ) {
			/** @var PartialImport $partial_importer */
			$partial_importer = new $partial_importer_class( $this->importer );
			$partial_importer->process( $object );
			$partial_importer->import();
		}

		//      $processed = get_term_meta( $term_id, 'processed', true ) ?: 0;
		//      update_term_meta( $term_id, 'processed', $processed + count( $job_data['objects'] ) );
	}

	/**
	 * If there are more than MAX_OBJECTS_PER_JOB objects, the total execution time of the
	 * batch will be too long and the batch needs to be split into smaller batches.
	 *
	 * @param $job_meta
	 * @param $stage
	 */
	protected function split_into_smaller_batches( $job_meta, $stage ) {

		$objects = $job_meta['objects'];

		while ( count( $objects ) ) {
			$job_meta['objects'] = array_splice( $objects, -self::MAX_OBJECTS_PER_JOB );
			$stage->add_job( static::class, $job_meta );
		}

	}

}
