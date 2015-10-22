/* SEPARATOR */
ALTER TABLE  `ajxp_log`
  ADD COLUMN `repository_id` VARCHAR( 32 ) NOT NULL ,
  ADD COLUMN `device` VARCHAR( 255 ) NOT NULL ,
  ADD COLUMN `dirname`		VARCHAR(255),
  ADD COLUMN `basename`  VARCHAR(255),
  ADD INDEX (  `source` ),
  ADD INDEX (  `logdate` ),
  ADD INDEX (  `severity` ),
  ADD INDEX (  `basename` ),
  ADD INDEX (  `repository_id` ),
  ADD INDEX (  `dirname` ),
  ADD INDEX (  `basename` )
;
/* SEPARATOR */
ALTER TABLE `ajxp_roles`
    ADD COLUMN `last_udpated` INT(11) NOT NULL DEFAULT 0,
    ADD INDEX (`last_udpated`)
