<?php

namespace ImporterExperiment\Interfaces;

use ImporterExperiment\Import;

interface  Logger {

	const LOG_ERROR   = 'error';
	const LOG_NOTICE  = 'notice';
	const LOG_WARNING = 'warning';

	public function set_import( Import $import );

	public function log( $message, $level, array $context = array());

	public function error( $message, array $context = array() );

	public function notice( $message, array $context = array() );

	public function warning( $message, array $context = array() );

}
