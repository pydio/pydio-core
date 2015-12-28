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
            $dir = $this->getPluginWorkDir(true);
            if(!is_file($dir.DIRECTORY_SEPARATOR."server_uuid")){
                file_put_contents($dir.DIRECTORY_SEPARATOR."server_uuid", md5(json_encode($_SERVER)));
            }
        } catch (Exception $e) {
            die("Impossible write into the AJXP_DATA_PATH folder: Make sure to grant write access to this folder for your webserver!");
        }
    }

    public function loadManifest()
    {
        parent::loadManifest();
        if (!AJXP_Utils::detectApplicationFirstRun()) {
            $actions = $this->getXPath()->query("server_settings|registry_contributions");
            foreach ($actions as $ac) {
                $ac->parentNode->removeChild($ac);
            }
            $this->reloadXPath();
        }
    }

    public function getServerUuid(){
        return file_get_contents($this->getPluginWorkDir().DIRECTORY_SEPARATOR."server_uuid");
    }

    public function printFormFromServerSettings($fullManifest){

        AJXP_XMLWriter::header("admin_data");
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
    public function loadInstallerForm($action, $httpVars, $fileVars)
    {
        if(isSet($httpVars["lang"])){
            ConfService::setLanguage($httpVars["lang"]);
        }
        $fullManifest = $this->getManifestRawContent("", "xml");
        $this->printFormFromServerSettings($fullManifest);
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

        list($newConfigPlugin, $newAuthPlugin) = $this->createBootstrapConf($data);

        $this->feedPluginsOptions($newConfigPlugin, $data);

        ConfService::setTmpStorageImplementations($newConfigPlugin, $newAuthPlugin);
        $this->createUsers($data);

        $this->setAdditionalData($data);
        $htContent = null;
        $htAccessToUpdate = $this->updateHtAccess($data, $htContent);
        $this->sendInstallResult($htAccessToUpdate, $htContent);

    }

    /**
     * Send output to the user.
     * @param String $htAccessToUpdate file path
     * @param String $htContent file content
     */
    public function sendInstallResult($htAccessToUpdate, $htContent){
        ConfService::clearAllCaches();
        AJXP_Utils::setApplicationFirstRunPassed();

        if($htAccessToUpdate != null){
            HTMLWriter::charsetHeader("application/json");
            echo json_encode(array('file' => $htAccessToUpdate, 'content' => $htContent));
        }else{
            session_destroy();
            HTMLWriter::charsetHeader("text/plain");
            echo 'OK';
        }

    }

    /**
     * Non-impacting operations
     * @param array $data Installer parsed form result
     * @throws Exception
     */
    public function setAdditionalData($data){
        if($data["ENCODING"] != (defined('AJXP_LOCALE')?AJXP_LOCALE:SystemTextEncoding::getEncoding())){
            file_put_contents($this->getPluginWorkDir()."/encoding.php", "<?php \$ROOT_ENCODING='".$data["ENCODING"]."';");
        }
    }

    /**
     * Update the plugins parameters. OptionsLinks can be an array associating keys of $data
     * to pluginID/plugin_parameter_name
     *
     * @param AbstractConfDriver $confDriver
     * @param array $data
     * @param null|array $optionsLinks
     */
    public function feedPluginsOptions($confDriver, $data, $optionsLinks = null){

        if(isSet($optionsLinks)){
            $direct = $optionsLinks;
        }else{
            $data["ENABLE_NOTIF"] = true;
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
        }

        foreach ($direct as $key => $value) {
            list($pluginId, $param) = explode("/", $value);
            $options = array();
            $confDriver->_loadPluginConfig($pluginId, $options);
            $options[$param] = $data[$key];
            $confDriver->_savePluginConfig($pluginId, $options);
        }


    }

    /**
     * Create or update the bootstrap json file.
     * @param Array $data Parsed result of the installer form
     * @return array 2 entries array containing the new Conf Driver (0) and Auth Driver (1)
     * @throws Exception
     */
    public function createBootstrapConf($data){

        // Create a custom bootstrap.json file
        $coreConf = array(); $coreAuth = array();
        $this->_loadPluginConfig("core.conf", $coreConf);
        $this->_loadPluginConfig("core.auth", $coreAuth);
        if(!isSet($coreConf["UNIQUE_INSTANCE_CONFIG"])) $coreConf["UNIQUE_INSTANCE_CONFIG"] = array();
        if(!isSet($coreAuth["MASTER_INSTANCE_CONFIG"])) $coreAuth["MASTER_INSTANCE_CONFIG"] = array();
        $coreConf["AJXP_CLI_SECRET_KEY"] = AJXP_Utils::generateRandomString(24, true);

        // REWRITE BOOTSTRAP.JSON
        $coreConf["DIBI_PRECONFIGURATION"] = $data["db_type"];
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

        // DETECT REQUIRED SQL TABLES AND INSTALL THEM
        $registry = AJXP_PluginsService::getInstance()->getDetectedPlugins();
        $driverData = array("SQL_DRIVER" => $data["db_type"]);
        foreach($registry as $type => $plugins){
            foreach($plugins as $plugObject){
                if($plugObject instanceof SqlTableProvider){
                    $plugObject->installSQLTables($driverData);
                }
            }
        }


        $oldBoot = $this->getPluginWorkDir(true)."/bootstrap.json";
        if (is_file($oldBoot)) {
            copy($oldBoot, $oldBoot.".bak");
            unlink($oldBoot);
        }
        $newBootstrap = array("core.conf" => $coreConf, "core.auth" => $coreAuth);
        AJXP_Utils::saveSerialFile($oldBoot, $newBootstrap, true, false, "json", true);


        // Write new bootstrap and reload conf plugin!
        $coreConf["UNIQUE_INSTANCE_CONFIG"]["SQL_DRIVER"] = $coreConf["DIBI_PRECONFIGURATION"];
        $coreAuth["MASTER_INSTANCE_CONFIG"]["SQL_DRIVER"] = $coreConf["DIBI_PRECONFIGURATION"];

        $newConfigPlugin = ConfService::instanciatePluginFromGlobalParams($coreConf["UNIQUE_INSTANCE_CONFIG"], "AbstractConfDriver");
        $newAuthPlugin = ConfService::instanciatePluginFromGlobalParams($coreAuth["MASTER_INSTANCE_CONFIG"], "AbstractAuthDriver");

        $sqlPlugs = array(
            "core.notifications/UNIQUE_FEED_INSTANCE" => "feed.sql",
            "core.log/UNIQUE_PLUGIN_INSTANCE" => "log.sql",
            "core.mq/UNIQUE_MS_INSTANCE" => "mq.sql"
        );
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

        return array($newConfigPlugin, $newAuthPlugin);
    }

    /**
     * Tries to detect if the .htaccess file needs updating. If so, returns the path to this file
     * and feed the $htContent with target data. That way it can be sent to the users to tell him
     * how to update the content.
     *
     * @param $data
     * @param $htContent
     * @return null|string
     */
    public function updateHtAccess($data, &$htContent){

        $htAccessToUpdate = null;
        $tpl = file_get_contents($this->getBaseDir()."/htaccess.tpl");
        if(!empty($data["SERVER_URI"]) && $data["SERVER_URI"] != "/"){
            $htContent = str_replace('${APPLICATION_ROOT}', $data["SERVER_URI"], $tpl);
        }else{
            $htContent = str_replace('${APPLICATION_ROOT}/', "/", $tpl);
            $htContent = str_replace('${APPLICATION_ROOT}', "/", $htContent);
        }
        if(is_writeable(AJXP_INSTALL_PATH."/.htaccess")){
            file_put_contents(AJXP_INSTALL_PATH."/.htaccess", $htContent);
        }else if(AJXP_PACKAGING == "zip"){
            $testContent = @file_get_contents(AJXP_INSTALL_PATH."/.htaccess");
            if($testContent === false || $testContent != $htContent){
                $htAccessToUpdate = AJXP_INSTALL_PATH."/.htaccess";
            }
        }
        return $htAccessToUpdate;

    }

    /**
     * Create the users based on the installer form results.
     * @param array $data Parsed form results
     * @param bool $loginIsEmail Whether to use the login as primary email.
     * @throws Exception
     */
    public function createUsers($data, $loginIsEmail = false){
        $newConfigPlugin = ConfService::getConfStorageImpl();
        require_once($newConfigPlugin->getUserClassFileName());

        $adminLogin = AJXP_Utils::sanitize($data["ADMIN_USER_LOGIN"], AJXP_SANITIZE_EMAILCHARS);
        $adminName = $data["ADMIN_USER_NAME"];
        $adminPass = $data["ADMIN_USER_PASS"];
        AuthService::createUser($adminLogin, $adminPass, true);
        $uObj = $newConfigPlugin->createUserObject($adminLogin);
        if($loginIsEmail){
            $uObj->personalRole->setParameterValue("core.conf", "email", $data["ADMIN_USER_LOGIN"]);
        }else if(isSet($data["MAILER_ADMIN"])) {
            $uObj->personalRole->setParameterValue("core.conf", "email", $data["MAILER_ADMIN"]);
        }
        $uObj->personalRole->setParameterValue("core.conf", "USER_DISPLAY_NAME", $adminName);
        $repos = ConfService::getRepositoriesList("all", false);
        foreach($repos as $repo){
            $uObj->personalRole->setAcl($repo->getId(), "rw");
        }
        AuthService::updateRole($uObj->personalRole);

        $loginP = "USER_LOGIN";
        $i = 0;
        while (isSet($data[$loginP]) && !empty($data[$loginP])) {
            $pass  = $data[str_replace("_LOGIN", "_PASS",  $loginP)];
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

    }

    /**
     * Helpers to test SQL connection and send a test email.
     * @param $action
     * @param $httpVars
     * @param $fileVars
     * @throws Exception
     */
    public function testConnexions($action, $httpVars, $fileVars)
    {
        $data = array();
        AJXP_Utils::parseStandardFormParameters($httpVars, $data, null, "DRIVER_OPTION_");

        if ($action == "boot_test_sql_connexion") {

            $p = AJXP_Utils::cleanDibiDriverParameters($data["db_type"]);
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
        return array();
    }

    /**
     * Returns a list of available repositories (dynamic ones only, not the ones defined in the config file).
     * @param Array $criteria
     * @param int $count
     * @return Array
     */
    public function listRepositoriesWithCriteria($criteria, &$count = null){
        return array();
    }

    /**
     * Retrieve a Repository given its unique ID.
     *
     * @param String $repositoryId
     * @return Repository
     */
    public function getRepositoryById($repositoryId)
    {
        return null;
    }

    /**
     * Retrieve a Repository given its alias.
     *
     * @param String $repositorySlug
     * @return Repository
     */
    public function getRepositoryByAlias($repositorySlug) {
        return null;
    }

    /**
     * Stores a repository, new or not.
     *
     * @param Repository $repositoryObject
     * @param Boolean $update
     * @return int -1 if failed
     */
    public function saveRepository($repositoryObject, $update = false) {
        return -1;
    }

    /**
     * Delete a repository, given its unique ID.
     *
     * @param String $repositoryId
     */
    public function deleteRepository($repositoryId) {

    }

    /**
     * Must return an associative array of roleId => AjxpRole objects.
     * @param array $roleIds
     * @param boolean $excludeReserved,
     * @return array AjxpRole[]
     */
    public function listRoles($roleIds = array(), $excludeReserved = false) {
        return array();
    }

    public function saveRoles($roles) {
    }

    /**
     * @param AJXP_Role $role
     * @param AbstractAjxpUser $userObject
     * @return void
     */
    public function updateRole($role, $userObject = null) {
    }

    /**
     * @param AJXP_Role|String $role
     * @return void
     */
    public function deleteRole($role) {

    }

    /**
     * Specific queries
     */
    public function countAdminUsers() {
    }

    /**
     * @param array $context
     * @param String $fileName
     * @param String $ID
     * @return String $ID
     */
    public function saveBinary($context, $fileName, $ID = null) {
        return $ID;
    }

    /**
     * @param array $context
     * @param String $ID
     * @param Stream $outputStream
     * @return boolean
     */
    public function loadBinary($context, $ID, $outputStream = null) {
        return false;
    }

    /**
     * @param array $context
     * @param String $ID
     * @return boolean
     */
    public function deleteBinary($context, $ID) {
        return false;
    }

    /**
     * Function for deleting a user
     *
     * @param String $userId
     * @param Array $deletedSubUsers
     */
    public function deleteUser($userId, &$deletedSubUsers) {
        return array();
    }

    /**
     * Instantiate the right class
     *
     * @param string $userId
     * @return AbstractAjxpUser
     */
    public function instantiateAbstractUserImpl($userId) {
        return null;
    }

    public function getUserClassFileName() {
        return "";
    }

    /**
     * @param $userId
     * @return AbstractAjxpUser[]
     */
    public function getUserChildren($userId) {
        return array();
    }

    /**
     * @param string $repositoryId
     * @return array()
     */
    public function getUsersForRepository($repositoryId) {
        return array();
    }

    /**
     * @abstract
     * @param string $repositoryId
     * @param string $rolePrefix
     * @param bool $countOnly
     * @return array()
     */
    public function getRolesForRepository($repositoryId, $rolePrefix = '', $countOnly = false){
        return array();
    }

    /**
     * @param string $repositoryId
     * @param boolean $details
     * @param bool $admin
     * @return array
     */
    public function countUsersForRepository($repositoryId, $details = false, $admin=false){
        return array();
    }

    /**
     * @param AbstractAjxpUser[] $flatUsersList
     * @param string $baseGroup
     * @param bool $fullTree
     * @return void
     */
    public function filterUsersByGroup(&$flatUsersList, $baseGroup = "/", $fullTree = false) {
    }

    /**
     * @param string $groupPath
     * @param string $groupLabel
     * @return mixed
     */
    public function createGroup($groupPath, $groupLabel) {
        return false;
    }

    /**
     * @param $groupPath
     * @return void
     */
    public function deleteGroup($groupPath) {
    }

    /**
     * @param string $groupPath
     * @param string $groupLabel
     * @return void
     */
    public function relabelGroup($groupPath, $groupLabel) {
    }

    /**
     * @param string $baseGroup
     * @return string[]
     */
    public function getChildrenGroups($baseGroup = "/") {
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

    /**
     * Check if group already exists
     * @param string $groupPath
     * @return boolean
     */
    public function groupExists($groupPath)
    {
        return false;
    }
}
