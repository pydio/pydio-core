<?php
// FORCE bootstrap_context copy, otherwise it won't reboot
if (is_file(AJXP_INSTALL_PATH."/conf/bootstrap_context.php".".new-".date("Ymd"))) {
    rename(AJXP_INSTALL_PATH."/conf/bootstrap_context.php", AJXP_INSTALL_PATH."/conf/bootstrap_context.php.orig");
    rename(AJXP_INSTALL_PATH."/conf/bootstrap_context.php".".new-".date("Ymd"), AJXP_INSTALL_PATH."/conf/bootstrap_context.php");
}

echo "The bootstrap_context was replaced by the new version, the .orig version is kept.";

// Clear i18n cache
$i18nFiles = glob(dirname(AJXP_PLUGINS_MESSAGES_FILE)."/i18n/*.ser");
if (is_array($i18nFiles)) {
    foreach ($i18nFiles as $file) {
        @unlink($file);
    }
}
echo "Force clearing i18n cache";

// Create first_run_passed file to avoid running the installer.
copy(AJXP_CACHE_DIR."/admin_counted", AJXP_CACHE_DIR."/first_run_passed");

echo "Avoid running installer";

echo "Upgrading database ...";

$confDriver = ConfService::getConfStorageImpl();
$authDriver = ConfService::getAuthDriverImpl();
$logger = AJXP_Logger::getInstance();
if (is_a($confDriver, "sqlConfDriver")) {
    $test = $confDriver->getOption("SQL_DRIVER");
    if (!isSet($test["driver"])) {
        $test = AJXP_Utils::cleanDibiDriverParameters($confDriver->getOption("SQL_DRIVER"));
    }
    if (is_array($test) && isSet($test["driver"])) {
        $sqlInstructions = file_get_contents($this->workingFolder."/UPGRADE/DB-UPGRADE.sql");

        $parts = array_map("trim", explode("/* SEPARATOR */", $sqlInstructions));
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

    } else {
        echo "Nothing to do for the DB";
    }

} else {
    echo "Nothing to do for the DB";
}

$htmlInstructions = file_get_contents($this->workingFolder."/UPGRADE/NOTE-HTML");
echo($htmlInstructions);
