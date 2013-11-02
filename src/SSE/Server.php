<?php

namespace SSE;

use \EventBase;
use \EventUtil;
use \EventListener;

class Server
{
	public $base, $listener, $socket;
	private static $conn = array();
	private static $established = array();

	public function __construct(){
		$this->base = new EventBase();

		$this->browserListener = new EventListener($this->base,
			array($this, "clientConnCallback"), $this->base,
			EventListener::OPT_CLOSE_ON_FREE | EventListener::OPT_REUSEABLE, -1,
			"0.0.0.0:20000"
		);

		$this->serverListener = new EventListener($this->base,
			array($this, 'serverConnCallback'), $this->base,
			EventListener::OPT_CLOSE_ON_FREE | EventListener::OPT_REUSEABLE, -1,
			"127.0.0.1:9499");

		$this->browserListener->setErrorCallback(array($this, "accept_error_cb"));
	}

	public function loop(){
		$this->base->loop();
	}

	public function __destruct() {
		foreach (self::$conn as &$c) $c = NULL;
	}

	public function dispatch() {
		$this->base->dispatch();
	}

	public function clientConnCallback($listener, $fd, $address, $ctx) {
		$base = $this->base;
		$ident = $this->getUUID();
		self::$conn[$ident] = new ClientConnection($base, $fd, $ident);
	}

	public static function assignUUID($ident, $uuid){
		if(empty(self::$established[$uuid])){
			self::$established[$uuid] = self::$conn[$ident];
		}
		unset(self::$conn[$ident]);
	}

	public static function sendMessage($serverident, $uuid, $message){
		if(!empty(self::$established[$uuid])){
			self::$established[$uuid]->send('message: '.$message);
		}
		self::$conn[$serverident]->__destruct();
		unset(self::$conn[$serverident]);
	}

	public function serverConnCallback($listener, $fd, $address, $ctx) {
		$base = $this->base;
		$ident = $this->getUUID();
		self::$conn[$ident] = new ServerConnection($base, $fd, $ident);
	}

	public function accept_error_cb($listener, $ctx) {
		$base = $this->base;

		fprintf(STDERR, "Got an error %d (%s) on the listener. "
			."Shutting down.\n",
			EventUtil::getLastSocketErrno(),
			EventUtil::getLastSocketError());

		$base->exit(NULL);
	}

	private function getUUID(){
		return microtime(true).rand(100000,999999);
	}
}

function E($val){
	error_log(var_export($val,true));
}
