<?php

require_once(dirname(__DIR__).'/vendor/autoload.php');

use SSE\EventStore;
use SSE\Server;

$config = parse_ini_file(dirname(__DIR__).'/config/sample_config.ini',true);
$eventStore = new EventStore($config);

$server = new Server($eventStore);
$server->loop();

function E($v){
	error_log(var_export($v,true));
}
