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
 * Implementation of the configuration driver on serial files
 * @package AjaXplorer_Plugins
 * @subpackage Boot
 */
class BootConfLoader extends AbstractConfDriver
{
    private static $internalConf;
    private static $jsonConf;
    private static $jsonPath;

    private function getInternalConf()
    {
        if (!isSet(BootConfLoader::$internalConf)) {
            if (file_exists(AJXP_CONF_PATH."/bootstrap_plugins.php")) {
                include(AJXP_CONF_PATH."/bootstrap_plugins.php");
                if (isSet($PLUGINS)) {
                    BootConfLoader::$internalConf = $PLUGINS;
                }
            } else {
                BootConfLoader::$internalConf = array();
            }
        }
        return BootConfLoader::$internalConf;
    }

    public function init($options)
    {
        parent::init($options);
        try {
            $this->getPluginWorkDir(true);
        } catch (Exception $e) {
            die("Impossible write into the AJXP_DATA_PATH folder: Make sure to grant write access to this folder for your webserver!");
        }
    }

    public function loadManifest()
    {
        parent::loadManifest();
        if (!AJXP_Utils::detectApplicationFirstRun()) {
            $actions = $this->xPath->query("server_settings|registry_contributions");
            foreach ($actions as $ac) {
                $ac->parentNode->removeChild($ac);
            }
            $this->reloadXPath();
        }
    }

    /**
     * Transmit to the ajxp_conf load_plugin_manifest action
     * @param $action
     * @param $httpVars
     * @param $fileVars
     */
    public function loadInstallerForm($action, $httpVars, $fileVars)
    {
        if(isSet($httpVars["lang"])){
            ConfService::setLanguage($httpVars["lang"]);
        }
        AJXP_XMLWriter::header("admin_data");
        $fullManifest = $this->getManifestRawContent("", "xml");
        $xPath = new DOMXPath($fullManifest->ownerDocument);
        $addParams = "";
        $pInstNodes = $xPath->query("server_settings/global_param[contains(@type, 'plugin_instance:')]");
        foreach ($pInstNodes as $pInstNode) {
            $type = $pInstNode->getAttribute("type");
            $instType = str_replace("plugin_instance:", "", $type);
            $fieldName = $pInstNode->getAttribute("name");
            $pInstNode->setAttribute("type", "group_switch:".$fieldName);
            $typePlugs = AJXP_PluginsService::getInstance()->getPluginsByType($instType);
            foreach ($typePlugs as $typePlug) {
                if($typePlug->getId() == "auth.multi") continue;
                $checkErrorMessage = "";
                try {
                    $typePlug->performChecks();
                } catch (Exception $e) {
                    $checkErrorMessage = " (Warning : ".$e->getMessage().")";
                }
                $tParams = AJXP_XMLWriter::replaceAjxpXmlKeywords($typePlug->getManifestRawContent("server_settings/param"));
                $addParams .= '<global_param group_switch_name="'.$fieldName.'" name="instance_name" group_switch_label="'.$typePlug->getManifestLabel().$checkErrorMessage.'" group_switch_value="'.$typePlug->getId().'" default="'.$typePlug->getId().'" type="hidden"/>';
                $addParams .= str_replace("<param", "<global_param group_switch_name=\"${fieldName}\" group_switch_label=\"".$typePlug->getManifestLabel().$checkErrorMessage."\" group_switch_value=\"".$typePlug->getId()."\" ", $tParams);
                $addParams .= AJXP_XMLWriter::replaceAjxpXmlKeywords($typePlug->getManifestRawContent("server_settings/global_param"));
            }
        }
        $uri = $_SERVER["REQUEST_URI"];
        if(strpos($uri, '.php') !== false) $uri = AJXP_Utils::safeDirname($uri);
        if(empty($uri)) $uri = "/";
        $loadedValues = array(
            "ENCODING"  => (defined('AJXP_LOCALE')?AJXP_LOCALE:SystemTextEncoding::getEncoding()),
            "SERVER_URI"=> $uri
        );
        foreach($loadedValues as $pName => $pValue){
            $vNodes = $xPath->query("server_settings/global_param[@name='$pName']");
            if(!$vNodes->length) continue;
            $vNodes->item(0)->setAttribute("default", $pValue);
        }
        $allParams = AJXP_XMLWriter::replaceAjxpXmlKeywords($fullManifest->ownerDocument->saveXML($fullManifest));
        $allParams = str_replace('type="plugin_instance:', 'type="group_switch:', $allParams);
        $allParams = str_replace("</server_settings>", $addParams."</server_settings>", $allParams);

        echo($allParams);
        AJXP_XMLWriter::close("admin_data");

    }

