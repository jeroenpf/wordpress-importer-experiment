# Importer Architectural overview

The WordPress Importer is responsible for importing content into a WordPress site. This content can come from an existing WordPress site or exported content from other platforms (such as Wix, Substack, Blogger, etc.).

The importer accepts a specific data format. Data that is not formatted correctly can not be imported.

## Key components of the importer

### Import

An [import](https://github.com/jeroenpf/wordpress-importer-experiment/blob/master/includes/Import.php) represents an individual import and is stored as a post. It is created after the user has uploaded a WXR file. All information (e.g. file path of WXR, Blog settings, stages, jobs) relevant to the import is associated with this post through meta and comments.

### Stage

A [stage](https://github.com/jeroenpf/wordpress-importer-experiment/blob/master/includes/ImportStage.php) can be seen as a collection of related jobs (called StageJobs). An import has multiple stages and each stage optionally has dependencies on other stages.  StageJobs will only execute when the dependencies of the stage they belong to have been met or when the stage has no dependencies.

### StageJob

A [StageJob](https://github.com/jeroenpf/wordpress-importer-experiment/blob/master/includes/Abstracts/StageJob.php) represents a job that belongs to a stage. Each stage can have an arbitrary number of StageJobs that will all be executed.  A StageJob is a class that implements the [StageJob interface](https://github.com/jeroenpf/wordpress-importer-experiment/blob/d2c068eb9255c6c761c2ce28149e4e7119d67a53/includes/Interfaces/StageJob.php). When a StageJob is created, additional arguments can be provided

### StageJobRunner

The [StageJobRunner](https://github.com/jeroenpf/wordpress-importer-experiment/blob/d2c068eb9255c6c761c2ce28149e4e7119d67a53/includes/StageJobRunner.php) is a class that is responsible for running a StageJob. It deals with running a StageJob and executes actions that need to take place before and after running a StageJob.  The StageJobRunner defines an [action hook](https://github.com/jeroenpf/wordpress-importer-experiment/blob/d2c068eb9255c6c761c2ce28149e4e7119d67a53/includes/StageJobRunner.php#L132) that can be called to run itself.

### Dispatcher

To run a job, it needs to be [dispatched](https://github.com/jeroenpf/wordpress-importer-experiment/blob/d2c068eb9255c6c761c2ce28149e4e7119d67a53/includes/ImportStage.php#L282) first. [Dispatching](zhttps://github.com/jeroenpf/wordpress-importer-experiment/blob/d2c068eb9255c6c761c2ce28149e4e7119d67a53/includes/Abstracts/Dispatcher.php) is a mechanism that allows sending a job to a queue or any other system that can execute the job. Since in different environments we want to execute jobs differently (e.g. wpcom job queue, cron, actionscheduler, or directly) a dispatcher is required to make sure a job gets executed in the right manner for the environment the import is running in.

The action job that is dispatched is always an action hook that is defined in the StageJobRunner. In other words, all jobs get executed through the same action hook. This action hook will initialize a StageJobRunner instance and provide an instance of the StageJob. Finally, it will run the StageJobRunner.

Currently an [ActionScheduler](https://github.com/jeroenpf/wordpress-importer-experiment/blob/d2c068eb9255c6c761c2ce28149e4e7119d67a53/includes/Dispatchers/ActionSchedulerDispatcher.php) and [Sync](https://github.com/jeroenpf/wordpress-importer-experiment/blob/d2c068eb9255c6c761c2ce28149e4e7119d67a53/includes/Dispatchers/SyncDispatcher.php) dispatcher is supported. The latter will execute a job immediately, without queuing it. This could be useful in a context where memory and time limits are not an issue.

## How the import, stages and jobs are stored

Imports are represented as follows:

- Import (post)
	- Stage X (comment)
		- StageJobFoo (child comment)
		- StageJobBar
	- Stage Y
		- StageJobBaz
		- ...
	- ...
