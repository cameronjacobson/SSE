<?php

namespace SSE;

use \Event;
use \EventBuffer;
use \EventBufferEvent;

class ServerConnection
{
	private $bev, $base;

	public function __destruct() {
		$this->bev->free();
	}
	public function __construct($base, $fd, $ident){
		$this->base = $base;
		$this->ident = $ident;
		$this->bev = new EventBufferEvent($base, $fd, EventBufferEvent::OPT_CLOSE_ON_FREE);

		$this->bev->setCallbacks(array($this, "echoReadCallback"), NULL,
			array($this, "echoEventCallback"), NULL
		);
		$this->bev->enable(Event::READ);

		$this->uuid = $this->getUUID();
		$this->bev->uuid = $this->uuid;
	}

	private function getUUID(){
		return (string)rand(1000,9999);
	}

	public function echoReadCallback($bev, $ctx) {
		$bev->readBuffer($bev->input);
		while($line = $bev->input->read(512)){
			$this->buffer .= $line;
		}
		$this->processRequest();
	}

	public function echoEventCallback($bev, $events, $ctx) {
		if ($events & EventBufferEvent::ERROR) {
		}

		if ($events & (EventBufferEvent::EOF | EventBufferEvent::ERROR)) {
		}
	}

	private function processRequest(){
		/**
		 *  TODO:
		 *   - Read received headers for Last-Event-ID
		 */
		$request = $this->buffer;
		if(strpos($request, "\r\n\r\n") !== false){
			list($headers,$body) = explode("\r\n\r\n", $request,2);
			$headers = explode("\r\n", $headers);
			$firstline = array_shift($headers);
			preg_match("|^POST\s+?/([^\s]+?)\s|",$firstline,$match);
			$clientUUID = $match[1];

			$output = $this->bev->output;
			$output->add(
				'HTTP/1.1 200 OK'."\r\n".
				'Date: '.gmdate('D, d M Y H:i:s').' GMT'."\r\n".
				'Server: Server-Sent-Events 0.1'."\r\n".
				'MIME-version: 1.0'."\r\n".
				"Content-Type: text/plain; charset=utf-8\r\n".
				"Content-Length: 0\r\n\r\n"
			);

			Server::sendMessage($this->ident, $clientUUID, $body);
		}
	}
}
