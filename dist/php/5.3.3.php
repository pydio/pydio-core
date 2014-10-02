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

echo "<br>The bootstrap_context and bootstrap_repositories files were replaced by the new version, the .pre-update version is kept.";

function buildPublicHtaccessContent()
{
    $downloadFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
    $dlURL = ConfService::getCoreConf("PUBLIC_DOWNLOAD_URL");
    if ($dlURL != "") {
        $url = rtrim($dlURL, "/");
    } else {
        $fullUrl = AJXP_Utils::detectServerURL(true);
        $url =  str_replace("\\", "/", rtrim($fullUrl, "/").rtrim(str_replace(AJXP_INSTALL_PATH, "", $downloadFolder), "/"));
    }
    $htaccessContent = "Order Deny,Allow\nAllow from all\n";
    $htaccessContent .= "\n<Files \".ajxp_*\">\ndeny from all\n</Files>\n";
    $path = parse_url($url, PHP_URL_PATH);
    $htaccessContent .= '
        <IfModule mod_rewrite.c>
        RewriteEngine on
        RewriteBase '.$path.'
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^([a-zA-Z0-9_-]+)\.php$ share.php?hash=$1 [QSA]
        RewriteRule ^([a-zA-Z0-9_-]+)--([a-z]+)$ share.php?hash=$1&lang=$2 [QSA]
        RewriteRule ^([a-zA-Z0-9_-]+)$ share.php?hash=$1 [QSA]
        </IfModule>
        ';
    return $htaccessContent;
}

