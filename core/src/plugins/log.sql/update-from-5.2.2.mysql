CREATE TABLE `ajxp_log2` LIKE `ajxp_log.back`; INSERT `ajxp_log.back` SELECT * FROM `ajxp_log`;
CREATE TABLE `ajxp_log2` LIKE `ajxp_log`; INSERT `ajxp_log2` SELECT * FROM `ajxp_log`;
ALTER TABLE  `ajxp_log2` ADD  `source` VARCHAR( 255 ) NOT NULL AFTER  `user` , ADD INDEX (  `source` ) ;
UPDATE `ajxp_log2` INNER JOIN ajxp_log ON ajxp_log2.id=ajxp_log.id SET ajxp_log2.source = ajxp_log.message, ajxp_log2.message = SUBSTRING_INDEX(SUBSTRING_INDEX(ajxp_log.params, '\t', 1), '\t', -1),ajxp_log2.params = SUBSTRING_INDEX(SUBSTRING_INDEX(ajxp_log.params, '\t', 2), '\t', -1);
DROP TABLE `ajxp_log`;
RENAME TABLE `ajxp_log2` TO `ajxp_log`;