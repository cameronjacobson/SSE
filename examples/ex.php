<?php

require_once(dirname(__DIR__).'/vendor/autoload.php');

use SSE\Config as SSEConfig;
use SSE\Server;

$server = new Server(
	new SSEConfig(dirname(__DIR__).'/config/sample_config.ini')
);

$server->loop();

function E($v){
	error_log(var_export($v,true));
}
