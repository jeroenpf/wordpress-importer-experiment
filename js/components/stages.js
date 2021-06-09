export let StagesComponent = {
	template: `
			<div>

				<h3><span class="dashicons dashicons-menu-alt"></span> Stages:</h3>

				<div class="stage" v-for="(stage, idx) in stages" :key="stage.id">

					<ul class="header subsubsub">
						<li class="name"><span class="dashicons dashicons-category"></span> {{ stage.meta.name }}</li>
						<li><strong>Active</strong> {{ stage.active_jobs }} </li>
						<li :class="['status', stage.meta.status]">{{ stage.meta.status }}</li>
						<li class="depends_on" v-if="stage.meta.depends_on.length">
							<strong>depends on:</strong>
							{{ Array.isArray(stage.meta.depends_on) ? stage.meta.depends_on.join(", ") : stage.meta.depends_on }}
						</li>
						<template v-if="stage.meta.total_objects > 0">
							<li>Total objects: {{ stage.meta.total_objects }}</li>
							<li>Processed: {{ stage.meta.success_objects + stage.meta.failed_objects }}</li>
							<li v-if="stage.meta.failed_objects > 0">Failed: {{ stage.meta.failed_objects }}</li>
						</template>
					</ul>

					<table class="wp-list-table widefat fixed striped table-view-list">
						<thead>
							<tr>
								<th>ID</th>
								<th>Job</th>
								<th>Class</th>
								<th>Status</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="job in stage.jobs"  :key="job.name">
								<td> {{ job.id }} </td>
								<td> {{ job.name }} </td>
								<td> <pre>{{ job.meta.job_class }}</pre> </td>
								<td :class="['status', job.meta.status]"> {{ job.meta.status }} </td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
			`,
	data: () => {
		return {
		}
	},
	props: [ 'stages' ],
	methods: {
	}
};
