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
		run_cron();
		run_cron();
	}


	var VueApp = new Vue({

		el: "#importer-experiment-app",
		data: () => {
			return {
				message: 'Hello this is working...',
				debug: null,
				showJobArgumentsFor: []
			}
		},
		methods: {
			get_debug: function() {
				let obj = this;
				$.post( ajaxurl, { action: 'wordpress_importer_get_debug' })
					.done( (response) => {

						obj.debug = response;
						setTimeout(obj.get_debug, 1000);
					});
			},
			toggleArguments: function( job ) {

				if(this.showJobArgumentsFor[job] === undefined) {
					this.showJobArgumentsFor[job] = false;
				}

				this.showJobArgumentsFor[job] = !this.showJobArgumentsFor[job];
			},
			completed_count: function( stage ) {

				let jobs = this.debug.stages.children[stage].children;

				return Object.keys(jobs)
					.filter( (k) => {
						return jobs[k].meta.status === 'completed';
					}).length;
			}
		},
		created: function() {
			this.get_debug();
		}

	});

});