    /**
     * Transmit to the ajxp_conf load_plugin_manifest action
     * @param $action
     * @param $httpVars
     * @param $fileVars
     */
    public function applyInstallerForm($action, $httpVars, $fileVars)
    {
        $data = array();
        AJXP_Utils::parseStandardFormParameters($httpVars, $data, null, "");

        // Create a custom bootstrap.json file
        $coreConf = array(); $coreAuth = array();
        $this->_loadPluginConfig("core.conf", $coreConf);
        $this->_loadPluginConfig("core.auth", $coreAuth);
        if(!isSet($coreConf["UNIQUE_INSTANCE_CONFIG"])) $coreConf["UNIQUE_INSTANCE_CONFIG"] = array();
        if(!isSet($coreAuth["MASTER_INSTANCE_CONFIG"])) $coreAuth["MASTER_INSTANCE_CONFIG"] = array();
        $coreConf["AJXP_CLI_SECRET_KEY"] = AJXP_Utils::generateRandomString(24, true);

        $storageType = $data["STORAGE_TYPE"]["type"];
        if ($storageType == "db") {
            // REWRITE BOOTSTRAP.JSON
            $coreConf["DIBI_PRECONFIGURATION"] = $data["STORAGE_TYPE"]["db_type"];
            if (isSet($coreConf["DIBI_PRECONFIGURATION"]["sqlite3_driver"])) {
                $dbFile = AJXP_VarsFilter::filter($coreConf["DIBI_PRECONFIGURATION"]["sqlite3_database"]);
                if (!file_exists(dirname($dbFile))) {
                    mkdir(dirname($dbFile), 0755, true);
                }
            }
            $coreConf["UNIQUE_INSTANCE_CONFIG"] = array_merge($coreConf["UNIQUE_INSTANCE_CONFIG"], array(
                "instance_name"=> "conf.sql",
                "group_switch_value"=> "conf.sql",
                "SQL_DRIVER"   => array("core_driver" => "core", "group_switch_value" => "core")
            ));
            $coreAuth["MASTER_INSTANCE_CONFIG"] = array_merge($coreAuth["MASTER_INSTANCE_CONFIG"], array(
                "instance_name"=> "auth.sql",
                "group_switch_value"=> "auth.sql",
                "SQL_DRIVER"   => array("core_driver" => "core", "group_switch_value" => "core")
            ));

            // INSTALL ALL SQL TABLES
            $sqlPlugs = array("conf.sql", "auth.sql", "feed.sql", "log.sql", "meta.syncable");
            foreach ($sqlPlugs as $plugId) {
                $plug = AJXP_PluginsService::findPluginById($plugId);
                $plug->installSQLTables(array("SQL_DRIVER" => $data["STORAGE_TYPE"]["db_type"]));
            }

        } else {

            $coreConf["UNIQUE_INSTANCE_CONFIG"] = array_merge($coreConf["UNIQUE_INSTANCE_CONFIG"], array(
                "instance_name"=> "conf.serial",
                "group_switch_value"=> "conf.serial"
            ));
            $coreAuth["MASTER_INSTANCE_CONFIG"] = array_merge($coreAuth["MASTER_INSTANCE_CONFIG"], array(
                "instance_name"=> "auth.serial",
                "group_switch_value"=> "auth.serial"
            ));

        }

        $oldBoot = $this->getPluginWorkDir(true)."/bootstrap.json";
        if (is_file($oldBoot)) {
            copy($oldBoot, $oldBoot.".bak");
            unlink($oldBoot);
        }
        $newBootstrap = array("core.conf" => $coreConf, "core.auth" => $coreAuth);
        AJXP_Utils::saveSerialFile($oldBoot, $newBootstrap, true, false, "json", true);


        // Write new bootstrap and reload conf plugin!
        if ($storageType == "db") {
            $coreConf["UNIQUE_INSTANCE_CONFIG"]["SQL_DRIVER"] = $coreConf["DIBI_PRECONFIGURATION"];
            $coreAuth["MASTER_INSTANCE_CONFIG"]["SQL_DRIVER"] = $coreConf["DIBI_PRECONFIGURATION"];
        }
        $newConfigPlugin = ConfService::instanciatePluginFromGlobalParams($coreConf["UNIQUE_INSTANCE_CONFIG"], "AbstractConfDriver");
        $newAuthPlugin = ConfService::instanciatePluginFromGlobalParams($coreAuth["MASTER_INSTANCE_CONFIG"], "AbstractAuthDriver");

        if($data["ENCODING"] != (defined('AJXP_LOCALE')?AJXP_LOCALE:SystemTextEncoding::getEncoding())){
            file_put_contents($this->getPluginWorkDir()."/encoding.php", "<?php \$ROOT_ENCODING='".$data["ENCODING"]."';");
        }

        $tpl = file_get_contents($this->getBaseDir()."/htaccess.tpl");
        if(!empty($data["SERVER_URI"]) && $data["SERVER_URI"] != "/"){
            $htContent = str_replace('${APPLICATION_ROOT}', $data["SERVER_URI"], $tpl);
        }else{
            $htContent = str_replace('${APPLICATION_ROOT}/', "/", $tpl);
            $htContent = str_replace('${APPLICATION_ROOT}', "/", $htContent);
        }
        if(is_writeable(AJXP_INSTALL_PATH."/.htaccess")){
            file_put_contents(AJXP_INSTALL_PATH."/.htaccess", $htContent);
        }else{
            $htAccessToUpdate = AJXP_INSTALL_PATH."/.htaccess";
        }

        if ($storageType == "db") {
            $sqlPlugs = array(
                "core.notifications/UNIQUE_FEED_INSTANCE" => "feed.sql",
                "core.log/UNIQUE_PLUGIN_INSTANCE" => "log.sql",
                "core.mq/UNIQUE_MS_INSTANCE" => "mq.sql"
            );
            $data["ENABLE_NOTIF"] = $data["STORAGE_TYPE"]["notifications"];
        }


        // Prepare plugins configs
        $direct = array(
            "APPLICATION_TITLE" => "core.ajaxplorer/APPLICATION_TITLE",
            "APPLICATION_LANGUAGE" => "core.ajaxplorer/DEFAULT_LANGUAGE",
            "ENABLE_NOTIF"      => "core.notifications/USER_EVENTS",
            "APPLICATION_WELCOME" => "gui.ajax/CUSTOM_WELCOME_MESSAGE"
        );
        $mailerEnabled = $data["MAILER_ENABLE"]["status"];
        if ($mailerEnabled == "yes") {
            // Enable core.mailer
            $data["MAILER_SYSTEM"] = $data["MAILER_ENABLE"]["MAILER_SYSTEM"];
            $data["MAILER_ADMIN"] = $data["MAILER_ENABLE"]["MAILER_ADMIN"];
            $direct = array_merge($direct, array(
                "MAILER_SYSTEM" => "mailer.phpmailer-lite/MAILER",
                "MAILER_ADMIN" => "core.mailer/FROM",
            ));
        }

        foreach ($direct as $key => $value) {
            list($pluginId, $param) = explode("/", $value);
            $options = array();
            $newConfigPlugin->_loadPluginConfig($pluginId, $options);
            $options[$param] = $data[$key];
            $newConfigPlugin->_savePluginConfig($pluginId, $options);
        }

        if (isSet($sqlPlugs)) {
            foreach ($sqlPlugs as $core => $value) {
                list($pluginId, $param) = explode("/",$core);
                $options = array();
                $newConfigPlugin->_loadPluginConfig($pluginId, $options);
                $options[$param] = array(
                    "instance_name"=> $value,
                    "group_switch_value"=> $value,
                    "SQL_DRIVER"   => array("core_driver" => "core", "group_switch_value" => "core")
                );
                $newConfigPlugin->_savePluginConfig($pluginId, $options);
            }
        }


        ConfService::setTmpStorageImplementations($newConfigPlugin, $newAuthPlugin);
        require_once($newConfigPlugin->getUserClassFileName());

        $adminLogin = AJXP_Utils::sanitize($data["ADMIN_USER_LOGIN"], AJXP_SANITIZE_EMAILCHARS);
        $adminName = $data["ADMIN_USER_NAME"];
        $adminPass = $data["ADMIN_USER_PASS"];
        $adminPass2 = $data["ADMIN_USER_PASS2"];
        AuthService::createUser($adminLogin, $adminPass, true);
        $uObj = $newConfigPlugin->createUserObject($adminLogin);
        if(isSet($data["MAILER_ADMIN"])) $uObj->personalRole->setParameterValue("core.conf", "email", $data["MAILER_ADMIN"]);
        $uObj->personalRole->setParameterValue("core.conf", "USER_DISPLAY_NAME", $adminName);
        $uObj->personalRole->setAcl('ajxp_conf', 'rw');
        AuthService::updateRole($uObj->personalRole);

        $loginP = "USER_LOGIN";
        $i = 0;
        while (isSet($data[$loginP]) && !empty($data[$loginP])) {
            $pass  = $data[str_replace("_LOGIN", "_PASS",  $loginP)];
            $pass2 = $data[str_replace("_LOGIN", "_PASS2", $loginP)];
            $name  = $data[str_replace("_LOGIN", "_NAME",  $loginP)];
            $mail  = $data[str_replace("_LOGIN", "_MAIL",  $loginP)];
            $saniLogin = AJXP_Utils::sanitize($data[$loginP], AJXP_SANITIZE_EMAILCHARS);
            AuthService::createUser($saniLogin, $pass);
            $uObj = $newConfigPlugin->createUserObject($saniLogin);
            $uObj->personalRole->setParameterValue("core.conf", "email", $mail);
            $uObj->personalRole->setParameterValue("core.conf", "USER_DISPLAY_NAME", $name);
            AuthService::updateRole($uObj->personalRole);
            $i++;
            $loginP = "USER_LOGIN_".$i;
        }


        AJXP_PluginsService::clearPluginsCache();
        AJXP_Utils::setApplicationFirstRunPassed();

        if(isSet($htAccessToUpdate)){
            HTMLWriter::charsetHeader("application/json");
            echo json_encode(array('file' => $htAccessToUpdate, 'content' => $htContent));
        }else{
            session_destroy();
            HTMLWriter::charsetHeader("text/plain");
            echo 'OK';
        }

    }

