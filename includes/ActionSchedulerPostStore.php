<?php

namespace ImporterExperiment;

use ActionScheduler_wpPostStore;

/**
 * Class ActionSchedulerPostStore
 *
 * This class exists because the ActionScheduler will try to run a migration if no custom store is set.
 *
 * @package ImporterExperiment
 */
class ActionSchedulerPostStore extends ActionScheduler_wpPostStore {}
