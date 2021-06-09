import {ImportStatusComponent} from "./components/import_status.js";

jQuery( document ).ready(($) => {

	if(document.getElementById('importer-experiment-app')) {

		var VueApp = new Vue({
			el: "#importer-experiment-app",
			components: { ImportStatusComponent }
		});
	}
});