    public function testConnexions($action, $httpVars, $fileVars)
    {
        $data = array();
        AJXP_Utils::parseStandardFormParameters($httpVars, $data, null, "DRIVER_OPTION_");

        if ($action == "boot_test_sql_connexion") {

            $p = AJXP_Utils::cleanDibiDriverParameters($data["STORAGE_TYPE"]["db_type"]);
            if ($p["driver"] == "sqlite3") {
                $dbFile = AJXP_VarsFilter::filter($p["database"]);
                if (!file_exists(dirname($dbFile))) {
                    mkdir(dirname($dbFile), 0755, true);
                }
            }

            // Should throw an exception if there was a problem.
            dibi::connect($p);
            dibi::disconnect();
            echo 'SUCCESS:Connexion established!';

        } else if ($action == "boot_test_mailer") {

            $mailerPlug = AJXP_PluginsService::findPluginById("mailer.phpmailer-lite");
            $mailerPlug->loadConfigs(array("MAILER" => $data["MAILER_ENABLE"]["MAILER_SYSTEM"]));
            $mailerPlug->sendMail(
                array("adress" => $data["MAILER_ENABLE"]["MAILER_ADMIN"]),
                "Pydio Test Mail",
                "Body of the test",
                array("adress" => $data["MAILER_ENABLE"]["MAILER_ADMIN"])
            );
            echo 'SUCCESS:Mail sent to the admin adress, please check it is in your inbox!';

        }
    }

