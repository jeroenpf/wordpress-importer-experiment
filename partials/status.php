<div class="wrap">
	An overview of the jobs.
	<div id="importer-progress">
		<div class="progress-bar">
			<span class="progress-bar-fill" style="width: 0%;"></span>
		</div>
	</div>

	<div id="importer-experiment-app">
		<h3>Debug:</h3>

		<div v-if="debug">
			<table class="import wp-list-table widefat fixed striped table-view-list">
				<tr>
					<td>File</td>
					<td>{{ debug.import.meta.file }}</td>
				</tr>
				<tr>
					<td>Checksum</td>
					<td>{{ debug.import.meta.file_checksum }}</td>
				</tr>
			</table>

			<h3>Stages:</h3>

			<div class="stage" v-for="stage in debug.stages.children" :key="stage.id">

				<ul class="header subsubsub">
					<li class="name">{{ stage.name }} ({{ Object.keys(stage.children).length }})</li>
					<li :class="['status', stage.meta.status]">{{ stage.meta.status }}</li>
					<li class="depends_on" v-if="stage.meta.state_depends_on"> {{ stage.meta.state_depends_on.join(", ") }}</li>
				</ul>

				<table class="wp-list-table widefat fixed striped table-view-list">
					<thead>
						<tr>
							<th>Job</th>
							<th>Class</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody>
						<template v-for="job in stage.children" >
							<tr >
								<td v-on:click="toggleArguments( job.id ) "> {{ job.name }} </td>
								<td> {{ job.meta.job_class }} </td>
								<td :class="['status', job.meta.status]"> {{ job.meta.status }} </td>
							</tr>
							<tr v-if="showJobArgumentsFor[job.id] === true">
								<td colspan="3"><pre>{{ job.meta.job_arguments }}</pre></td>
							</tr>
						</template>
					</tbody>
				</table>


			</div>

	</div>
</div>
