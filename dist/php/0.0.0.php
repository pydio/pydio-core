<?php

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
        $sqlInstructions = file_get_contents($this->workingFolder."/".$this->dbUpgrade.".sql");

        $parts = array_map("trim", explode("/* SEPARATOR */", $sqlInstructions));
        $results = array();
        $errors = array();

        require_once(AJXP_BIN_FOLDER."/dibi.compact.php");
        dibi::connect($test);
        dibi::begin();
        foreach ($parts as $sqlPart) {
            if(empty($sqlPart)) continue;
            try {
                //dibi::nativeQuery($sqlPart);
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
