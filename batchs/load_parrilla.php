<?php

$autoload_location = '/vendor/autoload.php';
$tries = 0;
while (!is_file(__DIR__ . $autoload_location)) {
	$autoload_location = '/..' . $autoload_location;
	$tries++;
	if ($tries > 10)
		die("Error trying to find autoload file\n");
}
require_once __DIR__ . $autoload_location;
require_once('../conf/config.php');
require_once('../conf/bootstrap.php');

ini_set("memory_limit", "9000M");
set_time_limit(0);
$start_microtime = microtime(true);

$total = 0;

use Omatech\Tviso\Tviso;

date_default_timezone_set('Europe/Madrid');
$tviso = new Tviso($conn, $tviso_params);
$token = $tviso->getToken();
if ($token) {
	// Start date
	$date = date("Y-m-d");
	// End date
	$end_date = date("Y-m-d", strtotime("+3 day", strtotime($date)));

	while (strtotime($date) <= strtotime($end_date)) {
		echo "$date\n";
		$response = $tviso->getCalendar($date);
		if ($response) {
			echo("Response size:".count($response)."\n");
			//echo "Response::: ".print_r($response, true)."\n";
		} else {
			echo $tviso->error . "\n";
		}
		$date = date("Y-m-d", strtotime("+1 day", strtotime($date)));
	}
} else {
	echo "Error al obtener el token " . $tviso->error . "\n";
}