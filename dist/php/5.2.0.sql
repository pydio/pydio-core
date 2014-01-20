/* SEPARATOR */
ALTER TABLE `ajxp_feed` ADD COLUMN `index_path` MEDIUMTEXT NULL;
/* SEPARATOR */
ALTER TABLE `ajxp_simple_store` ADD COLUMN `insertion_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
/* SEPARATOR */
ALTER TABLE  `ajxp_simple_store` CHANGE  `serialized_data`  `serialized_data` LONGBLOB NULL DEFAULT NULL ;
/* SEPARATOR */
ALTER TABLE  `ajxp_plugin_configs` CHANGE  `configs`  `configs` LONGBLOB NOT NULL ;
/* SEPARATOR */
ALTER TABLE  `ajxp_user_prefs` CHANGE  `val`  `val` BLOB NULL DEFAULT NULL ;
/* SEPARATOR */
ALTER TABLE  `ajxp_repo_options` CHANGE  `val`  `val` BLOB NULL DEFAULT NULL ;
/* SEPARATOR */
ALTER TABLE  `ajxp_log` CHANGE  `remote_ip`  `remote_ip` VARCHAR( 45 ) NULL DEFAULT NULL ;
/* SEPARATOR */
CREATE TABLE IF NOT EXISTS ajxp_user_teams (
    team_id VARCHAR(255) NOT NULL,
    user_id varchar(255) NOT NULL,
    team_label VARCHAR(255) NOT NULL,
    owner_id varchar(255) NOT NULL,
    PRIMARY KEY(team_id, user_id)
);