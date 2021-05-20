jQuery( document ).ready(($) => {

	let import_completed = false;

	// This runs the cron.
	let run_cron = () => {

		let data = {
			action: 'wordpress_importer_run_jobs'
		};

		$.post( ajaxurl, data).always(function() {
			if( !import_completed ) {
				run_cron();
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

			setTimeout(get_status, 1000);
		});
	};
	if ( $( 'div#importer-progress' ).length ) {
		get_status();
		run_cron();
	}
});
