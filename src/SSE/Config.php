<?php

namespace SSE;

use \EventBase;
use \Exception;
use \SSE\EventStoreInterface;
use \SSE\EventStore;

class Config
{
	public $base;
	public $server;
	public $client;
	public $store;

	public function __construct($config){

		if(is_string($config)){
			switch(substr(strrchr($config, "."), 1)){
				case 'ini':
					$config = parse_ini_file($config,true);
					break;
				case 'json':
					$config = json_decode(file_get_contents($config));
					break;
				default:
					throw new Exception('SSE\Config config file: file extension is invalid');
					break;
			}
		}

		if(empty($config['base']) || !($config['base'] instanceof EventBase)){
			$config['base'] = new EventBase();
		}

		if(empty($config['server']['ip'])){
			@$config['server']['ip'] = '127.0.0.1';
		}

		if(empty($config['server']['port'])){
			$config['server']['port'] = 9499;
		}

		if(empty($config['client']['ip'])){
			@$config['client']['ip'] = '0.0.0.0';
		}

		if(empty($config['client']['port'])){
			$config['client']['port'] = 20000;
		}

		if(empty($config['store']) || !($config['store'] instanceof EventStoreInterface)){
			if(empty($config['db'])){
				$config['db'] = array(
					'host'=>'localhost',
					'user'=>'sse',
					'pass'=>'password',
					'name'=>'SSE'
				);
			}
			$config['store'] = new EventStore(array('db'=>$config['db']));
		}

		$this->base = $config['base'];
		$this->server = $config['server'];
		$this->client = $config['client'];
		$this->store = $config['store'];
	}
}
