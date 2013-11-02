<?php

namespace SSE;

use \SplQueue;
use \Event;
use \EventDnsBase;
use \EventBufferEvent;

class ClientConnection
{
	private $bev, $base, $buffer, $id, $queue, $fd;
	public $status;

	const CONNECTED = 1;
	const INITIALIZED = 2;
	const READY = 4;

	const MIN_WATERMARK = 1;

	public function __destruct() {
		$this->bev->free();
	}

	public function __construct($base, $fd, $ident){
		$this->status = self::CONNECTED;
		$this->buffer = '';
		$this->base = $base;
		$this->fd = $fd;
		$this->queue = new SplQueue();
		$this->ident = $ident;

		$dns_base = new EventDnsBase($this->base, TRUE);

		$this->bev = new EventBufferEvent($this->base, $fd,
			EventBufferEvent::OPT_CLOSE_ON_FREE | EventBufferEvent::OPT_DEFER_CALLBACKS
		);

		$this->bev->setCallbacks(
			array($this,'readCallback'),
			array($this,'writeCallback'),
			array($this,'eventCallback')
			/* , $arg */
		);

		$this->bev->setWatermark(Event::READ|Event::WRITE, self::MIN_WATERMARK, 0);

		if(!$this->bev->enable(Event::READ | Event::WRITE)){
			echo 'failed to enable'.PHP_EOL;
		}
	}

	private function getUUID(){
		return (string)rand(1000,9999);
	}

	public function readCallback($bev/*, $arg*/) {
		$bev->readBuffer($bev->input);
		while($line = $bev->input->read(512)){
			if($this->status === self::READY){
				return;
			}
			$this->buffer .= $line;
		}
		$this->processRequest();
	}

	public function writeCallback($bev/*, $arg*/) {
		//$bev->output->writeBuffer($bev->output);
	}

	public function eventCallback($bev, $events/*, $arg*/) {
		if ($events & EventBufferEvent::TIMEOUT) {
		}
		if ($events & EventBufferEvent::EOF) {
			$bev->readBuffer($bev->input);
			$this->buffer .= $bev->input->read(1024);
		}
		if ($events & EventBufferEvent::ERROR) {
		}
	}

	public function send($message){
		if($this->status === self::READY){
			list($event, $data) = explode(':', $message,2);
			$event = trim($event);
			$data = trim($data);
			if(empty($event) || !ctype_alpha($event)){
				return;
			}
			if(empty($data) || !is_string($data) || preg_match("/[^\x20-\x7E]/", $data)){
				return;
			}
			$message = 'event: '.$event."\n".
                'data: '.trim($data)."\n".
                'id: '.(++$this->id)."\n\n";
			$output = $this->bev->output;
			$output->add($message);
		}
		else{
			$this->queue->enqueue($message);
		}
	}

	private function processRequest(){
		/**
		 *  TODO:
		 *   - Read received headers for Last-Event-ID
		 */
		$request = $this->buffer;
		if(strpos($request, "\r\n\r\n") !== false){
			list($headers) = explode("\r\n\r\n", $request,2);
			$headers = explode("\r\n", $headers);
			$firstline = array_shift($headers);
			preg_match("|^GET\s+?/([^\s]+?)\s|",$firstline,$match);
			$this->uuid = $match[1];
			foreach($headers as $header){
				if(empty($header)){
					continue;
				}

				list($key,$value) = explode(":",$header,2);
				switch(strtolower(trim($key))){
					case 'last-event-id':
						// RETRIEVE LAST EVENT ID AND SET ID
						$this->id = (int)$value;
						$this->send('initialize:'.json_encode('hello-lasteventid'));
						break;
					default:
						//echo $key.':'.$value.PHP_EOL;
						break;
				}
			}

			$output = $this->bev->output;
			$output->add(
				'HTTP/1.1 200 OK'."\r\n".
				'Date: '.gmdate('D, d M Y H:i:s').' GMT'."\r\n".
				'Server: Server-Sent-Events 0.1'."\r\n".
				'MIME-version: 1.0'."\r\n".
				'Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'."\r\n".
				'Access-Control-Allow-Origin: *'."\r\n".
				"Content-Type: text/event-stream; charset=utf-8\r\n\r\n\n\n"
			);

			$this->id = empty($this->id) ? 0 : $this->id;
			$this->status = self::READY;
			while(count($this->queue) > 0){
				$message = $this->queue->dequeue();
				$this->send($message);
			}
			Server::assignUUID($this->ident, $this->uuid);
		}
	}
}
