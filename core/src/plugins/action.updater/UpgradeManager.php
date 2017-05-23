<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <https://pydio.com>.
 */

namespace Pydio\Action\Update;

use dibi;
use DibiException;
use Exception;
use PclZip;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Utils\FileHelper;
use Pydio\Core\Utils\Vars\OptionsHelper;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * @package AjaXplorer_Plugins
 * @subpackage Action
 */
class UpgradeManager
{
    private $archiveURL;
    private $archiveHash;
    private $archiveHashMethod;
    private $markedFiles;

    private $debugMode = FALSE;
    private $cleanFile = "UPGRADE/CLEAN-FILES";
    private $additionalScript = "UPGRADE/PHP-SCRIPT";
    private $stepTriggerPrefix = "UPGRADE/PHP-";
    private $releaseNote = "UPGRADE/NOTE";
    private $htmlInstructions = "UPGRADE/NOTE-HTML";
    private $dbUpgrade = "UPGRADE/DB-UPGRADE";
    private $installPath;
    private static $context = null;

    private $archive;
    private $workingFolder;
    private $steps;
    public $step = 0;

    public $error = null;
    public $result = "";
    public $currentStepTitle;

    /**
     * UpgradeManager constructor.
     * @param $archiveURL
     * @param $hash
     * @param $method
     * @param array $backupFiles
     */
    public function __construct($archiveURL, $hash, $method, $backupFiles = array())
    {
        $this->archiveURL = $archiveURL;
        $this->archiveHash = $hash;
        $this->archiveHashMethod = $method;
        $this->markedFiles = $backupFiles;

        $this->installPath = AJXP_INSTALL_PATH;
        if ($this->debugMode) {
            @mkdir(AJXP_INSTALL_PATH . "/upgrade_test");
            $this->installPath = AJXP_INSTALL_PATH . "/upgrade_test";
        }

        $this->workingFolder = AJXP_DATA_PATH . "/tmp/update";
        $this->steps = array(
            "checkDownloadFolder" => "Checking download permissions",
            "downloadArchive" => "Downloading upgrade archive",
            "checkArchiveIntegrity" => "Checking archive integrity",
            "checkTargetFolder" => "Checking files and folders permissions, this may take a while.",
            "extractArchive" => "Extracting Archive",
            "backupMarkedFiles" => "Backuping your modified files",
            "copyCodeFiles" => "Copying core source files",
            "restoreMarkedFiles" => "Restoring your modified files",
            "duplicateConfFiles" => "Copying configuration files",
            "cleanUnusedFiles" => "Deleting unused files",
            "upgradeDB" => "Upgrading database",
            "specificTask" => "Running specific upgrade task",
            "updateVersion" => "Everything went ok, upgrading version!",
            "clearCache" => "Clearing plugins cache",
            "displayNote" => "Release note : ",
            "displayUpgradeInstructions" => "Upgrade instructions",
        );

    }

    /**
     * @param $proxyHost
     * @param $proxyUser
     * @param $proxyPass
     * @param string $siteUser
     * @param string $sitePass
     */
    public static function configureProxy($proxyHost, $proxyUser, $proxyPass, $siteUser = "", $sitePass = "")
    {
        $contextData = array('http' => array());
        if (!empty($proxyHost)) {
            $contextData['http']['proxy'] = 'tcp://' . $proxyHost;// $proxy = array( 'http' => array( 'proxy' => 'tcp://'.$proxyHost, 'request_fulluri' => true ) );
            $contextData['http']['request_fulluri'] = true;
            $contextData['ssl']['SNI_enabled'] = false;
            if (!empty($proxyUser) && !empty($proxyPass)) {
                $auth = base64_encode($proxyUser . ":" . $proxyPass);
                $contextData['http']['header'] = "Proxy-Authorization: Basic $auth";
            }
        }
        if (!empty($siteUser) && !empty($sitePass)) {
            $headerString = "Authorization: Basic " . base64_encode("$siteUser:$sitePass");
            if (isSet($contextData['http']['header'])) {
                $contextData['http']['header'] .= "; " . $headerString;
            } else {
                $contextData['http']['header'] = $headerString;
            }
        }

        self::$context = stream_context_create($contextData);
    }

    /**
     * @return null
     */
    public static function getContext()
    {
        return self::$context;
    }

