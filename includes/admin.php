<?php

namespace ImporterExperiment;


class Admin {

	public function run() {


		$action = $_GET['action'] ?? null;

		switch($action) {


			case "status":
				echo "Status page";
				break;
			case "create_jobs":
				echo "Creating jobs";
				break;

			default:
				include __DIR__ . '/../partials/start.php';
				break;

		}



	}


}
