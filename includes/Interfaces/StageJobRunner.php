<?php

namespace ImporterExperiment\Interfaces;

interface StageJobRunner {

	public function run();

	public static function init();

}
