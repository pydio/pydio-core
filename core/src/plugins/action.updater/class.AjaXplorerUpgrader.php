<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <http://pyd.io/>.
 */

defined('AJXP_EXEC') or die( 'Access not allowed');


/**
 * @package AjaXplorer_Plugins
 * @subpackage Action
 */
class AjaXplorerUpgrader
{
    private $archiveURL;
    private $archiveHash;
    private $archiveHashMethod;
    private $markedFiles;

    private $debugMode = FALSE;
    private $cleanFile = "UPGRADE/CLEAN-FILES";
    private $additionalScript = "UPGRADE/PHP-SCRIPT";
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

    public function __construct($archiveURL, $hash, $method, $backupFiles = array())
    {
        $this->archiveURL = $archiveURL;
        $this->archiveHash = $hash;
        $this->archiveHashMethod = $method;
        $this->markedFiles = $backupFiles;

        $this->installPath = AJXP_INSTALL_PATH;
        if ($this->debugMode) {
            @mkdir(AJXP_INSTALL_PATH."/upgrade_test");
            $this->installPath = AJXP_INSTALL_PATH."/upgrade_test";
        }

        $this->workingFolder = AJXP_DATA_PATH."/tmp/update";
        $this->steps = array(
            "checkDownloadFolder"       => "Checking download permissions",
            "downloadArchive"           => "Downloading upgrade archive",
            "checkArchiveIntegrity"     => "Checking archive integrity",
            "checkTargetFolder"         => "Checking folders permissions",
            "extractArchive"            => "Extracting Archive",
            "backupMarkedFiles"         => "Backuping your modified files",
            "copyCodeFiles"             => "Copying core source files",
            "restoreMarkedFiles"        => "Restoring your modified files",
            "duplicateConfFiles"        => "Copying configuration files",
            "cleanUnusedFiles"          => "Deleting unused files",
            "upgradeDB"                 => "Upgrading database",
            "specificTask"              => "Running specific upgrade task",
            "updateVersion"             => "Everything went ok, upgrading version!",
            "clearCache"                => "Clearing plugins cache",
            "displayNote"               => "Release note : ",
            "displayUpgradeInstructions"=> "Upgrade instructions",
        );

    }

    public static function configureProxy($proxyHost, $proxyUser, $proxyPass)
    {
        $proxy = array( 'http' => array( 'proxy' => 'tcp://'.$proxyHost, 'request_fulluri' => true ) );
        if (!empty($proxyUser) && !empty($proxyPass)) {
            $auth = base64_encode($proxyUser.":".$proxyPass);
            $proxy['http']['header'] = "Proxy-Authorization: Basic $auth";
        }
        self::$context = stream_context_create($proxy);
    }

