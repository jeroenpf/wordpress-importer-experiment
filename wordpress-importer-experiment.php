<?php
/**
 * Plugin Name:     WordPress Import Experiment
 * Plugin URI:      https://github.com/jeroenpf/wordpress-importer-experiment
 * Description:     A plugin that experiments breaking down WXR imports into smaller, isolated steps.
 * Author:          wordpressdotorg
 * Text Domain:     wordpress-importer-experiment
 * Version:         0.1.0
 * requires PHP:    5.6
 *
 * @package         WordpressImporterExperiment
 */

namespace ImporterExperiment;


defined( 'ABSPATH' ) || exit;

function show_experiment_page() {

	require_once __DIR__ . '/includes/admin.php';

	$admin = new Admin();

	$admin->run();
}

add_filter(
	'upload_size_limit',
	function() {
		return 524288000;
	}
);

function experiment_menu() {
	add_management_page(
		'Importer Experiment',
		'Importer Experiment',
		'manage_options',
		'importer-experiment',
		'ImporterExperiment\show_experiment_page',
		100
	);
}
add_action( 'admin_menu', 'ImporterExperiment\experiment_menu' );

function setup_taxonomies() {

	$taxonomy = 'importer_experiment';
	$args     = array(
		'hierarchical'      => true, // make it hierarchical (like categories)
		'show_ui'           => false,
		'show_admin_column' => false,
		'query_var'         => true,
		'rewrite'           => array( 'slug' => 'importer-experiment' ),
	);
	register_taxonomy( $taxonomy, array( 'user' ), $args );

	register_term_meta(
		$taxonomy,
		'file',
		array(
			'type'   => 'string',
			'single' => true,
		)
	);

	register_term_meta(
		$taxonomy,
		'file_checksum',
		array(
			'type'   => 'string',
			'single' => true,
		)
	);

	register_term_meta(
		$taxonomy,
		'job',
		array(
			'type'   => 'array',
			'single' => false,
		)
	);
}

add_action( 'init', 'ImporterExperiment\setup_taxonomies' );
