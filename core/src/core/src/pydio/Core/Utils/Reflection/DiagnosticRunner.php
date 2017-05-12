<?php
/*
 * Copyright 2007-2017 Abstrium <contact (at) pydio.com>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <https://pydio.com/>.
 */
namespace Pydio\Core\Utils\Reflection;

use Pydio\Core\Services\RepositoryService;
use Pydio\Tests\AbstractTest;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class DiagnosticRunner: run startup tests and output them in different formats
 * @package Pydio\Core\Utils
 */
class DiagnosticRunner
{

    /**
     * Generate an HTML table for the tests results. We should use a template somewhere...
     * @static
     * @param $outputArray
     * @param $testedParams
     * @param bool $showSkipLink
     * @return string
     */
    public static function testResultsToTable($outputArray, $testedParams, $showSkipLink = true)
    {
        $dumpRows = "";
        $passedRows = array();
        $warnRows = "";
        $errRows = "";
        $errs = $warns = 0;
        $ALL_ROWS = array(
            "error" => array(),
            "warning" => array(),
            "dump" => array(),
            "passed" => array(),
        );
        $TITLES = array(
            "error" => "Failed Tests",
            "warning" => "Warnings",
            "dump" => "Server Information",
            "passed" => "Other tests passed",
        );
        foreach ($outputArray as $item) {

            // A test is output only if it hasn't succeeded (doText returned FALSE)
            $result = $item["result"] ? "passed" : ($item["level"] == "info" ? "dump" : ($item["level"] == "warning"
                ? "warning" : "error"));
            $success = $result == "passed";
            if ($result == "dump") $result = "passed";
            $ALL_ROWS[$result][$item["name"]] = $item["info"];
        }
        ob_start();
        include(AJXP_TESTS_FOLDER . "/startup.phtml");
        return ob_get_flush();
    }

    /**
     * @static
     * @param $outputArray
     * @param $testedParams
     * @return bool
     */
    public static function runTests(&$outputArray, &$testedParams)
    {
        // At first, list folder in the tests subfolder
        chdir(AJXP_TESTS_FOLDER);
        $files = glob('*.php');

        $outputArray = array();
        $testedParams = array();
        $passed = true;
        foreach ($files as $file) {
            require_once($file);
            // Then create the test class
            $testName = "Pydio\\Tests\\" . str_replace(".php", "", $file);
            if (!class_exists($testName) || $testName == "Pydio\\Tests\\AbstractTest") continue;
            $class = new $testName();
            if (!($class instanceof AbstractTest)) continue;

            $result = $class->doTest();
            if (!$result && $class->failedLevel != "info") $passed = false;
            $outputArray[] = array(
                "name" => $class->name,
                "result" => $result,
                "level" => $class->failedLevel,
                "info" => $class->failedInfo);
            if (count($class->testedParams)) {
                $testedParams = array_merge($testedParams, $class->testedParams);
            }
        }
        // PREPARE REPOSITORY LISTS
        $repoList = array();
        $REPOSITORIES = array();
        //require_once("../classes/class.ConfService.php");
        //require_once("../classes/class.Repository.php");
        include(AJXP_CONF_PATH . "/bootstrap_repositories.php");
        foreach ($REPOSITORIES as $index => $repo) {
            $repoList[] = RepositoryService::createRepositoryFromArray($index, $repo);
        }
        // Try with the serialized repositories
        if (is_file(AJXP_DATA_PATH . "/plugins/conf.serial/repo.ser")) {
            $fileLines = file(AJXP_DATA_PATH . "/plugins/conf.serial/repo.ser");
            $repos = unserialize($fileLines[0]);
            $repoList = array_merge($repoList, $repos);
        }

        // NOW TRY THE PLUGIN TESTS
        chdir(AJXP_INSTALL_PATH . "/" . AJXP_PLUGINS_FOLDER);
        $files = glob('access.*/test.*.php');
        foreach ($files as $file) {
            require_once($file);
            // Then create the test class
            list($accessFolder, $testFileName) = explode("/", $file);
            $testName = "Pydio\\Tests\\" . str_replace(".php", "", substr($testFileName, 5) . "Test");
            $class = new $testName();
            foreach ($repoList as $repository) {
                if ($repository->isTemplate || $repository->getParentId() != null) continue;
                if (!($class instanceof AbstractTest)) continue;
                $result = $class->doRepositoryTest($repository);
                if ($result === false || $result === true) {
                    if (!$result && $class->failedLevel != "info") {
                        $passed = false;
                    }
                    $outputArray[] = array(
                        "name" => $class->name . "\n Testing repository : " . $repository->getDisplay(),
                        "result" => $result,
                        "level" => $class->failedLevel,
                        "info" => $class->failedInfo);
                    if (count($class->testedParams)) {
                        $testedParams = array_merge($testedParams, $class->testedParams);
                    }
                }
            }
        }

        return $passed;
    }

    /**
     * @static
     * @param $outputArray
     * @param $testedParams
     * @return void
     */
    public static function testResultsToFile($outputArray, $testedParams)
    {
        ob_start();
        echo '$diagResults = ';
        var_export($testedParams);
        echo ';';
        echo '$outputArray = ';
        var_export($outputArray);
        echo ';';
        $content = '<?php ' . ob_get_contents() . ' ?>';
        ob_end_clean();
        if(!file_exists(dirname(TESTS_RESULT_FILE))){
            mkdir(dirname(TESTS_RESULT_FILE), 0666, true);
        }
        file_put_contents(TESTS_RESULT_FILE, $content);
    }
}