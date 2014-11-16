CREATE DATABASE IF NOT EXISTS SSE;

DROP TABLE `subscriptions`;

CREATE TABLE `subscriptions` (
  `uuid` varchar(40) NOT NULL DEFAULT '',
  `name` varchar(10) NOT NULL DEFAULT '',
  UNIQUE KEY `uuid_name` (`uuid`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE `events`;

CREATE TABLE `events` (
  `uuid` varchar(40) NOT NULL DEFAULT '',
  `id` int(10) NOT NULL DEFAULT '0',
  `data` varchar(1024) NOT NULL DEFAULT '',
  UNIQUE KEY `uuid_id` (`uuid`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