    public function _loadPluginConfig($pluginId, &$options)
    {
        $internal = self::getInternalConf();
        if ($pluginId == "core.conf" && isSet($internal["CONF_DRIVER"])) {
            // Reformat
            $options["UNIQUE_INSTANCE_CONFIG"] = array(
                "instance_name" => "conf.".$internal["CONF_DRIVER"]["NAME"],
                "group_switch_value" => "conf.".$internal["CONF_DRIVER"]["NAME"],
            );
            unset($internal["CONF_DRIVER"]["NAME"]);
            $options["UNIQUE_INSTANCE_CONFIG"] = array_merge($options["UNIQUE_INSTANCE_CONFIG"], $internal["CONF_DRIVER"]["OPTIONS"]);
            return;

        } else if ($pluginId == "core.auth" && isSet($internal["AUTH_DRIVER"])) {

            $options = $this->authLegacyToBootConf($internal["AUTH_DRIVER"]);
            return;

        }
        $jsonPath = $this->getPluginWorkDir(false)."/bootstrap.json";
        $jsonData = AJXP_Utils::loadSerialFile($jsonPath, false, "json");
        if (is_array($jsonData) && isset($jsonData[$pluginId])) {
            $options = array_merge($options, $jsonData[$pluginId]);
        }
    }

