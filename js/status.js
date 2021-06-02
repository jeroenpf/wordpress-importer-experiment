jQuery( document ).ready(($) => {

	let import_completed = false;

	// This runs the cron.
	let run_cron = () => {

		let data = {
			action: 'wordpress_importer_run_jobs'
		};

		$.post( ajaxurl, data).always(function() {
			if( !import_completed ) {
				setTimeout(run_cron, 1500);
			}
		});
	}

	let get_status = () => {
		let data = {
			action : 'wordpress_importer_progress'
		};
		$.post( ajaxurl, data, (response) => {
			let progress = (response.processed / response.total) * 100;
			$( '#importer-progress .progress-bar-fill' ).css( 'width', progress + '%' );

			if(progress === 100) {
				import_completed = true;
				return;
			}

			setTimeout(get_status, 2000);
		});
	};
	if ( $( 'div#importer-progress' ).length ) {
		//get_status();
		//run_cron();
	}

	if(document.getElementById('importer-experiment-app')) {


		const StatusComponent = {
			template: `
				<div v-if="debug">
					<table class="import wp-list-table widefat fixed striped table-view-list">
						<tr>
							<td>File</td>
							<td>{{ debug.import.meta.wxr_file }}</td>
						</tr>
						<tr>
							<td>Checksum</td>
							<td>{{ debug.import.meta.wxr_file_checksum }}</td>
						</tr>
						<tr>
							<td>Status</td>
							<td :class="['status', debug.import.meta.status]">{{ debug.import.meta.status }}</td>
						</tr>
				</table>

				<h3><span class="dashicons dashicons-menu-alt"></span> Stages:</h3>

				<div class="stage" v-for="(stage, idx) in debug.stages" :key="stage.id">

					<ul class="header subsubsub">
						<li class="name"><span class="dashicons dashicons-category"></span> {{ stage.meta.name }}</li>
						<li><strong>Completed:</strong> {{ stage.total_jobs - stage.active_jobs }} </li>
						<li><strong>Active</strong> {{ stage.active_jobs }} </li>
						<li :class="['status', stage.meta.status]">{{ stage.meta.status }}</li>
						<li class="depends_on" v-if="stage.meta.depends_on.length">
							<strong>depends on:</strong>
							{{ Array.isArray(stage.meta.depends_on) ? stage.meta.depends_on.join(", ") : stage.meta.depends_on }}
						</li>
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
							<tr v-for="job in stage.jobs"  :key="job.name">
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
					message: 'Hello this is working...',
					debug: null,
					showJobArgumentsFor: []
				}
			},
			props: ['import_id'],
			methods: {
				run_cron: lodash.debounce(function () {
					let data = {
						action: 'wordpress_importer_run_jobs'
					};

					let obj = this;

					$.post(ajaxurl, data).always(function () {

						let done = !obj.debug ? false : obj.debug.import.meta.status === 'completed';

						if (!done) {
							obj.run_cron();
						}

					});
				}, 1000),
				get_debug: lodash.debounce(function () {
					let obj = this;

					let data = {
						action: 'wordpress_importer_get_debug',
						import_id: obj.import_id
					}

					$.post(ajaxurl, data)
						.done((response) => {

							obj.debug = response;
							obj.get_debug();
						});
				}, 3000),
				toggleArguments: function (job) {

					if (this.showJobArgumentsFor[job] === undefined) {
						this.showJobArgumentsFor[job] = false;
					}

					this.showJobArgumentsFor[job] = !this.showJobArgumentsFor[job];
				},
				completed_count: function (stage) {

					let jobs = this.debug.stages[stage].jobs;

					return jobs
						.filter((job) => {
							return job.meta.status === 'completed';
						}).length;
				}
			},
			created: function () {
				this.get_debug();
				this.run_cron();
			}
		};

		var VueApp = new Vue({
			el: "#importer-experiment-app",
			components: { StatusComponent }
		});
	}
});
