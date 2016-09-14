


ALTER TABLE  `ajxp_log` CHANGE  `dirname`  `dirname` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL ,
CHANGE  `basename`  `basename` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL ;

ALTER TABLE  `ajxp_log` DROP INDEX `basename`;
ALTER TABLE  `ajxp_log` DROP INDEX `dirname`;

ALTER TABLE  `ajxp_log` ADD INDEX (  `user` ) ;
ALTER TABLE  `ajxp_log` ADD INDEX (  `dirname`, `basename` ) ;


CREATE TABLE IF NOT EXISTS `ajxp_tasks` (
  `uid` varchar(255) NOT NULL,
  `type` int(11) NOT NULL,
  `parent_uid` varchar(255) DEFAULT NULL,
  `flags` int(11) NOT NULL,
  `label` varchar(255) NOT NULL,
  `userId` varchar(255) NOT NULL,
  `wsId` varchar(32) NOT NULL,
  `status` int(11) NOT NULL,
  `status_msg` mediumtext NOT NULL,
  `progress` int(11) NOT NULL,
  `schedule` int(11) NOT NULL,
  `schedule_value` varchar(255) DEFAULT NULL,
  `action` text NOT NULL,
  `parameters` mediumtext NOT NULL,
  `nodes` text NOT NULL,
  `creation_date` int(11) NOT NULL DEFAULT '0' COMMENT 'Date of creation of the job',
  `status_update` int(11) NOT NULL DEFAULT '0' COMMENT 'Last time the status was updated',
  PRIMARY KEY (`uid`),
  KEY `userId` (`userId`,`status`),
  KEY `type` (`type`),
  FULLTEXT KEY `nodes` (`nodes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Task persistence layer';