    protected function authLegacyToBootConf($legacy)
    {
        $data = array();
        $kOpts = array("LOGIN_REDIRECT","TRANSMIT_CLEAR_PASS", "AUTOCREATE_AJXPUSER");
        foreach ($kOpts as $k) {
            if(isSet($legacy["OPTIONS"][$k])) $data[$k] = $legacy["OPTIONS"][$k];
        }
        if ($legacy["NAME"] == "multi") {
            $drivers = $legacy["OPTIONS"]["DRIVERS"];
            $master = $legacy["OPTIONS"]["MASTER_DRIVER"];
            $slave = array_pop(array_diff(array_keys($drivers), array($master)));

            $data["MULTI_MODE"] = array("instance_name" => $legacy["OPTIONS"]["MODE"]);
            $data["MULTI_USER_BASE_DRIVER"] = $legacy["OPTIONS"]["USER_BASE_DRIVER"] == $master ? "master" : ($legacy["OPTIONS"]["USER_BASE_DRIVER"] == $slave ? "slave" :  "" ) ;

            $data["MASTER_INSTANCE_CONFIG"] = array_merge($legacy["OPTIONS"]["DRIVERS"][$master]["OPTIONS"], array("instance_name" => "auth.".$master));
            $data["SLAVE_INSTANCE_CONFIG"] = array_merge($legacy["OPTIONS"]["DRIVERS"][$slave]["OPTIONS"], array("instance_name" => "auth.".$slave));

        } else {
            $data["MASTER_INSTANCE_CONFIG"] = array_merge($legacy["OPTIONS"], array("instance_name" => "auth.".$legacy["NAME"]));
        }
        return $data;
    }

