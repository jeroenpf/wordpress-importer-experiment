<?php

namespace ImporterExperiment\Abstracts;

use ImporterExperiment\Import;
use ImporterExperiment\Interfaces\Logger as LoggerInterface;

abstract class Logger implements LoggerInterface {

	/**
	 * @var Import
	 */
	protected $import;

	public function set_import( Import $import ) {
		$this->import = $import;
	}

	public function error( $message, array $context = array() ) {
		$this->log( $message, self::LOG_ERROR, $context );
	}

	public function notice( $message, array $context = array() ) {
		$this->log( $message, self::LOG_NOTICE, $context );
	}

	public function warning( $message, array $context = array() ) {
		$this->log( $message, self::LOG_WARNING, $context );
	}

}
