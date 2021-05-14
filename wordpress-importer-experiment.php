<?php
/**
 * Plugin Name:     Wordpress Import Experiment
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

	require_once 'includes/admin.php';

	$admin = new Admin();

	$admin->run();
}


function experiment_menu() {
	add_management_page(
		'Importer Experiment',
		'Importer Experiment',
		'manage_options',
		'importer-experiment',
		'show_experiment_page',
		100
	);
}
add_action('admin_menu', 'experiment_menu');