    /**
     * @param String $pluginId
     * @param String $options
     */
    public function _savePluginConfig($pluginId, $options)
    {
        $jsonPath = $this->getPluginWorkDir(true)."/bootstrap.json";
        $jsonData = AJXP_Utils::loadSerialFile($jsonPath, false, "json");
        if(!is_array($jsonData)) $jsonData = array();
        $jsonData[$pluginId] = $options;
        if ($pluginId == "core.conf" || $pluginId == "core.auth") {
            $testKey = ($pluginId == "core.conf" ? "UNIQUE_INSTANCE_CONFIG" : "MASTER_INSTANCE_CONFIG" );
            $current = array();
            $this->_loadPluginConfig($pluginId, $current);
            if (isSet($current[$testKey]["instance_name"]) && $current[$testKey]["instance_name"] != $options[$testKey]["instance_name"]) {
                $forceDisconnexion = $pluginId;
            }
        }
        if (file_exists($jsonPath)) {
            copy($jsonPath, $jsonPath.".bak");
        }
        AJXP_Utils::saveSerialFile($jsonPath, $jsonData, true, false, "json", true);
        if (isSet($forceDisconnexion)) {
            if ($pluginId == "core.conf") {
                // DISCONNECT
                AuthService::disconnect();
            } else if ($pluginId == "core.auth") {
                // DELETE admin_counted file and DISCONNECT
                @unlink(AJXP_CACHE_DIR."/admin_counted");
            }
        }
    }

    /**
     * Returns a list of available repositories (dynamic ones only, not the ones defined in the config file).
     * @param AbstractAjxpUser $user
     * @return Array
     */
    public function listRepositories($user = null)
    {
        // TODO: Implement listRepositories() method.
    }

    /**
     * Returns a list of available repositories (dynamic ones only, not the ones defined in the config file).
     * @param Array $criteria
     * @return Array
     */
    public function listRepositoriesWithCriteria($criteria){

    }

    /**
     * Retrieve a Repository given its unique ID.
     *
     * @param String $repositoryId
     * @return Repository
     */
    public function getRepositoryById($repositoryId)
    {
        // TODO: Implement getRepositoryById() method.
    }

    /**
     * Retrieve a Repository given its alias.
     *
     * @param String $repositorySlug
     * @return Repository
     */
    public function getRepositoryByAlias($repositorySlug)
    {
        // TODO: Implement getRepositoryByAlias() method.
    }

    /**
     * Stores a repository, new or not.
     *
     * @param Repository $repositoryObject
     * @param Boolean $update
     * @return -1 if failed
     */
    public function saveRepository($repositoryObject, $update = false)
    {
        // TODO: Implement saveRepository() method.
    }

    /**
     * Delete a repository, given its unique ID.
     *
     * @param String $repositoryId
     */
    public function deleteRepository($repositoryId)
    {
        // TODO: Implement deleteRepository() method.
    }

    /**
     * Must return an associative array of roleId => AjxpRole objects.
     * @param array $roleIds
     * @param boolean $excludeReserved,
     * @return array AjxpRole[]
     */
    public function listRoles($roleIds = array(), $excludeReserved = false)
    {
        // TODO: Implement listRoles() method.
    }

    public function saveRoles($roles)
    {
        // TODO: Implement saveRoles() method.
    }

    /**
     * @param AJXP_Role $role
     * @return void
     */
    public function updateRole($role)
    {
        // TODO: Implement updateRole() method.
    }

    /**
     * @param AJXP_Role|String $role
     * @return void
     */
    public function deleteRole($role)
    {
        // TODO: Implement deleteRole() method.
    }

    /**
     * Specific queries
     */
    public function countAdminUsers()
    {
        // TODO: Implement countAdminUsers() method.
    }

