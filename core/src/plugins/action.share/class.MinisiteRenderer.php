<?php
/*
 * Copyright 2007-2015 Abstrium <contact (at) pydio.com>
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

defined('AJXP_EXEC') or die('Access not allowed');


class MinisiteRenderer
{
    public static function renderError($data, $hash = '', $error = null){
        self::loadMinisite($data, $hash, $error);
    }

    public static function loadMinisite($data, $hash = '', $error = null)
    {
        if(isset($data["SECURITY_MODIFIED"]) && $data["SECURITY_MODIFIED"] === true){
            $mess = ConfService::getMessages();
            $error = $mess['share_center.164'];
        }
        $repository = $data["REPOSITORY"];
        AJXP_PluginsService::getInstance()->initActivePlugins();
        $shareCenter = AJXP_PluginsService::findPlugin("action", "share");
        $confs = $shareCenter->getConfigs();
        $minisiteLogo = "plugins/gui.ajax/PydioLogo250.png";
        if(!empty($confs["CUSTOM_MINISITE_LOGO"])){
            $logoPath = $confs["CUSTOM_MINISITE_LOGO"];
            if (strpos($logoPath, "plugins/") === 0 && is_file(AJXP_INSTALL_PATH."/".$logoPath)) {
                $minisiteLogo = $logoPath;
            }else{
                $minisiteLogo = "index_shared.php?get_action=get_global_binary_param&binary_id=". $logoPath;
            }
        }
        // Default value
        if(isSet($data["AJXP_TEMPLATE_NAME"])){
            $templateName = $data["AJXP_TEMPLATE_NAME"];
            if($templateName == "ajxp_film_strip" && AJXP_Utils::userAgentIsMobile()){
                $templateName = "ajxp_shared_folder";
            }
        }
        if(isSet($repository)){
            $repoObject = ConfService::getRepositoryById($repository);
            if(!is_object($repoObject)){
                $mess = ConfService::getMessages();
                $error = $mess["share_center.166"];
                $templateName = "ajxp_unique_strip";
                $repoObject = null;
            }
        }
        if(!isSet($templateName) && isSet($repoObject)){
            $filter = $repoObject->getContentFilter();
            if(!empty($filter) && count($filter->virtualPaths) == 1){
                $templateName = "ajxp_unique_strip";
            }else{
                $templateName = "ajxp_shared_folder";
            }
        }
        if(!isSet($templateName) && isSet($error)){
            $templateName = "ajxp_unique_strip";
        }
        // UPDATE TEMPLATE
        $html = file_get_contents(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/action.share/res/minisite.php");
        AJXP_Controller::applyHook("tpl.filter_html", array(&$html));
        $html = AJXP_XMLWriter::replaceAjxpXmlKeywords($html);
        $html = str_replace("AJXP_MINISITE_LOGO", $minisiteLogo, $html);
        $html = str_replace("AJXP_APPLICATION_TITLE", ConfService::getCoreConf("APPLICATION_TITLE"), $html);
        $html = str_replace("PYDIO_APP_TITLE", ConfService::getCoreConf("APPLICATION_TITLE"), $html);
        if(isSet($repository) && isSet($repoObject)){
            $html = str_replace("AJXP_START_REPOSITORY", $repository, $html);
            $html = str_replace("AJXP_REPOSITORY_LABEL", ConfService::getRepositoryById($repository)->getDisplay(), $html);
        }
        $html = str_replace('AJXP_HASH_LOAD_ERROR', isSet($error)?$error:'', $html);
        $html = str_replace("AJXP_TEMPLATE_NAME", $templateName, $html);
        $html = str_replace("AJXP_LINK_HASH", $hash, $html);
        $guiConfigs = AJXP_PluginsService::findPluginById("gui.ajax")->getConfigs();
        $html = str_replace("AJXP_THEME", $guiConfigs["GUI_THEME"] , $html);

        if(isSet($_GET["dl"]) && isSet($_GET["file"])){
            AuthService::$useSession = false;
        }else{
            session_name("AjaXplorer_Shared".str_replace(".","_",$hash));
            session_start();
            AuthService::disconnect();
        }

        if (!empty($data["PRELOG_USER"])) {
            AuthService::logUser($data["PRELOG_USER"], "", true);
            $html = str_replace("AJXP_PRELOGED_USER", "ajxp_preloged_user", $html);
        } else if(isSet($data["PRESET_LOGIN"])) {
            $_SESSION["PENDING_REPOSITORY_ID"] = $repository;
            $_SESSION["PENDING_FOLDER"] = "/";
            $html = str_replace("AJXP_PRELOGED_USER", $data["PRESET_LOGIN"], $html);
        } else{
            $html = str_replace("AJXP_PRELOGED_USER", "ajxp_legacy_minisite", $html);
        }
        if(isSet($hash)){
            $_SESSION["CURRENT_MINISITE"] = $hash;
        }

        if(isSet($_GET["dl"]) && isSet($_GET["file"]) && (!isSet($data["DOWNLOAD_DISABLED"]) || $data["DOWNLOAD_DISABLED"] === false)){
            ConfService::switchRootDir($repository);
            ConfService::loadRepositoryDriver();
            AJXP_PluginsService::deferBuildingRegistry();
            AJXP_PluginsService::getInstance()->initActivePlugins();
            AJXP_PluginsService::flushDeferredRegistryBuilding();
            $errMessage = null;
            try {
                $params = $_GET;
                $ACTION = "download";
                if(isset($_GET["ct"])){
                    $mime = pathinfo($params["file"], PATHINFO_EXTENSION);
                    $editors = AJXP_PluginsService::searchAllManifests("//editor[contains(@mimes,'$mime') and @previewProvider='true']", "node", true, true, false);
                    if (count($editors)) {
                        foreach ($editors as $editor) {
                            $xPath = new DOMXPath($editor->ownerDocument);
                            $callbacks = $xPath->query("//action[@contentTypedProvider]", $editor);
                            if ($callbacks->length) {
                                $ACTION = $callbacks->item(0)->getAttribute("name");
                                if($ACTION == "audio_proxy") {
                                    $params["file"] = "base64encoded:".base64_encode($params["file"]);
                                }
                                break;
                            }
                        }
                    }
                }
                AJXP_Controller::registryReset();
                AJXP_Controller::findActionAndApply($ACTION, $params, null);
            } catch (Exception $e) {
                $errMessage = $e->getMessage();
            }
            if($errMessage == null) return;
            $html = str_replace('AJXP_HASH_LOAD_ERROR', $errMessage, $html);
        }

        if (isSet($_GET["lang"])) {
            $loggedUser = &AuthService::getLoggedUser();
            if ($loggedUser != null) {
                $loggedUser->setPref("lang", $_GET["lang"]);
            } else {
                setcookie("AJXP_lang", $_GET["lang"]);
            }
        }

        if (!empty($data["AJXP_APPLICATION_BASE"])) {
            $tPath = $data["AJXP_APPLICATION_BASE"];
        } else {
            $tPath = (!empty($data["TRAVEL_PATH_TO_ROOT"]) ? $data["TRAVEL_PATH_TO_ROOT"] : "../..");
        }
        // Update Host dynamically if it differ from registered one.
        $registeredHost = parse_url($tPath, PHP_URL_HOST);
        $currentHost = parse_url(AJXP_Utils::detectServerURL("SERVER_URL"), PHP_URL_HOST);
        if($registeredHost != $currentHost){
            $tPath = str_replace($registeredHost, $currentHost, $tPath);
        }
        $html = str_replace("AJXP_PATH_TO_ROOT", rtrim($tPath, "/")."/", $html);
        HTMLWriter::internetExplorerMainDocumentHeader();
        HTMLWriter::charsetHeader();
        echo($html);
    }

}