function updateBaseHtaccessContent(){

    $uri = $_SERVER["REQUEST_URI"];
    if(strpos($uri, '.php') !== false) $uri = AJXP_Utils::safeDirname($uri);
    if(empty($uri)) $uri = "/";

    $tpl = file_get_contents(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/boot.conf/htaccess.tpl");
    if($uri == "/"){
        $htContent = str_replace('${APPLICATION_ROOT}/', "/", $tpl);
        $htContent = str_replace('${APPLICATION_ROOT}', "/", $htContent);
    }else{
        $htContent = str_replace('${APPLICATION_ROOT}', $uri, $tpl);
    }

    if(is_writeable(AJXP_INSTALL_PATH."/.htaccess")){
        echo '<br>Updating Htaccess';
        file_put_contents(AJXP_INSTALL_PATH."/.htaccess", $htContent);
    }else{
        echo '<br>Cannot write htaccess file, please copy and paste the code below: <br><pre>'.$htContent.'</pre>';
    }
}

function upgradeRootRoleForWelcome(){
    $rootRole = AuthService::getRole("ROOT_ROLE");
    if(!empty($rootRole)){
        echo '<br>Upgrading Root Role to let users access the new welcome page<br>';
        $rootRole->setAcl("ajxp_home", "rw");
        $rootRole->setParameterValue("core.conf", "DEFAULT_START_REPOSITORY", "ajxp_home");
        AuthService::updateRole($rootRole);
    }

}


if(AJXP_VERSION == "5.2.3"){

    $sqliteUpgrade = 'CREATE TABLE ajxp_changes (
  seq INTEGER PRIMARY KEY AUTOINCREMENT,
  repository_identifier TEXT,
  node_id NUMERIC,
  type TEXT,
  source TEXT,
  target TEXT
);
/* SEPARATOR */
CREATE TABLE ajxp_index (
  node_id INTEGER PRIMARY KEY AUTOINCREMENT,
  repository_identifier TEXT,
  node_path TEXT,
  bytesize NUMERIC,
  md5 TEXT,
  mtime NUMERIC
);
/* SEPARATOR */
CREATE TRIGGER LOG_DELETE AFTER DELETE ON ajxp_index
BEGIN
  INSERT INTO ajxp_changes (repository_identifer, node_id,source,target,type) VALUES (old.repository_identifer, old.node_id, old.node_path, "NULL", "delete");
END;
/* SEPARATOR */
CREATE TRIGGER LOG_INSERT AFTER INSERT ON ajxp_index
BEGIN
  INSERT INTO ajxp_changes (repository_identifer, node_id,source,target,type) VALUES (new.repository_identifer, new.node_id, "NULL", new.node_path, "create");
END;
/* SEPARATOR */
CREATE TRIGGER "LOG_UPDATE_CONTENT" AFTER UPDATE ON "ajxp_index" FOR EACH ROW  WHEN old.node_path=new.node_path
BEGIN
  INSERT INTO ajxp_changes (repository_identifer, node_id,source,target,type) VALUES (new.repository_identifer, new.node_id, old.node_path, new.node_path, "content");
END;
/* SEPARATOR */
CREATE TRIGGER "LOG_UPDATE_PATH" AFTER UPDATE ON "ajxp_index" FOR EACH ROW  WHEN old.node_path!=new.node_path
BEGIN
  INSERT INTO ajxp_changes (repository_identifer, node_id,source,target,type) VALUES (new.repository_identifer, new.node_id, old.node_path, new.node_path, "path");
END;

/* SEPARATOR */
ALTER TABLE "ajxp_log" ADD COLUMN "source" text;
';

    $mysqlUpgrade = "
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
CREATE TABLE `ajxp_log.bak` LIKE `ajxp_log`;
/* SEPARATOR */
INSERT `ajxp_log.bak` SELECT * FROM `ajxp_log`;
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

    $pgDbInsts = array(

        "CREATE INDEX ajxp_user_rights_i ON ajxp_user_rights(repo_uuid);
    /* SEPARATOR */
    CREATE INDEX ajxp_user_rights_k ON ajxp_user_rights(login);

    /* SEPARATOR */
    CREATE TYPE ajxp_change_type AS ENUM ('create','delete','path','content');
    /* SEPARATOR */
    CREATE TABLE ajxp_changes (
      seq BIGSERIAL,
      repository_identifier TEXT NOT NULL,
      node_id INTEGER NOT NULL,
      type ajxp_change_type NOT NULL,
      source text NOT NULL,
      target text NOT NULL,
      constraint pk primary key(seq)
    );
    /* SEPARATOR */
    CREATE INDEX ajxp_changes_node_id ON ajxp_changes (node_id);
    /* SEPARATOR */
    CREATE INDEX ajxp_changes_repo_id ON ajxp_changes (repository_identifier);
    /* SEPARATOR */
    CREATE INDEX ajxp_changes_type ON ajxp_changes (type);
    /* SEPARATOR */
    CREATE TABLE ajxp_index (
      node_id BIGSERIAL ,
      node_path text NOT NULL,
      bytesize INTEGER NOT NULL,
      md5 varchar(32) NOT NULL,
      mtime INTEGER NOT NULL,
      repository_identifier text NOT NULL,
      PRIMARY KEY (node_id)
    );
    /* SEPARATOR */
    CREATE INDEX ajxp_index_repo_id ON ajxp_index (repository_identifier);
    /* SEPARATOR */
    CREATE INDEX ajxp_index_md5 ON ajxp_index (md5);

    /* SEPARATOR */
    CREATE TABLE ajxp_log2 AS TABLE ajxp_log;
    /* SEPARATOR */
    ALTER TABLE  ajxp_log2 ADD source VARCHAR( 255 );
    /* SEPARATOR */
    ALTER TABLE  ajxp_log2 ADD PRIMARY KEY (id);
    /* SEPARATOR */
    UPDATE ajxp_log2 SET source = ajxp_log.message, message = split_part(ajxp_log.params,'\t', 1), params = split_part(ajxp_log.params,'\t', 2) FROM ajxp_log WHERE ajxp_log2.id = ajxp_log.id;
    /* SEPARATOR */
    DROP TABLE ajxp_log;
    /* SEPARATOR */
    ALTER TABLE ajxp_log2 RENAME TO ajxp_log;",

        "CREATE FUNCTION ajxp_index_delete() RETURNS trigger AS \$ajxp_index_delete\$
        BEGIN
          INSERT INTO ajxp_changes (repository_identifier, node_id,source,target,type)
            VALUES (OLD.repository_identifier, OLD.node_id, OLD.node_path, 'NULL', 'delete');
          RETURN NULL;
        END;
        \$ajxp_index_delete\$ LANGUAGE plpgsql;
        ",
        "CREATE FUNCTION ajxp_index_insert() RETURNS trigger AS \$ajxp_index_insert\$
        BEGIN
          INSERT INTO ajxp_changes (repository_identifier, node_id,source,target,type)
            VALUES (NEW.repository_identifier, NEW.node_id, 'NULL', NEW.node_path, 'create');
          RETURN NEW;
        END;
        \$ajxp_index_insert\$ LANGUAGE plpgsql;",

        "CREATE FUNCTION ajxp_index_update() RETURNS trigger AS \$ajxp_index_update\$
            BEGIN
              IF OLD.node_path = NEW.node_path THEN
                INSERT INTO ajxp_changes (repository_identifier, node_id,source,target,type)
                  VALUES (NEW.repository_identifier, NEW.node_id, OLD.node_path, NEW.node_path, 'content');
              ELSE
                INSERT INTO ajxp_changes (repository_identifier, node_id,source,target,type)
                  VALUES (NEW.repository_identifier, NEW.node_id, OLD.node_path, NEW.node_path, 'path');
              END IF;
              RETURN NEW;
            END;
            \$ajxp_index_update\$ LANGUAGE plpgsql;",
        "CREATE TRIGGER LOG_DELETE AFTER DELETE ON ajxp_index FOR EACH ROW EXECUTE PROCEDURE ajxp_index_delete();",
        "CREATE TRIGGER LOG_INSERT AFTER INSERT ON ajxp_index FOR EACH ROW EXECUTE PROCEDURE ajxp_index_insert();",
        "CREATE TRIGGER LOG_UPDATE AFTER UPDATE ON ajxp_index FOR EACH ROW EXECUTE PROCEDURE ajxp_index_update();"
    );


    $confDriver = ConfService::getConfStorageImpl();
    $authDriver = ConfService::getAuthDriverImpl();
    $logger = AJXP_Logger::getInstance();
    if (is_a($confDriver, "sqlConfDriver")) {
        $test = $confDriver->getOption("SQL_DRIVER");
        if (!isSet($test["driver"])) {
            $test = AJXP_Utils::cleanDibiDriverParameters($confDriver->getOption("SQL_DRIVER"));
        }
        if (is_array($test) && isSet($test["driver"])) {

            $results = array();
            $errors = array();
            $parts = array();
            if($test["driver"] == "postgre"){
                echo "Upgrading PostgreSQL database ...";
                foreach($pgDbInsts as $pgPart){
                    $parts = array_merge($parts, explode("/* SEPARATOR */", $pgPart));
                }
            }else if($test["driver"] == "mysql"){
                echo "Upgrading MySQL database ...";
                $parts = explode("/* SEPARATOR */", $mysqlUpgrade);
            }else if($test["driver"] == "sqlite3"){
                echo "Upgrading Sqlite database ...";
                $parts = explode("/* SEPARATOR */", $sqliteUpgrade);
            }


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

        } else {

            echo "<br>DB Already upgraded via the script<br>";

        }

    } else {

        echo "<br>Nothing to do for the DB<br>";

    }



    // Update HTACCESS PUBLIC FILE
    $publicHtaccess = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER")."/.htaccess";
    if(file_exists($publicHtaccess)){
        $content = file_get_contents($publicHtaccess);
        if(strpos($content, "RewriteCond") === false){
            if(is_writable($publicHtaccess)){
                echo '<br>Updating public folder htaccess file<br>';
                file_put_contents($publicHtaccess, buildPublicHtaccessContent());
            }else{
                echo '<br>You should update your public htaccess file to server the new shares : <br><pre>'.buildPublicHtaccessContent().'</pre>';
            }
        }
    }

    // Update HTACCESS FILE
    updateBaseHtaccessContent();
    upgradeRootRoleForWelcome();

}