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
	 * @todo Error handling, checking if the meta exists, etc.
	 */
	public function run() {

		$file     = $this->import->get_meta( 'wxr_file' );
		$checksum = $this->import->get_meta( 'wxr_file_checksum' );

		if ( md5_file( $file ) !== $checksum ) {
			return false;
		}

		$objects = $this->get_objects_and_schedule_next();

		// There was no more object.
		if ( empty( $objects ) ) {
			return false;
		}

		$importer = $this->arguments['importer'];

		if ( ! isset( $this->default_partial_importers[ $importer ] ) ) {
			throw new Exception( sprintf( __( 'Partial importer of type %s not implemented.' ), $importer ) );
		}

		$partial_importer_class = apply_filters( 'wordpress_importer_' . $importer . '_class', $this->default_partial_importers[ $importer ] );

		foreach ( $objects as $object ) {
			/** @var PartialImport $partial_importer */
			$start            = microtime( true );
			$partial_importer = new $partial_importer_class( $this->import );
			$partial_importer->process( $object );
			$partial_importer->import();
			$total = ( microtime( true ) - $start );
		}

	}


	public function get_objects_and_schedule_next() {

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
