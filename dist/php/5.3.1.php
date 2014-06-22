<?php
// FORCE bootstrap_context copy, otherwise it won't reboot
if (is_file(AJXP_INSTALL_PATH."/conf/bootstrap_context.php".".new-".date("Ymd"))) {
    rename(AJXP_INSTALL_PATH."/conf/bootstrap_context.php", AJXP_INSTALL_PATH."/conf/bootstrap_context.php.pre-update");
    rename(AJXP_INSTALL_PATH."/conf/bootstrap_context.php".".new-".date("Ymd"), AJXP_INSTALL_PATH."/conf/bootstrap_context.php");
}

// FORCE bootstrap_context copy, otherwise it won't reboot
if (is_file(AJXP_INSTALL_PATH."/conf/bootstrap_repositories.php".".new-".date("Ymd"))) {
    rename(AJXP_INSTALL_PATH."/conf/bootstrap_repositories.php", AJXP_INSTALL_PATH."/conf/bootstrap_repositories.php.pre-update");
    rename(AJXP_INSTALL_PATH."/conf/bootstrap_repositories.php".".new-".date("Ymd"), AJXP_INSTALL_PATH."/conf/bootstrap_repositories.php");
}

echo "The bootstrap_context and bootstrap_repositories files were replaced by the new version, the .pre-update version is kept.";

$dbInst = "
/* SEPARATOR */
ALTER TABLE ajxp_user_rights ADD INDEX (login), ADD INDEX (repo_uuid);
/* SEPARATOR */
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
/* SEPARATOR */
CREATE TABLE IF NOT EXISTS `ajxp_index` (
  `node_id` int(20) NOT NULL AUTO_INCREMENT,
  `node_path` text NOT NULL,
  `bytesize` bigint(20) NOT NULL,
  `md5` varchar(32) NOT NULL,
  `mtime` int(11) NOT NULL,
  `repository_identifier` text NOT NULL,
  PRIMARY KEY (`node_id`)
);
/* SEPARATOR */
DROP TRIGGER IF EXISTS `LOG_DELETE`;
/* SEPARATOR */
CREATE TRIGGER `LOG_DELETE` AFTER DELETE ON `ajxp_index`
FOR EACH ROW INSERT INTO ajxp_changes (repository_identifier, node_id,source,target,type)
  VALUES (old.repository_identifier, old.node_id, old.node_path, 'NULL', 'delete');
/* SEPARATOR */
DROP TRIGGER IF EXISTS `LOG_INSERT`;
/* SEPARATOR */
CREATE TRIGGER `LOG_INSERT` AFTER INSERT ON `ajxp_index`
FOR EACH ROW INSERT INTO ajxp_changes (repository_identifier, node_id,source,target,type)
  VALUES (new.repository_identifier, new.node_id, 'NULL', new.node_path, 'create');
/* SEPARATOR */
DROP TRIGGER IF EXISTS `LOG_UPDATE`;
/* SEPARATOR */
CREATE TRIGGER `LOG_UPDATE` AFTER UPDATE ON `ajxp_index`
FOR EACH ROW INSERT INTO ajxp_changes (repository_identifier, node_id,source,target,type)
  VALUES (new.repository_identifier, new.node_id, old.node_path, new.node_path, CASE old.node_path = new.node_path WHEN true THEN 'content' ELSE 'path' END);
/* SEPARATOR */
CREATE TABLE `ajxp_log2` LIKE `ajxp_log.back`;
/* SEPARATOR */
INSERT `ajxp_log.back` SELECT * FROM `ajxp_log`;
/* SEPARATOR */
CREATE TABLE `ajxp_log2` LIKE `ajxp_log`;
/* SEPARATOR */
INSERT `ajxp_log2` SELECT * FROM `ajxp_log`;
/* SEPARATOR */
ALTER TABLE  `ajxp_log2` ADD  `source` VARCHAR( 255 ) NOT NULL AFTER  `user` , ADD INDEX (  `source` ) ;
/* SEPARATOR */
UPDATE `ajxp_log2` INNER JOIN ajxp_log ON ajxp_log2.id=ajxp_log.id SET ajxp_log2.source = ajxp_log.message, ajxp_log2.message = SUBSTRING_INDEX(SUBSTRING_INDEX(ajxp_log.params, '\t', 1), '\t', -1),ajxp_log2.params = SUBSTRING_INDEX(SUBSTRING_INDEX(ajxp_log.params, '\t', 2), '\t', -1);
/* SEPARATOR */
DROP TABLE `ajxp_log`;
/* SEPARATOR */
RENAME TABLE `ajxp_log2` TO `ajxp_log`;
";


$confDriver = ConfService::getConfStorageImpl();
$authDriver = ConfService::getAuthDriverImpl();
$logger = AJXP_Logger::getInstance();
if (is_a($confDriver, "sqlConfDriver")) {
    $test = $confDriver->getOption("SQL_DRIVER");
    if (!isSet($test["driver"])) {
        $test = AJXP_Utils::cleanDibiDriverParameters($confDriver->getOption("SQL_DRIVER"));
    }
    if (is_array($test) && isSet($test["driver"]) && $test["driver"] == "mysql") {

        echo "Upgrading MYSQL database ...";

        $parts = array_map("trim", explode("/* SEPARATOR */", $dbInst));
        $results = array();
        $errors = array();

        require_once(AJXP_BIN_FOLDER."/dibi.compact.php");
        dibi::connect($test);
        dibi::begin();
        foreach ($parts as $sqlPart) {
            if(empty($sqlPart)) continue;
            try {
                dibi::nativeQuery($sqlPart);
                echo "<div class='upgrade_result success'>$sqlPart ... OK</div>";
            } catch (DibiException $e) {
                $errors[] = $e->getMessage();
                echo "<div class='upgrade_result success'>$sqlPart ... FAILED (".$e->getMessage().")</div>";
            }
        }
        dibi::commit();
        dibi::disconnect();

    } else if(is_array($test) && $test["driver"] != "mysql"){

        echo "Cannot auto-upgrade Sqlite or PostgreSql DB automatically, please review the update instructions.";

    } else {

        echo "Nothing to do for the DB";

    }

} else {

    echo "Nothing to do for the DB";

}
