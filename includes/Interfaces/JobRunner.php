<?php

namespace ImporterExperiment\Interfaces;

interface JobRunner {

	public function run();

	public static function init();

}
