<?php

namespace ImporterExperiment;

use ImporterExperiment\Abstracts\Scheduler;

class ActionScheduler extends Scheduler {

	const ACTION_GROUP = 'importer_experiment_action_group';

	public function init() {

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
		require_once( plugin_dir_path( __FILE__ ) . '/../vendor/woocommerce/action-scheduler/action-scheduler.php' );

	}

	public function schedule( $hook, $job_class, $args = array() ) {

		$args = array(
			'job_class' => $this->sanitize_class( $job_class ),
			'args'      => $args,
		);

		as_enqueue_async_action( $hook, $args, self::ACTION_GROUP );
	}

	public function unschedule( $hook ) {
		as_unschedule_all_actions( null, array(), self::ACTION_GROUP );
	}

	protected function sanitize_class( $class ) {
		return str_replace( '\\', '\\\\', $class );
	}
}
