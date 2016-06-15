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
namespace Pydio\Share\View;


use DOMXPath;
use Pydio\Core\Model\Context;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\Utils;
use Pydio\Core\Controller\XMLWriter;
use Pydio\Core\Controller\HTMLWriter;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Share\ShareCenter;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class MinisiteRenderer
 * View class to load a share and display it as a minisite
 *
 * @package Pydio\Share\View
 */
class MinisiteRenderer
{
    /**
     * Render a simple error instead of the minisite
     * @param $data
     * @param string $hash
     * @param null $error
     */
    public static function renderError($data, $hash = '', $error = null){
        self::loadMinisite($data, $hash, $error);
    }

    /**
     * Load the minisite
     *
     * @param $data
     * @param string $hash
     * @param null $error
     * @throws \Exception
     * @throws \Pydio\Core\Exception\LoginException
     * @throws \Pydio\Core\Exception\WorkspaceNotFoundException
     */
    public static function loadMinisite($data, $hash = '', $error = null)
    {
        $repository = $data["REPOSITORY"];
        $confs = [];
        $ctx = Context::emptyContext();
        PluginsService::getInstance($ctx)->initActivePlugins();
        $shareCenter = ShareCenter::getShareCenter($ctx);
        if($shareCenter !== false){
            $confs = $shareCenter->getConfigs();
        }
        $minisiteLogo = "plugins/gui.ajax/PydioLogo250.png";
        if(!empty($confs["CUSTOM_MINISITE_LOGO"])){
            $logoPath = $confs["CUSTOM_MINISITE_LOGO"];
            if (strpos($logoPath, "plugins/") === 0 && is_file(AJXP_INSTALL_PATH."/".$logoPath)) {
                $minisiteLogo = $logoPath;
            }else{
                $minisiteLogo = "public/?get_action=get_global_binary_param&binary_id=". $logoPath;
            }
        }
        // Default value
        if(isSet($data["AJXP_TEMPLATE_NAME"])){
            $templateName = $data["AJXP_TEMPLATE_NAME"];
            if($templateName == "ajxp_film_strip" && Utils::userAgentIsMobile()){
                $templateName = "ajxp_shared_folder";
            }
        }
        if(isSet($repository)){
            $repoObject = RepositoryService::getRepositoryById($repository);
            if(!is_object($repoObject)){
                $mess = LocaleService::getMessages();
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
        Controller::applyHook("tpl.filter_html", [$ctx, &$html]);
        $html = XMLWriter::replaceAjxpXmlKeywords($html);
        $html = str_replace("AJXP_MINISITE_LOGO", $minisiteLogo, $html);
        $html = str_replace("AJXP_APPLICATION_TITLE", ConfService::getCoreConf("APPLICATION_TITLE"), $html);
        $html = str_replace("PYDIO_APP_TITLE", ConfService::getCoreConf("APPLICATION_TITLE"), $html);
        if(isSet($repository) && isSet($repoObject)){
            $html = str_replace("AJXP_START_REPOSITORY", $repository, $html);
            $html = str_replace("AJXP_REPOSITORY_LABEL", RepositoryService::getRepositoryById($repository)->getDisplay(), $html);
        }
        $html = str_replace('AJXP_HASH_LOAD_ERROR', isSet($error)?$error:'', $html);
        $html = str_replace("AJXP_TEMPLATE_NAME", $templateName, $html);
        $html = str_replace("AJXP_LINK_HASH", $hash, $html);
        $guiConfigs = PluginsService::getInstance($ctx)->getPluginById("gui.ajax")->getConfigs();
        $html = str_replace("AJXP_THEME", $guiConfigs["GUI_THEME"] , $html);

        if(isSet($_GET["dl"]) && isSet($_GET["file"])){
            AuthService::$useSession = false;
        }else{
            session_name("AjaXplorer_Shared".str_replace(".","_",$hash));
            session_start();
            AuthService::disconnect();
        }
        $loggedUser = null;
        if (!empty($data["PRELOG_USER"])) {
            $loggedUser = AuthService::logUser($data["PRELOG_USER"], "", true);
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
            $repoObject = UsersService::getRepositoryWithPermission($loggedUser, $repository);
            PluginsService::getInstance(Context::emptyContext());
            $errMessage = null;
            try {
                $params = $_GET;
                $ACTION = "download";
                if(isset($_GET["ct"])){
                    $mime = pathinfo($params["file"], PATHINFO_EXTENSION);
                    $editors = PluginsService::getInstance(Context::emptyContext())->searchAllManifests("//editor[contains(@mimes,'$mime') and @previewProvider='true']", "node", true, true, false);
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
                $ctx = Context::fromGlobalServices();
                $req =  Controller::executableRequest($ctx, $ACTION, $params);
                $response = Controller::run($req);
                $emitter = new \Pydio\Core\Http\Middleware\SapiMiddleware();
                $emitter->emitResponse($req, $response);
            } catch (\Exception $e) {
                $errMessage = $e->getMessage();
            }
            if($errMessage == null) return;
            $html = str_replace('AJXP_HASH_LOAD_ERROR', $errMessage, $html);
        }

        if (isSet($_GET["lang"])) {
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

        $serverBaseUrl = Utils::detectServerURL(true);

        // Update Host dynamically if it differ from registered one.
        $registeredHost = parse_url($tPath, PHP_URL_HOST);
        $currentHost = parse_url($serverBaseUrl, PHP_URL_HOST);
        if($registeredHost != $currentHost){
            $tPath = str_replace($registeredHost, $currentHost, $tPath);
        }
        // Update scheme dynamically if it differ from registered one.
        $registeredScheme = parse_url($tPath, PHP_URL_SCHEME);
        $currentScheme = parse_url($serverBaseUrl, PHP_URL_SCHEME);
        if($registeredScheme != $currentScheme){
            $tPath = str_replace($registeredScheme."://", $currentScheme."://", $tPath);
        }
        global $skipHtmlBase;
        if(!empty($skipHtmlBase)){
            $html = str_replace("<base href=\"AJXP_PATH_TO_ROOT\"/>", "", $html);
        }else{
            $html = str_replace("AJXP_PATH_TO_ROOT", rtrim($tPath, "/")."/", $html);
        }
        HTMLWriter::internetExplorerMainDocumentHeader();
        HTMLWriter::charsetHeader();
        echo($html);
    }

}