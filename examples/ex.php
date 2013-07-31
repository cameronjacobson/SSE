<?php

require_once(dirname(__DIR__).'/vendor/autoload.php');

use SSE\Server;

$server = new Server();
$server->loop();
