<?php

namespace SSE;

use \SSE\SSEEvent;
use \Event;
use \EventBuffer;
use \EventBufferEvent;
use \SSE\EventStoreInterface;

class ServerConnection
{
	private $bev, $base;

	public function __destruct() {
		$this->bev->free();
	}

	public function __construct($base, $fd, $ident, EventStoreInterface $eventStore){
		$this->base = $base;
		$this->ident = $ident;
		$this->eventStore = $eventStore;
		$this->bev = new EventBufferEvent($base, $fd, EventBufferEvent::OPT_CLOSE_ON_FREE);
		$this->processed = array();
		$this->bev->setCallbacks(array($this, "readCallback"), NULL,
			array($this, "eventCallback"), NULL
		);

		$this->bev->enable(Event::READ);

		// If unable to process request within 3 sec, kill it
		$e = Event::timer($base, function() use (&$e,$ident){
			Server::disconnect('server',$ident);
			$e->delTimer();
		});
		$e->addTimer(3);
	}

	public function readCallback($bev, $ctx) {
		$input = $bev->getInput();
		if(empty($this->headers) && ($pos = $input->search("\r\n\r\n"))){
			$this->headers = $this->processHeaders($input->read($pos));
			if(empty($this->headers['content-length'])){
				Server::disconnect('server',$this->ident);
			}
		}
		if($input->length >= $this->headers['content-length']+4){
			$this->body = $input->read($this->headers['content-length'] + 4);
			$this->processRequest();
		} 
	}

	public function eventCallback($bev, $events, $ctx) {
		if ($events & EventBufferEvent::ERROR) {
		}

		if ($events & (EventBufferEvent::EOF | EventBufferEvent::ERROR)) {
		}
	}

	private function processHeaders($buffer){
		/**
		 *  TODO:
		 *   - Read received headers for Last-Event-ID
		 */
		$headers = explode("\r\n", $buffer);
		$firstline = array_shift($headers);
		preg_match("|^POST\s+?/([^\s]+?)\s|",$firstline,$match);
		$this->clientUUID = $match[1];

		$return = array();
		foreach($headers as $header){
			list($k,$v) = explode(':',$header);
			$return[trim(strtolower($k))] = trim(strtolower($v));
		}
		return $return;
	}

	private function processRequest(){
		$output = $this->bev->output;
		$output->add(implode("\r\n", array(
			'HTTP/1.1 200 OK',
			'Date: '.gmdate('D, d M Y H:i:s').' GMT',
			'Server: Server-Sent-Events 0.1',
			'MIME-version: 1.0',
			"Content-Type: text/plain; charset=utf-8",
			"Content-Length: 0",
			"",""
		)));

		Server::sendMessage($this->clientUUID, $this->body);
		Server::disconnect('server',$this->ident);
	}
}
