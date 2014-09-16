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

$pgDbInsts = array(
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
    if (is_array($test) && isSet($test["driver"]) && $test["driver"] == "postgre") {

        echo "Upgrading PostgreSQL database ...";

        $results = array();
        $errors = array();

        require_once(AJXP_BIN_FOLDER."/dibi.compact.php");
        dibi::connect($test);
        dibi::begin();
        foreach ($pgDbInsts as $sqlPart) {
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

        echo "DB Already upgraded via the script";

    }

} else {

    echo "Nothing to do for the DB";

}
