# WordPress Importer Experiment

This WordPress plugin aims to solve performance and stability issues encountered
in the [WordPress Importer Plugin](https://github.com/WordPress/wordpress-importer).

Problems this experiment intends to solve:

 - Memory & timeout issues when dealing with large imports
 - Maintainability
 - Extensibility

## Setup

### Install on existing WordPress installation

The plugin can be zipped and uploaded as plugin  to any WordPress installation.

Make sure to run `composer install` prior to zipping and uploading as there are
dependencies that need to be installed.

### Use wp-env for local development and testing

The easiest way to run the plugin locally is by using [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/).
For this to work you need to have docker (and docker-compose) installed locally.

If you have installed wp-env, simply run `wp-env start` in the plugin directory.

This plugin has composer dependencies and uses the composer autoloader. To install them simply run:

`composer install`
