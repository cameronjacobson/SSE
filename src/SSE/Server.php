<?php

namespace SSE;

use \EventBase;
use \EventUtil;
use \EventListener;
use \SSE\EventStoreInterface;
use \Event;
use \SSE\SSEEvent;

class Server
{
	public $base, $listener, $socket;
	public static $conn = array();
	private static $established = array();
	private $eventStore;
	public static $subscriptions;

	public function __construct(EventStoreInterface $eventStore){
		self::$conn = array('server'=>array(),'client'=>array());
		$this->base = new EventBase();

		$this->eventStore = $eventStore;
		self::$subscriptions = $this->eventStore->getAllSubscriptions();

		$this->browserListener = new EventListener($this->base,
			array($this, "clientConnCallback"), $this->base,
			EventListener::OPT_CLOSE_ON_FREE | EventListener::OPT_REUSEABLE, -1,
			"0.0.0.0:20000"
		);

		$this->serverListener = new EventListener($this->base,
			array($this, 'serverConnCallback'), $this->base,
			EventListener::OPT_CLOSE_ON_FREE | EventListener::OPT_REUSEABLE, -1,
			"127.0.0.1:9499"
		);

		$this->browserListener->setErrorCallback(array($this, "accept_error_cb"));
	}

	public static function setSubscription($uuid,$name){
		self::$subscriptions[$name] = @self::$subscriptions[$name] || array();
		self::$subscriptions[$name][] = $uuid;
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
		self::$conn['client'][$ident] = new ClientConnection($base, $fd, $ident, $this->eventStore);
	}

	public function serverConnCallback($listener, $fd, $address, $ctx) {
		$base = $this->base;
		$ident = $this->getUUID();
		self::$conn['server'][$ident] = new ServerConnection($base, $fd, $ident, $this->eventStore);
	}

	public static function assignUUID($conntype, $ident, $uuid){
		if(empty(self::$established[$uuid])){
			self::$established[$uuid] = array();
		}
		self::$established[$uuid][] = self::$conn[$conntype][$ident];
		unset(self::$conn[$conntype][$ident]);
		end(self::$established[$uuid]);
		return key(self::$established[$uuid]);
	}

	public static function sendMessage($uuid, $message){
		if(!empty(self::$established[$uuid])){
			foreach(self::$established[$uuid] as $k => $conn){
				if(!$conn->send('message: '.$message)){
					E('failed connection');
					unset(self::$established[$uuid][$k]);
				}
			}
		}
		error_log('ESTABLISHED: '.count(self::$established[$uuid]));
	}

	public static function sendGroupMessage($group,$message){
		foreach(self::$subscriptions[$group] as $uuid){
			self::sendMessage($uuid, $message);
		}
	}

	public static function disconnect($conntype, $uuid, $ident, $index){
		if(!empty(self::$conn[$conntype][$ident])){
			self::$conn[$conntype][$ident]->__destruct();
			unset(self::$conn[$conntype][$ident]);
		}
		if(!empty(self::$established[$uuid][$index])){
			self::$established[$uuid][$index]->__destruct();
			unset(self::$established[$uuid][$index]);
		}
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
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}
}

function E($val){
	error_log(var_export($val,true));
}
