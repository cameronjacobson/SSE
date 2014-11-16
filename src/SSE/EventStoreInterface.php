<?php

namespace SSE;

use SSE\Event;

interface EventStoreInterface
{
	public function put(Event $event);

	public function get($id = null);

	public function delete($uuid);

	public function getSubscriptions($uuid);
}
