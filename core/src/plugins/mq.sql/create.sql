CREATE TABLE IF NOT EXISTS `ajxp_mq_queues` (
  `channel_name` varchar(255) NOT NULL,
  `content` text NOT NULL,
  PRIMARY KEY (`channel_name`)
) DEFAULT CHARSET=utf8;