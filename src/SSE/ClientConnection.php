<?php

namespace SSE;

use \SplQueue;
use \Event;
use \EventDnsBase;
use \EventBufferEvent;
use \SSE\EventStoreInterface;
use \SSE\SSEEvent;

class ClientConnection
{
	private $bev, $base, $buffer, $id, $fd;

	const MIN_WATERMARK = 1;

	public function __destruct() {
		$this->bev->free();
	}

	public function __construct($base, $fd, $ident, EventStoreInterface $eventStore){
		$this->buffer = '';
		$this->base = $base;
		$this->fd = $fd;
		$this->ident = $ident;
		$this->id = 0;
		$this->index = null;
		$this->eventStore = $eventStore;

		$dns_base = new EventDnsBase($this->base, TRUE);

		$this->bev = new EventBufferEvent($this->base, $fd,
			EventBufferEvent::OPT_CLOSE_ON_FREE | EventBufferEvent::OPT_DEFER_CALLBACKS
		);

		$this->bev->setCallbacks(
			array($this,'readCallback'),
			array($this,'writeCallback'),
			array($this,'eventCallback')
		);

		$this->bev->setWatermark(Event::READ|Event::WRITE, self::MIN_WATERMARK, 0);

		if(!$this->bev->enable(Event::READ | Event::WRITE)){
			echo 'failed to enable'.PHP_EOL;
		}

		// If client hasn't sent headers within 3 sec, kill it
		$e = Event::timer($base, function() use (&$e, $ident){
			if(empty($this->headers)){
				Server::disconnect('client',null,$ident,null);
			}
			$e->delTimer();
		});
		$e->addTimer(3);
	}

	public function readCallback($bev/*, $arg*/) {
		$input = $bev->getInput();
		if(empty($this->headers)){
			if($pos = $input->search("\r\n\r\n")){
				list($method,$resource,$this->headers) = $this->processHeaders($input->read($pos));
				switch($method){
					case 'OPTIONS':
						$this->headers = array();
						$this->processOptionsRequest($bev);
						break;
					case 'GET':
						$this->processRequest();
						break;
					default:
						break;
				}
			}
		}
		else{
			if($input->length > 0){
				$input->read($input->length);
			}
		}
	}

	public function writeCallback($bev/*, $arg*/) {
		//var_dump($bev);
	}

	public function eventCallback($bev, $events/*, $arg*/) {
		if ($events & EventBufferEvent::TIMEOUT) {
		}
		if ($events & EventBufferEvent::EOF) {
			Server::disconnect('client',$this->uuid,$this->ident,$this->index);
		}
		if ($events & EventBufferEvent::ERROR) {
		}
	}

	private function processHeaders($buffer){
		/**
		 *  TODO:
		 *   - Read received headers for Last-Event-ID
		 */
		$headers = explode("\r\n", $buffer);
		$firstline = array_shift($headers);
		preg_match("/^(OPTIONS|GET)\s+?\/([^\s]+?)\s/",$firstline,$match);

		$method = $match[1];
		$resource = $match[2];
		$this->uuid = $match[2];
		$return = array();
		foreach($headers as $header){
			list($k,$v) = explode(':',$header);
			$return[trim(strtolower($k))] = trim(strtolower($v));
		}
		return array($method,$resource,$return);
	}


	public function send($message, $id = null){
		list($event, $data) = explode(':', $message,2);
		$event = trim($event);
		$data = trim($data);
		if(empty($event) || !ctype_alpha($event)){
			return;
		}
		if(empty($data) || !is_string($data) || preg_match("/[^\x20-\x7E]/", $data)){
			return;
		}

		$this->id = empty($id) ? ++$this->id : $id;

		$evt = new SSEEvent();
		$evt->id = $this->id;
		$evt->event = $event;
		$evt->data = $data;
		$evt->uuid = $this->uuid;

		$this->eventStore->putEvent($evt);

		$message = "\n".implode("\n",array(
			'event: '.$event,
			'data: '.trim($data),
			'id: '.$this->id,
			"",""
		));
		$output = $this->bev->output;
		return $output->add($message);
	}

	private function processRequest(){
		$this->id = $last_event_id = empty($this->headers['last-event-id']) ? 0 : $this->headers['last-event-id'];

		$output = $this->bev->output;
		$output->add(implode("\r\n",array(
			'HTTP/1.1 200 OK',
			'Date: '.gmdate('D, d M Y H:i:s').' GMT',
			'Server: Server-Sent-Events 0.1',
			'MIME-version: 1.0',
			'Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT',
			'Access-Control-Allow-Origin: *',
			'Access-Control-Allow-Methods: GET,OPTIONS',
			'Access-Control-Allow-Headers: Last-Event-ID',
			"Content-Type: text/event-stream; charset=utf-8",
			"","",""
		)));

		$this->index = Server::assignUUID('client', $this->ident, $this->uuid);

		$events = $this->eventStore->getEvents($this->uuid, $last_event_id);
		foreach($events as $event){
			$this->send($event['event'].':'.$event['data'], $event['id']);
		}
	}

	private function processOptionsRequest($bev){
		$last_event_id = empty($this->headers['last-event-id']) ? 0 : $this->headers['last-event-id'];

		$output = $this->bev->output;
		$output->add(implode("\r\n",array(
			'HTTP/1.1 200 OK',
			'Date: '.gmdate('D, d M Y H:i:s').' GMT',
			'Server: Server-Sent-Events 0.1',
			'Access-Control-Allow-Origin: *',
			'Access-Control-Allow-Methods: GET,OPTIONS',
			'Access-Control-Allow-Headers: Last-Event-ID',
			'Access-Control-Max-Age: 1728000',
			"Content-Type: text/plain; charset=utf-8",
			'Keep-Alive: timeout=2, max=100',
			'Connection: Keep-Alive',
			'Content-Length: 0',
			"",""
		)));
	}
}
