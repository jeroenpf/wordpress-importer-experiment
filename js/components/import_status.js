import {LogComponent} from "./log.js";
import {StagesComponent} from "./stages.js";
import { ImportSummaryComponent } from "./import_summary.js";
import {StatusBarComponent} from "./statusbar.js";

const $ = jQuery;

export let ImportStatusComponent = {
	template: `
			<div>
				<div v-if="status">
					<status-bar-component :completed_percentage="completed_percentage"></status-bar-component>
					<import-summary-component :status="status"></import-summary-component>
					<stages-component v-if="status.stages" :stages="status.stages"></stages-component>
					<log-component v-if="status.logs" :log_entries="status.logs"></log-component>
				</div>
				<div v-else>
					<h2>Import data is loading...</h2>
				</div>
			</div>
			`,
	data: () => {
		return {
			status: null
		}
	},
	props: ['import_id'],
	components: {
		LogComponent,
		StagesComponent,
		ImportSummaryComponent,
		StatusBarComponent
	},
	methods: {
		run_cron: lodash.debounce(function () {
			let data = {
				action: 'wordpress_importer_run_jobs'
			};

			let obj = this;

			$.post(ajaxurl, data).always(function () {

				let done = !obj.status ? false : obj.status.import.meta.status === 'completed';

				if (!done) {
					obj.run_cron();
				}

			});
		}, 100),
		get_status: lodash.debounce(function () {
			let obj = this;

			let data = {
				action: 'wordpress_importer_get_status',
				import_id: obj.import_id
			}

			$.post(ajaxurl, data)
				.done((response) => {

					obj.status = response;
					obj.get_status();
				});
		}, 6000),
	},
	computed: {
		completed_percentage: function() {
			if( !this.status ) {
				return 0;
			}


			let total = 0;
			let processed = 0;

			this.status.stages.forEach(function( stage ) {
				total += stage.meta.total_objects;
				processed += stage.meta.success_objects + stage.meta.failed_objects;
			});

			return Math.round( processed / total * 100 );

		}
	},
	created: function () {
		this.get_status();
		this.run_cron();
	}

};
