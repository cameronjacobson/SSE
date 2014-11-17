<?php

namespace SSE;

use \SSE\SSEEvent;
use \SSE\Subscription;

interface EventStoreInterface
{
	public function putEvent(SSEEvent $event);

	public function putSubscription(Subscription $event);

	public function getEvents($uuid, $id=0);

	public function getSubscriptions($uuid);

	public function getAllSubscriptions();

	public function deleteEvents($uuid);

	public function deleteSubscriptions($uuid);
}
