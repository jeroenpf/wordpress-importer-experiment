<div class="wrap">
	An overview of the jobs.
	<div id="importer-progress">
		<div class="progress-bar">
			<span class="progress-bar-fill" style="width: 0%;"></span>
		</div>
	</div>

	<div id="importer-experiment-app">
		<h3><span class="dashicons dashicons-code-standards"></span> Debug:</h3>
		<status-component :import_id="<?php echo $import->get_id(); ?>"></status-component>
</div>