    public static function getUpgradePath($url, $format = "php", $channel="stable")
    {
        if (isSet(self::$context)) {
            $json = file_get_contents($url."?channel=".$channel."&version=".AJXP_VERSION."&package=pydio-core", null, self::$context);
        } else {
            $json = AJXP_Utils::getRemoteContent($url."?channel=".$channel."&version=".AJXP_VERSION."&package=pydio-core");
        }
        if($format == "php") return json_decode($json, true);
        else return $json;
    }

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
        try {
            if (method_exists($this, $stepKeys[$this->step])) {
                $this->result = call_user_func(array($this, $stepKeys[$this->step]));
            } else {
                $this->result = "Skipping step, method not found";
            }
        } catch (Exception $e) {
            $this->error = $e->getMessage();
        }
        $this->step ++;
    }

    public function checkDownloadFolder()
    {
        if (!is_dir($this->workingFolder)) {
            $t = @mkdir($this->workingFolder, 0755, true);
            if($t === false) throw new Exception("Cannot create target folder for downloading upgrade archive!");
        }
        return "OK";
    }

    public function checkTargetFolder()
    {
        if (!is_writable(AJXP_INSTALL_PATH)) {
            throw new Exception("The root install path is not writeable, no file will be copied!
            The archive is available on your server, you can copy its manually to override the current installation.");
        }
        return "OK";
    }

    public function downloadArchive()
    {
        $this->archive = $this->workingFolder."/".basename($this->archiveURL);
        if ($this->debugMode && is_file($this->archive)) {
            return "Already downloaded";
        }
        $content = AJXP_Utils::getRemoteContent($this->archiveURL);
        if ($content === false || strlen($content) == 0) {
            throw new Exception("Error while downloading");
        }
        file_put_contents($this->archive, $content);
        return "File saved in ".$this->archive;
    }

    public function extractArchive()
    {
        require_once(AJXP_BIN_FOLDER . "/pclzip.lib.php");
        $archive = new PclZip($this->archive);
        $result = $archive->extract(PCLZIP_OPT_PATH, $this->workingFolder);
        if ($result <= 0) {
            throw new Exception($archive->errorInfo(true));
        } else {
            // Check that there is a new folder without zip extension
            if (is_dir($this->workingFolder."/".substr(basename($this->archive), 0, -4)) ) {
                $this->workingFolder = $this->workingFolder."/".substr(basename($this->archive), 0, -4);
            }
            return "Extracted folder ".$this->workingFolder;
        }

    }

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

    public function backupMarkedFiles()
    {
        $targetFolder = $this->installPath;
        if (!is_array($this->markedFiles) || !count($this->markedFiles)) {
            return "Nothing to do";
        }
        foreach ($this->markedFiles as $index => $file) {
            $file = trim($file);
            if (!empty($file) && is_file($targetFolder."/".$file)) {
                $newName = $file.".orig-".date("Ymd");
                copy($targetFolder."/".$file, $targetFolder."/".$newName);
            } else {
                unset($this->markedFiles[$index]);
            }
        }
        if (!count($this->markedFiles)) {
            return "Nothing to do";
        }
        return "Backup of ".count($this->markedFiles)." file(s) marked as preserved.";
    }

    public function copyCodeFiles()
    {
        // CORE & PLUGINS
        $targetFolder = $this->installPath;
        self::copy_r($this->workingFolder."/core", $targetFolder."/core");
        self::copy_r($this->workingFolder."/plugins", $targetFolder."/plugins");
        $rootFiles = glob($this->workingFolder."/*.php");
        if ($rootFiles !== false) {
            foreach ($rootFiles as $file) {
                copy($file, $targetFolder."/".basename($file));
            }
            return "Upgraded core, plugins and base access points.";
        } else {
            return "Upgrade core and plugins. Nothing to do at the base";
        }
    }

    public function restoreMarkedFiles()
    {
        if (!count($this->markedFiles)) {
            return "Nothing to do";
        }
        $targetFolder = $this->installPath;
        foreach ($this->markedFiles as $file) {
            $bakupName = $file.".orig-".date("Ymd");
            $newName = $file.".new-".date("Ymd");
            if (is_file($targetFolder."/".$file) && is_file($targetFolder."/".$bakupName)) {
                copy($targetFolder."/".$file, $targetFolder."/".$newName);
                copy($targetFolder."/".$bakupName, $targetFolder."/".$file);
                unlink($targetFolder."/".$bakupName);
            }
        }
        return "Restoration of ".count($this->markedFiles)." file(s) marked as preserved.";
    }


    public function duplicateConfFiles()
    {
        $confFiles = glob($this->workingFolder."/conf/*.php");
        if ($confFiles !== false) {
            foreach ($confFiles as $file) {
                $newFileName = $this->installPath."/conf/".basename($file).".new-".date("Ymd");
                copy($file, $newFileName);
            }
        }
        return "Successfully copied ".count($confFiles)." files inside config folder (not overriden, please review them)";
    }


    public function cleanUnusedFiles()
    {
        if(!is_file($this->workingFolder."/".$this->cleanFile)) return "Nothing to do.";
        $deleted = array();
        foreach (file($this->workingFolder."/".$this->cleanFile) as $file) {
            $file = trim($file);
            if (is_file($this->installPath."/".$file)) {
                if (in_array($file, $this->markedFiles)) {
                    rename($this->installPath."/".$file, $this->installPath."/".$file.".unused");
                } else {
                    unlink($this->installPath."/".$file);
                }
                $deleted[] = $file;
            }
        }
        return "Deleted (or backedup) following files : ".implode(", ",$deleted);

    }

    public function upgradeDB()
    {
        $confDriver = ConfService::getConfStorageImpl();
        $authDriver = ConfService::getAuthDriverImpl();
        $logger = AJXP_Logger::getInstance();
        if (is_a($confDriver, "sqlConfDriver")) {
            $conf = AJXP_Utils::cleanDibiDriverParameters($confDriver->getOption("SQL_DRIVER"));
            if(!is_array($conf) || !isSet($conf["driver"])) return "Nothing to do";
            switch ($conf["driver"]) {
                case "sqlite":
                case "sqlite3":
                    $ext = ".sqlite";
                    break;
                case "postgre":
                    $ext = ".pgsql";
                    break;
                case "mysql":
                    $ext = (is_file($this->workingFolder."/".$this->dbUpgrade.".mysql")) ? ".mysql" : ".sql";
                    break;
                default:
                    return "ERROR!, DB driver "+ $conf["driver"] +" not supported yet in __FUNCTION__";
            }

            $file = $this->dbUpgrade.$ext;
            if(!is_file($this->workingFolder."/".$file)) return "Nothing to do.";
            $sqlInstructions = file_get_contents($this->workingFolder."/".$file);

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
                    $results[] = $sqlPart;
                } catch (DibiException $e) {
                    $errors[] = $sqlPart. " (". $e->getMessage().")";
                }
            }
            dibi::commit();
            dibi::disconnect();

            if (!count($errors)) {
                return "Database successfully upgraded";
            } else {
                return "Database upgrade failed. <br>The following statements were executed : <br>".implode("<br>", $results).",<br><br> The following statements failed : <br>".implode("<br>", $errors)."<br><br> You should manually upgrade your DB.";
            }

        }

    }

    public function specificTask()
    {
        if(!is_file($this->workingFolder."/".$this->additionalScript)) return "Nothing to do.";
        include($this->workingFolder."/".$this->additionalScript);
        return "Executed specific upgrade task.";

    }

    public function updateVersion()
    {
        // Finally copy VERSION file
        if (!is_file($this->workingFolder."/conf/VERSION")) {
            return "<b>No VERSION file in archive</b>";
        }
        copy($this->workingFolder."/conf/VERSION", $this->installPath."/conf/VERSION");
        $vCont = file_get_contents($this->installPath."/conf/VERSION");
        list($v, $date) = explode("__", $vCont);
        return "<b>Version upgraded to ".$v." ($date)</b>";
    }

    public function clearCache()
    {
        @unlink(AJXP_PLUGINS_CACHE_FILE);
        @unlink(AJXP_PLUGINS_REQUIRES_FILE);
        @unlink(AJXP_PLUGINS_MESSAGES_FILE);
        $i18nFiles = glob(dirname(AJXP_PLUGINS_MESSAGES_FILE)."/i18n/*.ser");
        if (is_array($i18nFiles)) {
            foreach ($i18nFiles as $file) {
                @unlink($file);
            }
        }
        return "Ok";
    }

    public function displayNote()
    {
        if (is_file($this->workingFolder."/".$this->releaseNote)) {
            return nl2br(file_get_contents($this->workingFolder."/".$this->releaseNote));
        }
    }

    public function displayUpgradeInstructions()
    {
        if (is_file($this->workingFolder."/".$this->htmlInstructions)) {
            return "<div id='upgrade_last_html'>".file_get_contents($this->workingFolder."/".$this->htmlInstructions)."
            <h1>Upgrade report</h1>
            </div>";
        }
    }


    public static function upgradeFrom324($oldLocation, $dryRun = true)
    {
        $mess = ConfService::getMessages();
        $logFile = AJXP_CACHE_DIR."/import_from_324.log";
        if ($dryRun) {
            print("<b>".$mess["updater.10"]."</b><br><br>");
        }

        $itemsToCopy = array(
            array(
                "mask"      => "public/*.php",
                "target"    => "data/public"
            ),
            array(
                "mask"      => "public/.ajxp_publiclet_counters.ser",
                "target"    => "data/public"
            ),
            array(
                "mask"      => "server/logs/*.txt",
                "target"    => "data/logs"
            ),
            array(
                "mask"      => "server/conf/repo.ser",
                "target"    => "data/plugins/conf.serial"
            ),
            array(
                "mask"      => "server/conf/aliases.ser",
                "target"    => "data/plugins/conf.serial"
            ),
            array(
                "mask"      => "server/users/*",
                "target"    => "data/plugins/auth.serial"
            )
        );

        $configToPluginsConf = array(
            array(
                "type"      => "constant",
                "name"      => "ENABLE_USERS",
                "target"    => "core.auth/ENABLE_USERS"
            ),
            array(
                "type"      => "constant",
                "name"      => "ALLOW_GUEST_BROWSING",
                "target"    => "core.auth/ALLOW_GUEST_BROWSING"
            ),
            array(
                "type"      => "constant",
                "name"      => "AJXP_PASSWORD_MINLENGTH",
                "target"    => "core.auth/PASSWORD_MINLENGTH"
            ),
            array(
                "type"      => "variable",
                "name"      => "AJXP_SESSION_SET_CREDENTIALS",
                "target"    => "core.auth/SESSION_SET_CREDENTIALS"
            ),
            array(
                "type"      => "constant",
                "name"      => "PUBLIC_DOWNLOAD_FOLDER",
                "target"    => "core.ajaxplorer/PUBLIC_DOWNLOAD_FOLDER"
            ),
            array(
                "type"      => "constant",
                "name"      => "PUBLIC_DOWNLOAD_URL",
                "target"    => "core.ajaxplorer/PUBLIC_DOWNLOAD_URL"
            ),
            array(
                "type"      => "variable",
                "name"      => "default_language",
                "target"    => "core.ajaxplorer/DEFAULT_LANGUAGE"
            ),
            array(
                "type"      => "constant",
                "name"      => "GZIP_DOWNLOAD",
                "target"    => "core.ajaxplorer/GZIP_COMPRESSION"
            ),
            array(
                "type"      => "constant",
                "name"      => "GZIP_LIMIT",
                "target"    => "core.ajaxplorer/GZIP_LIMIT"
            ),
            array(
                "type"      => "constant",
                "name"      => "DISABLE_ZIP_CREATION",
                "target"    => "core.ajaxplorer/ZIP_CREATION",
                "modifier"  => "NOT"
            ),
            array(
                "type"      => "constant",
                "name"      => "AJXP_WEBDAV_ENABLE",
                "target"    => "core.ajaxplorer/WEBDAV_ENABLE"
            ),
            array(
                "type"      => "constant",
                "name"      => "AJXP_WEBDAV_BASEURI",
                "target"    => "core.ajaxplorer/WEBDAV_BASEURI"
            ),
            array(
                "type"      => "constant",
                "name"      => "AJXP_WEBDAV_BASEHOST",
                "target"    => "core.ajaxplorer/WEBDAV_BASEHOST"
            ),
            array(
                "type"      => "constant",
                "name"      => "AJXP_WEBDAV_DIGESTREALM",
                "target"    => "core.ajaxplorer/WEBDAV_DIGESTREALM"
            ),
            array(
                "type"      => "variable",
                "name"      => "webmaster_email",
                "target"    => "core.ajaxplorer/WEBMASTER_EMAIL"
            ),
            array(
                "type"      => "variable",
                "name"      => "max_caracteres",
                "target"    => "core.ajaxplorer/NODENAME_MAX_LENGTH"
            ),
            array(
                "type"      => "variable",
                "name"      => "customTitle",
                "target"    => "core.ajaxplorer/APPLICATION_TITLE"
            ),
            array(
                "type"      => "constant",
                "name"      => "HTTPS_POLICY_FILE",
                "target"    => "uploader.flex/HTTPS_POLICY_FILE"
            ),
            array(
                "type"      => "variable",
                "name"      => "upload_max_number",
                "target"    => "core.uploader/UPLOAD_MAX_NUMBER"
            ),
            array(
                "type"      => "variable",
                "name"      => "upload_max_size_per_file",
                "target"    => "core.uploader/UPLOAD_MAX_SIZE"
            ),
            array(
                "type"      => "variable",
                "name"      => "upload_max_size_total",
                "target"    => "core.uploader/UPLOAD_MAX_SIZE_TOTAL"
            ),
            array(
                "type"      => "constant",
                "name"      => "AJXP_CLIENT_TIMEOUT_TIME",
                "target"    => "gui.ajax/CLIENT_TIMEOUT_TIME"
            ),
            array(
                "type"      => "constant",
                "name"      => "AJXP_CLIENT_TIMEOUT_WARN_BEFORE",
                "target"    => "gui.ajax/CLIENT_TIMEOUT_WARN"
            ),
            array(
                "type"      => "constant",
                "name"      => "GOOGLE_ANALYTICS_ID",
                "target"    => "gui.ajax/GOOGLE_ANALYTICS_ID"
            ),
            array(
                "type"      => "constant",
                "name"      => "GOOGLE_ANALYTICS_DOMAIN",
                "target"    => "gui.ajax/GOOGLE_ANALYTICS_DOMAIN"
            ),
            array(
                "type"      => "constant",
                "name"      => "GOOGLE_ANALYTICS_EVENT",
                "target"    => "gui.ajax/GOOGLE_ANALYTICS_EVENT"
            ),
            array(
                "type"      => "variable",
                "name"      => "customTitleFontSize",
                "target"    => "gui.ajax/CUSTOM_FONT_SIZE"
            ),
            array(
                "type"      => "variable",
                "name"      => "customIcon",
                "target"    => "gui.ajax/CUSTOM_ICON"
            ),
            array(
                "type"      => "variable",
                "name"      => "customIconWidth",
                "target"    => "gui.ajax/CUSTOM_ICON_WIDTH"
            ),
            array(
                "type"      => "variable",
                "name"      => "welcomeCustomMessage",
                "target"    => "gui.ajax/CUSTOM_WELCOME_MESSAGE"
            ),
        );

        if (!$dryRun) {
            $logFileHandle = fopen($logFile, "w");
        }

        foreach ($itemsToCopy as $item) {
            $files = glob($oldLocation."/".$item["mask"]);
            if($files === false) continue;
            foreach ($files as $fileOrFolder) {
                $target = AJXP_INSTALL_PATH."/".$item["target"];
                if (is_file($fileOrFolder)) {
                    $l = "Copy $fileOrFolder to ".$target."/".basename($fileOrFolder)."\n";
                    if ($dryRun) {
                        print(nl2br($l));
                    } else {
                        copy($fileOrFolder, $target."/".basename($fileOrFolder));
                        fwrite($logFileHandle, $l);
                    }

                } else {
                    $l= "Copy recursively ".$fileOrFolder." to ".$target."/".basename($fileOrFolder)."\n";
                    if ($dryRun) {
                        print(nl2br($l));
                    } else {
                        self::copy_r($fileOrFolder, $target."/".basename($fileOrFolder));
                        fwrite($logFileHandle, $l);
                    }
                }
            }
        }

        // FILTER THE CONF FILE TO REMOVE ALL CONSTANTS
        $originalConfdir = $oldLocation."/server/conf";
        $lines = file($originalConfdir."/conf.php");
        $filteredLines = array();
        $mutedConstants = array();
        foreach ($lines as $line) {
            if (preg_match('/define\("(.*)", (.*)\);/', $line, $matches)) {
                //var_dump($matches);
                $value = trim($matches[2]);
                if (!empty($value)) {
                    if ($value[0] == "\"") {
                        $strValue = substr($value, 1, strlen($value)-2);
                        if (!empty($strValue)) {
                            $mutedConstants[$matches[1]] = $strValue;
                        }
                    } else if ($value == "true") {
                        $mutedConstants[$matches[1]] = true;
                    } else if ($value == "false") {
                        $mutedConstants[$matches[1]] = false;
                    } else if (is_numeric($value)) {
                        $mutedConstants[$matches[1]] = intval($value);
                    } else {
                        eval("\$res = $value;");
                        $mutedConstants[$matches[1]] = $res;
                    }
                }
                $filteredLines[] = "//".$line;
            } else {
                $filteredLines[] = $line;
            }
        }
        if (!$dryRun) {
            fwrite($logFileHandle, "Writing alternate version of conf.php without constants.");
        }
        file_put_contents($originalConfdir."/muted_conf.php", implode("", $filteredLines));

        // NOW IMPORT THE MODIFIED CONF FILE AND GATHER ALL DATA
        include $originalConfdir."/muted_conf.php";
        $allOptions = array();
        foreach ($configToPluginsConf as $localConfig) {
            $localConfigName = $localConfig["name"];
            if ($localConfig["type"] == "constant" && isset($mutedConstants[$localConfigName])) {
                $localConfig["value"] = $mutedConstants[$localConfigName];
            } else if ($localConfig["type"] == "variable" && isSet( $$localConfigName )) {
                $localConfig["value"] = $$localConfigName;
            }
            if(!isSet($localConfig["value"]) || empty($localConfig["value"])) continue;
            $l = "Should set ".$localConfig["target"]." to value ".$localConfig["value"]."\n";
            if ($dryRun) {
                $value = AJXP_Utils::xmlEntities($localConfig["value"]);
                list($pluginId, $pluginOptionName) = explode("/", $localConfig["target"]);
                $plug = AJXP_PluginsService::getInstance()->getPluginById($pluginId);
                $options = $plug->getConfigs();
                $options[$pluginOptionName] = $value;
                print(nl2br($l));
            } else {
                list($pluginId, $pluginOptionName) = explode("/", $localConfig["target"]);
                $confStorage = ConfService::getConfStorageImpl();
                $value = AJXP_Utils::xmlEntities($localConfig["value"]);
                if (!isSet($allOptions[$pluginId])) {
                    $plug = AJXP_PluginsService::getInstance()->getPluginById($pluginId);
                    $allOptions[$pluginId] = $plug->getConfigs();
                } else {
                    $allOptions[$pluginId][$pluginOptionName] = $value;
                }
                fwrite($logFileHandle, $l);
            }
        }
        if (!$dryRun && count($allOptions)) {
            foreach ($allOptions as $pId => $pOptions) {
                $confStorage->savePluginConfig($pId, $pOptions);
            }
            @unlink(AJXP_PLUGINS_CACHE_FILE);
            @unlink(AJXP_PLUGINS_REQUIRES_FILE);
            @unlink(AJXP_PLUGINS_MESSAGES_FILE);
        }

        foreach ($REPOSITORIES as $localRepoKey => $localRepoDef) {
            $localRepoString = '$REPOSITORIES['.(is_numeric($localRepoKey)?$localRepoKey:'"'.$localRepoKey.'"').'] = '.str_replace(array("'", "\\\\"), array("\"","\\"), var_export($localRepoDef, true)).';';
            $l = "Will print this to bootstrap_repositories : \n". $localRepoString;
            if ($dryRun) {
                print(nl2br($l));
            } else {
                file_put_contents($originalConfdir."/bootstrap_repositories.php", $localRepoString);
                fwrite($logFileHandle, $l);
            }
        }

        if (!$dryRun) {
            fclose($logFileHandle);
            print("<b>The operation is finished, all actions are logged in $logFile. Nothing was touch on your previous installation, please note that the repositories are not moved.<br>You should now logout, clear your browser cache, and refresh this page. Then you will log in with your previous users ids.</b>");
        }

    }

    public static function migrateMetaSerialPlugin($repositoryId, $dryRun)
    {
        $repo = ConfService::getRepositoryById($repositoryId);
        if($repo == null) throw new Exception("Cannot find repository!");
        $sources = $repo->getOption("META_SOURCES");
        if (!isSet($sources["meta.serial"])) {
            //throw new Exception("This repository does not have the meta.serial plugin!");
            $sources["meta.serial"] = array(
                "meta_file_name" => ".ajxp_meta",
                "meta_fields"   => "comment_field,css_label",
                "meta_labels"   => "Comment,Label"
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
            ConfService::replaceRepository($oldId, $repo);
        }
        print("Will replace the META_SOURCES options with the following : <br><pre>".($log)."</pre>");

    }

    public static function copy_r( $path, $dest )
    {
        if ( is_dir($path) ) {
            @mkdir( $dest );
            $objects = scandir($path);
            if ( sizeof($objects) > 0 ) {
                foreach ($objects as $file) {
                    if( $file == "." || $file == ".." )
                        continue;
                    // go on
                    if ( is_dir( $path.DIRECTORY_SEPARATOR.$file ) ) {
                        self::copy_r( $path.DIRECTORY_SEPARATOR.$file, $dest.DIRECTORY_SEPARATOR.$file );
                    } else {
                        copy( $path.DIRECTORY_SEPARATOR.$file, $dest.DIRECTORY_SEPARATOR.$file );
                    }
                }
            }
            return true;
        } elseif ( is_file($path) ) {
            return copy($path, $dest);
        } else {
            return false;
        }
    }

}
