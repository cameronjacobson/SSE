<?php

namespace SSE;

use \PDO;
use \SSE\Event;
use \SSE\Subscription;

class EventStore implements EventStoreInterface
{
	private $db;
	private $db_name;

	public function __construct(array $config){
		$this->id = 0;
		$this->db_config = $config['db'];
		$this->DB = $config['db']['name'];
		$this->db = new PDO('mysql:host='.$this->db_config['host'].';dbname='.$this->DB,
			$this->db_config['user'],
			$this->db_config['pass']
		);
	}


	public function putEvent(Event $event){
		$sql = 'INSERT INTO events SET uuid = :uuid, id = :id, data = :data
			ON DUPLICATE KEY UPDATE data = :data';
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array(
			':uuid'=>$event->uuid,
			':id'=>$event->id,
			':data'=>$event->data
		));
	}

	public function putEvents(array $events){
		foreach($events as $event){
			$this->putEvent($event);
		}
	}



	public function putSubscription(Subscription $subscription){
		$sql = 'INSERT IGNORE INTO subscriptions SET uuid = :uuid, name = :name';
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array(
			':uuid'=>$subscription->uuid,
			':name'=>$subscription->name
		));
	}

	public function putSubscriptions(array $subscriptions){
		foreach($subscriptions as $subscription){
			$this->putSubscription($subscription);
		}
	}


	public function getAllSubscriptions($uuid){
		$sql = 'SELECT uuid,name FROM subscriptions';
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array(
			':uuid'=>$uuid
		));
		$return = array();
		$subscriptions = $stmt->fetchAll();
		foreach($subscriptions as $subscription){
			$return[$subscription['name']] = @$return[$subscription['name']] || array();
			$return[$subscription['name']][] = $subscription['uuid'];
		}
		return $return;
	}


	public function getEvents($uuid, $id = 0){
		$this->id = $id;
		if(empty($id)){
			$this->deleteEvents($uuid);
			$this->deleteSubscriptions($uuid);
			return array();
		}

		$sql = 'SELECT id,data
			FROM events
			WHERE uuid=:uuid AND id>=:id
			ORDER BY id ASC';
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array(
			':id'=>$id
		));
		return $stmt->fetchAll();
	}

	public function getSubscriptions($uuid){
		$sql = 'SELECT name FROM subscriptions WHERE uuid=:uuid';
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array(
			':uuid'=>$uuid
		));
		return $stmt->fetchAll();
	}



	public function deleteEvents($uuid){
		$sql = 'DELETE FROM events WHERE uuid=:uuid';
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array(
			':uuid'=>$uuid
		));
	}

	public function deleteSubscriptions($uuid){
		$sql = 'DELETE FROM subscriptions WHERE uuid=:uuid';
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array(
			':uuid'=>$uuid
		));
	}
}