    /**
     * @param array $context
     * @param String $fileName
     * @param String $ID
     * @return String $ID
     */
    public function saveBinary($context, $fileName, $ID = null)
    {
        // TODO: Implement saveBinary() method.
    }

    /**
     * @param array $context
     * @param String $ID
     * @param Stream $outputStream
     * @return boolean
     */
    public function loadBinary($context, $ID, $outputStream = null)
    {
        // TODO: Implement loadBinary() method.
    }

    /**
     * @param array $context
     * @param String $ID
     * @return boolean
     */
    public function deleteBinary($context, $ID)
    {
        // TODO: Implement deleteBinary() method.
    }

    /**
     * Function for deleting a user
     *
     * @param String $userId
     * @param Array $deletedSubUsers
     */
    public function deleteUser($userId, &$deletedSubUsers)
    {
        // TODO: Implement deleteUser() method.
    }

    /**
     * Instantiate the right class
     *
     * @param string $userId
     * @return AbstractAjxpUser
     */
    public function instantiateAbstractUserImpl($userId)
    {
        // TODO: Implement instantiateAbstractUserImpl() method.
    }

    public function getUserClassFileName()
    {
        // TODO: Implement getUserClassFileName() method.
    }

    /**
     * @param $userId
     * @return AbstractAjxpUser[]
     */
    public function getUserChildren($userId)
    {
        // TODO: Implement getUserChildren() method.
    }

    /**
     * @param string $repositoryId
     * @return array()
     */
    public function getUsersForRepository($repositoryId)
    {
        // TODO: Implement getUsersForRepository() method.
    }

    /**
     * @abstract
     * @param string $repositoryId
     * @param string $rolePrefix
     * @param bool $countOnly
     * @return array()
     */
    public function getRolesForRepository($repositoryId, $rolePrefix = '', $countOnly = false){

        // TODO: Implement getUsersForRepository() method.

    }
    /**
     * @param string $repositoryId
     * @param boolean $details
     * @return array('internal' => count, 'external' => count)
     */
    public function countUsersForRepository($repositoryId, $details = false){

    }

    /**
     * @param AbstractAjxpUser[] $flatUsersList
     * @param string $baseGroup
     * @param bool $fullTree
     * @return void
     */
    public function filterUsersByGroup(&$flatUsersList, $baseGroup = "/", $fullTree = false)
    {
        // TODO: Implement filterUsersByGroup() method.
    }

    /**
     * @param string $groupPath
     * @param string $groupLabel
     * @return mixed
     */
    public function createGroup($groupPath, $groupLabel)
    {
        // TODO: Implement createGroup() method.
    }

    /**
     * @param $groupPath
     * @return void
     */
    public function deleteGroup($groupPath)
    {
        // TODO: Implement deleteGroup() method.
    }

    /**
     * @param string $groupPath
     * @param string $groupLabel
     * @return void
     */
    public function relabelGroup($groupPath, $groupLabel)
    {
        // TODO: Implement relabelGroup() method.
    }

    /**
     * @param string $baseGroup
     * @return string[]
     */
    public function getChildrenGroups($baseGroup = "/")
    {
        // TODO: Implement getChildrenGroups() method.
    }

    /**
     * @abstract
     * @param String $keyType
     * @param String $keyId
     * @param String $userId
     * @param Array $data
     * @return boolean
     */
    public function saveTemporaryKey($keyType, $keyId, $userId, $data){}

    /**
     * @abstract
     * @param String $keyType
     * @param String $keyId
     * @return array
     */
    public function loadTemporaryKey($keyType, $keyId){}

    /**
     * @abstract
     * @param String $keyType
     * @param String $keyId
     * @return boolean
     */
    public function deleteTemporaryKey($keyType, $keyId){}

    /**
     * @abstract
     * @param String $keyType
     * @param String $expiration
     * @return null
     */
    public function pruneTemporaryKeys($keyType, $expiration){}

}
