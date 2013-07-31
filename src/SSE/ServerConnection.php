<?php

namespace SSE;

use \EventBuffer;
use \EventBufferEvent;

class ServerConnection
{
	private $bev, $base;

	public function __destruct() {
		$this->bev->free();
	}

	public function __construct($base, $fd){
		$this->base = $base;

		$this->bev = new EventBufferEvent($base, $fd, EventBufferEvent::OPT_CLOSE_ON_FREE);

		$this->bev->setCallbacks(array($this, "echoReadCallback"), NULL,
			array($this, "echoEventCallback"), NULL
		);
		$this->bev->enable(Event::READ);

		$this->uuid = $this->getUUID();
		$this->bev->uuid = $this->uuid;
		Server::$connections[$this->uuid] = $this->bev;
	}

	private function getUUID(){
		return (string)rand(1000,9999);
	}

	public function echoReadCallback($bev, $ctx) {
		$id = trim($bev->input->readLine(EventBuffer::EOL_LF));
		$buff = new EventBuffer();
		if(empty(Server::$connections[$id])){
			$buff->add($id.PHP_EOL);
			$bev->output->addBuffer($buff);
		}
		else{
			$buff->add($id.PHP_EOL);
			Server::$connections[$id]->output->addBuffer($buff);
		}
	}

	public function echoEventCallback($bev, $events, $ctx) {
		if ($events & EventBufferEvent::ERROR) {
		}

		if ($events & (EventBufferEvent::EOF | EventBufferEvent::ERROR)) {
		}
	}
}
