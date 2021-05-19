jQuery( document ).ready(($) => {

	let import_completed = false;

	// This runs the cron.
	let run_cron = () => {
		$.get('/wp-cron.php', function() {

			if( !import_completed ) {
				setTimeout(run_cron, 100);
			}
		});
	}

	run_cron();

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
	}
});
