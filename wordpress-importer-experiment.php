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

require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/job_runner.php';

function show_experiment_page() {
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

	require __DIR__ . '/vendor/autoload.php';

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

	register_term_meta(
		$taxonomy,
		'total',
		array(
			'type'   => 'int',
			'single' => true,
		)
	);

	register_term_meta(
		$taxonomy,
		'processed',
		array(
			'type'   => 'int',
			'single' => true,
		)
	);

	$runner = new Job_Runner();

	// Register the action that is triggered by the cron.
	// This action will run a single job.
	add_action( 'run_wordpress_importer', array( $runner, 'run' ) );

	// Get status
	$admin = new Admin();
	add_action( 'wp_ajax_wordpress_importer_progress', array( $admin, 'get_status' ) );

	add_action( 'wp_ajax_wordpress_importer_run_jobs', array( $admin, 'run_jobs' ) );

}

add_action( 'admin_init', 'ImporterExperiment\setup_taxonomies' );
function register_job_runner() {

	add_action(
		'wordpress_importer_experiment_run_job',
		function( $type, $item_id ) {
			$runner = new Job_Runner();
			$job    = get_metadata_by_mid( 'term', $item_id );
			$runner->run( $job );
		},
		10,
		2
	);

}
add_action( 'admin_init', 'ImporterExperiment\register_job_runner' );


function enqueue_scripts() {
	if ( isset( $_GET['page'] ) && 'importer-experiment' === $_GET['page'] ) {
		wp_enqueue_script( 'substack-index-js', plugins_url( '/js/status.js', __FILE__ ) );
		wp_enqueue_style( 'substack-index-css', plugins_url( '/css/status.css', __FILE__ ) );
	}
}


add_action( 'admin_init', 'ImporterExperiment\enqueue_scripts' );


add_filter(
	'action_scheduler_store_class',
	function() {
		return 'ActionScheduler_wpPostStore';
	},
	101,
	1
);

add_filter(
	'action_scheduler_logger_class',
	function() {
		return 'ActionScheduler_wpCommentLogger';
	},
	101,
	1
);

// Load the ActionScheduler library
require_once( plugin_dir_path( __FILE__ ) . '/vendor/woocommerce/action-scheduler/action-scheduler.php' );
