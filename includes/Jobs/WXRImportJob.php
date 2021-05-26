<?php

namespace ImporterExperiment\Jobs;

use ImporterExperiment\Abstracts\Job;
use ImporterExperiment\Exception;
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
	 * @param $job_meta
	 *
	 * @return false
	 *
	 * @todo Error handling, checking if the meta exists, etc.
	 */
	public function run( $job_meta ) {

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

		foreach ( (array) $job_meta['objects'] as $object ) {
			/** @var PartialImport $partial_importer */
			$partial_importer = new $partial_importer_class( $this->importer );
			$partial_importer->run( $object );
		}

		//      $processed = get_term_meta( $term_id, 'processed', true ) ?: 0;
		//      update_term_meta( $term_id, 'processed', $processed + count( $job_data['objects'] ) );
	}

}