    /**
     * @param $url
     * @param string $format
     * @param string $channel
     * @return bool|mixed|string
     */
    public static function getUpgradePath($url, $format = "php", $channel = "stable")
    {
        $packageName = "pydio-core";
        if (defined('AJXP_PACKAGE_NAME')) {
            $packageName = AJXP_PACKAGE_NAME;
        }
        if (isSet(self::$context)) {
            $json = file_get_contents($url . "?channel=" . $channel . "&version=" . AJXP_VERSION . "&package=" . $packageName, null, self::$context);
        } else {
            $json = FileHelper::getRemoteContent($url . "?channel=" . $channel . "&version=" . AJXP_VERSION . "&package=" . $packageName);
        }
        if($channel === 'test'){
            $data = json_decode($json, true);
            $package = $data['packages'][0];
            $data['packages'][] = str_replace('0.0.0', '0.0.1', $package);
            $data['packages'][] = str_replace('0.0.0', '0.0.2', $package);
            $data['packages'][] = str_replace('0.0.0', '0.0.3', $package);
            $json = json_encode($data);
        }

        if ($format == "php") {
            return json_decode($json, true);
        } else {
            return $json;
        }
    }

    /**
     * @return bool
     */
    public function hasNextStep()
    {
        if ($this->step < count($this->steps) && $this->error == NULL) {
            $stepValues = array_values($this->steps);
            $this->currentStepTitle = $stepValues[$this->step];
            return true;
        }
        return false;
    }

    public function execute()
    {
        $stepKeys = array_keys($this->steps);
        $stepName = $stepKeys[$this->step];
        try {
            $this->executeStepTrigger($stepName, "pre");
            if (method_exists($this, $stepKeys[$this->step])) {
                $this->result = call_user_func(array($this, $stepKeys[$this->step]));
            } else {
                $this->result = "Skipping step, method not found";
            }
            $this->executeStepTrigger($stepName, "post");
        } catch (Exception $e) {
            $this->error = $e->getMessage();
        }
        $this->step++;
    }

    public function testUpgradeScripts()
    {
        echo '<br>' . $this->upgradeDB();
        echo '<br>' . $this->specificTask();
    }

    /**
     * @return string
     * @throws Exception
     */
    public function checkDownloadFolder()
    {
        if (!is_dir($this->workingFolder)) {
            $t = @mkdir($this->workingFolder, 0755, true);
            if ($t === false) throw new Exception("Cannot create target folder for downloading upgrade archive!");
        }
        return "OK";
    }

