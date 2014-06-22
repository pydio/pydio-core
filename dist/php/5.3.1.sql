ALTER TABLE ajxp_user_rights ADD INDEX (login), ADD INDEX (repo_uuid);


-- CREATE TABLES FOR META.SYNCABLE PLUGIN

CREATE TABLE IF NOT EXISTS `ajxp_changes` (
  `seq` int(20) NOT NULL AUTO_INCREMENT,
  `repository_identifier` TEXT NOT NULL,
  `node_id` bigint(20) NOT NULL,
  `type` enum('create','delete','path','content') NOT NULL,
  `source` text NOT NULL,
  `target` text NOT NULL,
  PRIMARY KEY (`seq`),
  KEY `node_id` (`node_id`,`type`)
);

CREATE TABLE IF NOT EXISTS `ajxp_index` (
  `node_id` int(20) NOT NULL AUTO_INCREMENT,
  `node_path` text NOT NULL,
  `bytesize` bigint(20) NOT NULL,
  `md5` varchar(32) NOT NULL,
  `mtime` int(11) NOT NULL,
  `repository_identifier` text NOT NULL,
  PRIMARY KEY (`node_id`)
);

DROP TRIGGER IF EXISTS `LOG_DELETE`;

CREATE TRIGGER `LOG_DELETE` AFTER DELETE ON `ajxp_index`
FOR EACH ROW INSERT INTO ajxp_changes (repository_identifier, node_id,source,target,type)
  VALUES (old.repository_identifier, old.node_id, old.node_path, 'NULL', 'delete');

DROP TRIGGER IF EXISTS `LOG_INSERT`;

CREATE TRIGGER `LOG_INSERT` AFTER INSERT ON `ajxp_index`
FOR EACH ROW INSERT INTO ajxp_changes (repository_identifier, node_id,source,target,type)
  VALUES (new.repository_identifier, new.node_id, 'NULL', new.node_path, 'create');

DROP TRIGGER IF EXISTS `LOG_UPDATE`;

CREATE TRIGGER `LOG_UPDATE` AFTER UPDATE ON `ajxp_index`
FOR EACH ROW INSERT INTO ajxp_changes (repository_identifier, node_id,source,target,type)
  VALUES (new.repository_identifier, new.node_id, old.node_path, new.node_path, CASE old.node_path = new.node_path WHEN true THEN 'content' ELSE 'path' END);


-- RESPLIT LOGS TABLE
CREATE TABLE `ajxp_log2` LIKE `ajxp_log.back`; INSERT `ajxp_log.back` SELECT * FROM `ajxp_log`;
CREATE TABLE `ajxp_log2` LIKE `ajxp_log`; INSERT `ajxp_log2` SELECT * FROM `ajxp_log`;
ALTER TABLE  `ajxp_log2` ADD  `source` VARCHAR( 255 ) NOT NULL AFTER  `user` , ADD INDEX (  `source` ) ;
UPDATE `ajxp_log2` INNER JOIN ajxp_log ON ajxp_log2.id=ajxp_log.id SET ajxp_log2.source = ajxp_log.message, ajxp_log2.message = SUBSTRING_INDEX(SUBSTRING_INDEX(ajxp_log.params, '\t', 1), '\t', -1),ajxp_log2.params = SUBSTRING_INDEX(SUBSTRING_INDEX(ajxp_log.params, '\t', 2), '\t', -1);
DROP TABLE `ajxp_log`;
RENAME TABLE `ajxp_log2` TO `ajxp_log`;