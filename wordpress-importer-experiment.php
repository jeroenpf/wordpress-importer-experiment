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

require __DIR__ . '/vendor/autoload.php';

add_filter(
	'upload_size_limit',
	function() {
		return 524288000;
	}
);

// Instantiate the admin interface
Admin::instance()->init( __FILE__ );