    /**
     * @return string
     * @throws Exception
     */
    public function checkTargetFolder()
    {
        if (!is_writable(AJXP_INSTALL_PATH)) {
            throw new PydioException("The root install path is not writeable, no file will be overriden!
            <br>When performing upgrades, first change the ownership (using chown) of the Pydio root folder
            to your web server account (e.g. www-data or apache) and propagate that ownership change to all
            Pydio sub-folders. <br>Run the upgrade again then, post upgrade, change the ownership back
            to the previous settings for all Pydio folders, <b>except for the data/ folder</b> that must stay
            writeable by the web server.");
        }

        $this->crawlPermissions(null, AJXP_INSTALL_PATH."/*.php");
        $this->crawlPermissions(AJXP_INSTALL_PATH."/core");
        $this->crawlPermissions(AJXP_INSTALL_PATH."/plugins");

        return "Crawling folders core, plugins and files /*.php to check that all code files are writeable : OK";

        return "OK";
    }

    /**
     * @return string
     * @throws Exception
     */
    public function downloadArchive()
    {
        $this->archive = $this->workingFolder . "/" . basename($this->archiveURL);
        if ($this->debugMode && is_file($this->archive)) {
            return "Already downloaded";
        }
        if (self::$context) {
            $content = file_get_contents($this->archiveURL, null, self::$context);
        } else {
            $content = FileHelper::getRemoteContent($this->archiveURL);
        }
        if ($content === false || strlen($content) == 0) {
            throw new Exception("Error while downloading");
        }
        file_put_contents($this->archive, $content);
        return "File saved in " . $this->archive;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function extractArchive()
    {
        require_once(AJXP_BIN_FOLDER . "/lib/pclzip.lib.php");
        $archive = new PclZip($this->archive);
        $result = $archive->extract(PCLZIP_OPT_PATH, $this->workingFolder);
        if ($result <= 0) {
            throw new Exception($archive->errorInfo(true));
        } else {
            // Check that there is a new folder without zip extension
            if (is_dir($this->workingFolder . "/" . substr(basename($this->archive), 0, -4))) {
                $this->workingFolder = $this->workingFolder . "/" . substr(basename($this->archive), 0, -4);
            }
            return "Extracted folder " . $this->workingFolder;
        }

    }

    /**
     * @return string
     * @throws Exception
     */
    public function checkArchiveIntegrity()
    {
        if (!is_file($this->archive)) {
            throw new Exception("Cannot find archive file!");
        }
        $result = hash_file($this->archiveHashMethod, $this->archive);
        if ($result != $this->archiveHash) {
            throw new Exception("Warning the archive seems corrupted, you should re-download it!");
        }
        return "Hash is ok ($this->archiveHash)";
    }

    /**
     * @return string
     */
    public function backupMarkedFiles()
    {
        $targetFolder = $this->installPath;
        if (!is_array($this->markedFiles) || !count($this->markedFiles)) {
            return "Nothing to do";
        }
        foreach ($this->markedFiles as $index => $file) {
            $file = trim($file);
            if (!empty($file) && is_file($targetFolder . "/" . $file)) {
                $newName = $file . ".orig-" . date("Ymd");
                copy($targetFolder . "/" . $file, $targetFolder . "/" . $newName);
            } else {
                unset($this->markedFiles[$index]);
            }
        }
        if (!count($this->markedFiles)) {
            return "Nothing to do";
        }
        return "Backup of " . count($this->markedFiles) . " file(s) marked as preserved.";
    }

    /**
     * @return string
     */
    public function copyCodeFiles()
    {
        // CORE & PLUGINS
        $targetFolder = $this->installPath;
        self::copy_r($this->workingFolder . "/core", $targetFolder . "/core");
        self::copy_r($this->workingFolder . "/plugins", $targetFolder . "/plugins");
        $rootFiles = glob($this->workingFolder . "/*.php");
        if ($rootFiles !== false) {
            foreach ($rootFiles as $file) {
                copy($file, $targetFolder . "/" . basename($file));
            }
            return "Upgraded core, plugins and base access points.";
        } else {
            return "Upgrade core and plugins. Nothing to do at the base";
        }
    }

    /**
     * @return string
     */
    public function restoreMarkedFiles()
    {
        if (!count($this->markedFiles)) {
            return "Nothing to do";
        }
        $targetFolder = $this->installPath;
        foreach ($this->markedFiles as $file) {
            $bakupName = $file . ".orig-" . date("Ymd");
            $newName = $file . ".new-" . date("Ymd");
            if (is_file($targetFolder . "/" . $file) && is_file($targetFolder . "/" . $bakupName)) {
                copy($targetFolder . "/" . $file, $targetFolder . "/" . $newName);
                copy($targetFolder . "/" . $bakupName, $targetFolder . "/" . $file);
                unlink($targetFolder . "/" . $bakupName);
            }
        }
        return "Restoration of " . count($this->markedFiles) . " file(s) marked as preserved.";
    }


    /**
     * @return string
     */
    public function duplicateConfFiles()
    {
        $confFiles = glob($this->workingFolder . "/conf/*.php");
        if ($confFiles !== false) {
            foreach ($confFiles as $file) {
                $newFileName = $this->installPath . "/conf/" . basename($file) . ".new-" . date("Ymd");
                copy($file, $newFileName);
            }
        }
        return "Successfully copied " . count($confFiles) . " files inside config folder (not overriden, please review them)";
    }


    /**
     * @return string
     */
    public function cleanUnusedFiles()
    {
        if (!is_file($this->workingFolder . "/" . $this->cleanFile)) return "Nothing to do.";
        $deleted = array();
        foreach (file($this->workingFolder . "/" . $this->cleanFile) as $file) {
            $file = trim($file);
            if (is_file($this->installPath . "/" . $file)) {
                if (in_array($file, $this->markedFiles)) {
                    rename($this->installPath . "/" . $file, $this->installPath . "/" . $file . ".unused");
                } else {
                    unlink($this->installPath . "/" . $file);
                }
                $deleted[] = $file;
            }
        }
        return "Deleted (or backedup) following files : " . implode(", ", $deleted);

    }

    /**
     * @return string
     * @throws Exception
     */
    public function upgradeDB()
    {
        $confDriver = ConfService::getConfStorageImpl();
        if (!$confDriver instanceof \Pydio\Conf\Sql\SqlConfDriver) {
            return "";
        }

        $conf = OptionsHelper::cleanDibiDriverParameters($confDriver->getOption("SQL_DRIVER"));
        if (!is_array($conf) || !isSet($conf["driver"])) return "Nothing to do";
        switch ($conf["driver"]) {
            case "sqlite":
            case "sqlite3":
                $ext = ".sqlite";
                break;
            case "postgre":
                $ext = ".pgsql";
                break;
            case "mysql":
                $ext = (is_file($this->workingFolder . "/" . $this->dbUpgrade . ".mysql")) ? ".mysql" : ".sql";
                break;
            default:
                return "ERROR!, DB driver " . $conf["driver"] . " not supported yet in __FUNCTION__";
        }

        $file = $this->dbUpgrade . $ext;
        if (!is_file($this->workingFolder . "/" . $file)) return "Nothing to do.";
        $sqlInstructions = file_get_contents($this->workingFolder . "/" . $file);

        $parts = array_map("trim", explode("/* SEPARATOR */", $sqlInstructions));
        $results = array();
        $errors = array();

        dibi::connect($conf);
        dibi::begin();
        foreach ($parts as $sqlPart) {
            if (empty($sqlPart)) continue;
            try {
                dibi::nativeQuery($sqlPart);
                $results[] = $sqlPart;
            } catch (DibiException $e) {
                $errors[] = $sqlPart . " (" . $e->getMessage() . ")";
            }
        }
        dibi::commit();
        dibi::disconnect();

        if (!count($errors)) {
            return "Database successfully upgraded";
        } else {
            return "Database upgrade failed. <br>The following statements were executed : <br>" . implode("<br>", $results) . ",<br><br> The following statements failed : <br>" . implode("<br>", $errors) . "<br><br> You should manually upgrade your DB.";
        }


    }

    /**
     * @return string
     */
    public function specificTask()
    {
        if (!is_file($this->workingFolder . "/" . $this->additionalScript)) return "Nothing to do.";
        include($this->workingFolder . "/" . $this->additionalScript);
        return "Executed specific upgrade task.";

    }

    /**
     * @param $stepName
     * @param string $trigger
     * @return string
     */
    protected function executeStepTrigger($stepName, $trigger = "pre")
    {
        $scriptName = $this->workingFolder . "/" . $this->stepTriggerPrefix . "-" . $trigger . "-" . $stepName . ".php";
        if (!is_file($scriptName)) return "";
        ob_start();
        include($scriptName);
        $output = ob_get_flush();
        return "Executed specific task for " . $trigger . "-" . $stepName . ": " . $output;
    }

    /**
     * @return string
     */
    public function updateVersion()
    {
        if (is_file($this->workingFolder . "/conf/VERSION.php")) {
            copy($this->workingFolder . "/conf/VERSION.php", $this->installPath . "/conf/VERSION.php");
        }
        // Finally copy VERSION file
        if (!is_file($this->workingFolder . "/conf/VERSION")) {
            return "<b>No VERSION file in archive</b>";
        }
        copy($this->workingFolder . "/conf/VERSION", $this->installPath . "/conf/VERSION");
        $vCont = file_get_contents($this->installPath . "/conf/VERSION");
        list($v, $date) = explode("__", $vCont);
        return "<b>Version upgraded to " . $v . " ($date)</b>";
    }

    /**
     * @return string
     */
    public function clearCache()
    {
        ConfService::clearAllCaches();
        return "Ok";
    }

    /**
     * @return string
     */
    public function displayNote()
    {
        if (is_file($this->workingFolder . "/" . $this->releaseNote)) {
            return nl2br(file_get_contents($this->workingFolder . "/" . $this->releaseNote));
        }
        return "";
    }

    /**
     * @return string
     */
    public function displayUpgradeInstructions()
    {
        if (is_file($this->workingFolder . "/" . $this->htmlInstructions)) {
            return "<div id='upgrade_last_html'>" . file_get_contents($this->workingFolder . "/" . $this->htmlInstructions) . "
            <h1>Upgrade report</h1>
            </div>";
        }
        return "";
    }

    /**
     * @param \Pydio\Core\Model\ContextInterface $ctx
     * @param string $repositoryId
     * @param bool $dryRun
     * @throws Exception
     */
    public static function migrateMetaSerialPlugin($ctx, $repositoryId, $dryRun)
    {
        $repo = RepositoryService::getRepositoryById($repositoryId);
        if ($repo == null) throw new Exception("Cannot find repository!");
        $sources = $repo->getContextOption($ctx, "META_SOURCES");
        if (!isSet($sources["meta.serial"])) {
            //throw new Exception("This repository does not have the meta.serial plugin!");
            $sources["meta.serial"] = array(
                "meta_file_name" => ".ajxp_meta",
                "meta_fields" => "comment_field,css_label",
                "meta_labels" => "Comment,Label"
            );
        }
        if ($repo->hasParent()) {
            throw new Exception("This repository is defined by a template or is shared, you should upgrade the parent instead!");
        }
        $oldMetaFileName = $sources["meta.serial"]["meta_file_name"];

        $sources["metastore.serial"] = array("METADATA_FILE" => $oldMetaFileName, "UPGRADE_FROM_METASERIAL" => true);
        $sources["meta.user"] = array(
            "meta_fields" => $sources["meta.serial"]["meta_fields"],
            "meta_labels" => $sources["meta.serial"]["meta_labels"],
            "meta_visibility" => $sources["meta.serial"]["meta_visibility"]
        );
        unset($sources["meta.serial"]);
        $oldId = $repo->getId();
        $repo->addOption("META_SOURCES", $sources);
        $log = print_r($sources, true);
        if (!$dryRun) {
            RepositoryService::replaceRepository($oldId, $repo);
        }
        print("Will replace the META_SOURCES options with the following : <br><pre>" . ($log) . "</pre>");

    }

    /**
     * @throws DibiException
     */
    public static function executeLocalScripts(){

        $workingDir = AJXP_INSTALL_PATH . '/' . AJXP_PLUGINS_FOLDER . '/action.updater/scripts';
        $dbMismatch = ConfService::detectVersionMismatch();
        if($dbMismatch !== false){
            $conf = $dbMismatch['conf'];
            $dbVersion = $dbMismatch['current'];
            $targetDb = $dbMismatch['target'];

            switch ($conf["driver"]) {
                case "sqlite":
                case "sqlite3":
                    $ext = "sqlite";
                    break;
                case "postgre":
                    $ext = "pgsql";
                    break;
                case "mysql":
                    $ext = "mysql";
                    break;
                default:
                    throw new PydioException("ERROR!, DB driver " . $conf["driver"] . " not supported yet");
            }

            dibi::connect($conf);
            for($i = $dbVersion + 1; $i <= $targetDb; $i++ ){
                $versionUpgrade = $workingDir.'/sql/'.$i.'.'.$ext;
                $result[] = 'Applying script for DB version '.$i;
                if(file_exists($versionUpgrade)){
                    // Apply Upgrade Script
                    $sqlInstructions = file_get_contents($versionUpgrade);
                    $parts = array_map("trim", explode("/* SEPARATOR */", $sqlInstructions));
                    dibi::begin();
                    foreach ($parts as $sqlPart) {
                        if (empty($sqlPart)) continue;
                        dibi::nativeQuery($sqlPart);
                        $result[] = ' - ' . $sqlPart;
                    }
                    dibi::commit();
                }
            }
            dibi::disconnect();

        }

        $phpUpgrade = $workingDir . '/php/' . AJXP_VERSION . '.php';
        if(file_exists($phpUpgrade)){
            include_once($phpUpgrade);
            $result[] = 'Applied specific script for version '.AJXP_VERSION;
        }
        ConfService::clearAllCaches();
        return $result;
    }

    /**
     * Crawl all files permissions to make sure they are writeable.
     * @param $path
     * @param null $glob
     * @throws PydioException
     */
    public function crawlPermissions($path, $glob = null){

        @set_time_limit(1000);
        $error = false;
        if($glob !== null){
            $files = glob($glob);
            foreach($files as $file){
                if(!is_writeable($file)){
                    $error = $file;
                    break;
                }
            }
        }else{
            $directory = new \RecursiveDirectoryIterator($path, \FilesystemIterator::FOLLOW_SYMLINKS);
            $iterator = new \RecursiveIteratorIterator($directory);
            foreach ($iterator as $info) {
                if(!is_writable($info->getPathname())){
                    $error = $info->getPathname();
                    break;
                }
            }
        }
        if($error){
            throw new PydioException("Crawling folder $path to check all files are writeable : File $info FAIL! Please make sure that the whole tree is currently writeable by the webserver, or upgrade may probably fail at one point.");
        }
    }

    /**
     * @param $path
     * @param $dest
     * @return bool
     */
    public static function copy_r($path, $dest)
    {
        if (is_dir($path)) {
            @mkdir($dest);
            $objects = scandir($path);
            if (sizeof($objects) > 0) {
                foreach ($objects as $file) {
                    if ($file == "." || $file == "..")
                        continue;
                    // go on
                    if (is_dir($path . DIRECTORY_SEPARATOR . $file)) {
                        self::copy_r($path . DIRECTORY_SEPARATOR . $file, $dest . DIRECTORY_SEPARATOR . $file);
                    } else {
                        copy($path . DIRECTORY_SEPARATOR . $file, $dest . DIRECTORY_SEPARATOR . $file);
                    }
                }
            }
            return true;
        } elseif (is_file($path)) {
            return copy($path, $dest);
        } else {
            return false;
        }
    }

}
