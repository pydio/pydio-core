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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Share\View;


use DOMXPath;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Http\Server;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\Http\UserAgent;
use Pydio\Core\Utils\Vars\XMLFilter;
use Pydio\Core\Controller\HTMLWriter;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Share\ShareCenter;
use Zend\Diactoros\Response;

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
     * Called as a middleware
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @return ResponseInterface
     */
    public static function handleRequest(ServerRequestInterface $requestInterface, ResponseInterface $responseInterface, callable $next = null){

        $hash       = $requestInterface->getAttribute("hash");
        $data       = $requestInterface->getAttribute("data");
        $params     = $requestInterface->getParsedBody();

        if(isSet($params["dl"]) && isSet($params["file"])){
            $responseInterface = self::triggerDownload($requestInterface, $responseInterface);
        }else{
            $responseInterface = self::writeHtml($responseInterface, $requestInterface->getAttribute("ctx"), $data, $hash);
        }
        return Server::callNextMiddleWare($requestInterface, $responseInterface, $next);

    }

    /**
     * @param array $data
     * @param null|string $error
     * @return string
     */
    protected static function computeTemplateName($data, $error = null){
        if($error !== null){
            return "ajxp_unique_strip";
        }

    }

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
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @return ResponseInterface
     * @throws PydioException
     * @throws \Pydio\Core\Exception\ActionNotFoundException
     * @throws \Pydio\Core\Exception\AuthRequiredException
     */
    private static function triggerDownload(ServerRequestInterface $requestInterface, ResponseInterface $responseInterface){

        /** @var ContextInterface $ctx */
        $ctx        = $requestInterface->getAttribute("ctx");
        $data       = $requestInterface->getAttribute("data");
        $params     = $requestInterface->getParsedBody();
        if(isSet($data["DOWNLOAD_DISABLED"]) && $data["DOWNLOAD_DISABLED"] === true){
            throw new PydioException("You are not allowed to download this file");
        }
        if(!$ctx->hasRepository()){
            throw new PydioException("No active repository");
        }

        $errMessage = null;
        $ACTION = "download";
        if(isset($params["ct"])){
            $mime = pathinfo($params["file"], PATHINFO_EXTENSION);
            $editors = PluginsService::getInstance($ctx)->searchAllManifests("//editor[contains(@mimes,'$mime') and @previewProvider='true']", "node", true, true, false);
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
        $req =  Controller::executableRequest($ctx, $ACTION, $params);
        return Controller::run($req);

    }

    /**
     * [LEGACY] Load the minisite
     * @deprecated 
     * @param $data
     * @param string $hash
     * @param null $error
     * @throws \Exception
     * @throws \Pydio\Core\Exception\LoginException
     * @throws \Pydio\Core\Exception\WorkspaceNotFoundException
     */
    public static function loadMinisite($data, $hash = '', $error = null)
    {
        $resp = new Response();
        $context = Context::emptyContext();
        HTMLWriter::internetExplorerMainDocumentHeader();
        HTMLWriter::charsetHeader();
        $resp = self::writeHtml($resp, $context, $data, $hash, $error);
        $b = $resp->getBody();
        $b->rewind();
        echo $b->getContents();
    }


    /**
     * @param ResponseInterface $responseInterface
     * @param ContextInterface $context
     * @param $data
     * @param string $hash
     * @param null $error
     * @return ResponseInterface
     * @throws \Exception
     */
    public static function writeHtml(ResponseInterface $responseInterface, ContextInterface $context, $data, $hash = '', $error = null){

        $confs = [];
        $shareCenter = ShareCenter::getShareCenter($context);
        if($shareCenter !== false){
            $confs = $shareCenter->getConfigs();
        }
        $repoObject = $context->getRepository();
        if($repoObject === null && (isSet($data['PRESET_LOGIN']) || ($context->hasUser() && $context->getUser()->getLock() !== false)) && isSet($data["REPOSITORY"])){
            $repoObject = RepositoryService::getRepositoryById($data["REPOSITORY"]);
        }

        $minisiteLogo = "plugins/gui.ajax/PydioLogo250.png";
        if(!empty($confs["CUSTOM_MINISITE_LOGO"])){
            $logoPath = $confs["CUSTOM_MINISITE_LOGO"];
            if (strpos($logoPath, "plugins/") === 0 && is_file(AJXP_INSTALL_PATH."/".$logoPath)) {
                $minisiteLogo = $logoPath;
            }else{
                $minisiteLogo = trim(ConfService::getGlobalConf("PUBLIC_BASEURI"), "/")."/?get_action=get_global_binary_param&binary_id=". $logoPath;
            }
        }
        // Default value
        if(isSet($data["AJXP_TEMPLATE_NAME"])){
            $templateName = $data["AJXP_TEMPLATE_NAME"];
            if($templateName == "ajxp_film_strip" && UserAgent::userAgentIsMobile()){
                $templateName = "ajxp_shared_folder";
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
        if(!isSet($templateName) || isSet($error)){
            $templateName = "ajxp_unique_strip";
        }
        // UPDATE TEMPLATE
        $html = file_get_contents(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/action.share/res/minisite.php");
        Controller::applyHook("tpl.filter_html", [$context, &$html]);
        $html = XMLFilter::resolveKeywords($html);
        $html = str_replace("AJXP_PUBLIC_BASEURI", trim(ConfService::getGlobalConf("PUBLIC_BASEURI"), '/'), $html);
        $html = str_replace("AJXP_MINISITE_LOGO", $minisiteLogo, $html);
        $html = str_replace("AJXP_APPLICATION_TITLE", ConfService::getGlobalConf("APPLICATION_TITLE"), $html);
        $html = str_replace("PYDIO_APP_TITLE", ConfService::getGlobalConf("APPLICATION_TITLE"), $html);
        if(isSet($repoObject)){
            $html = str_replace("AJXP_START_REPOSITORY", $repoObject->getId(), $html);
            $html = str_replace("AJXP_REPOSITORY_LABEL", $repoObject->getDisplay(), $html);
        }
        $html = str_replace('AJXP_HASH_LOAD_ERROR', isSet($error)?$error:'', $html);
        $html = str_replace("AJXP_TEMPLATE_NAME", $templateName, $html);
        $html = str_replace("AJXP_LINK_HASH", $hash, $html);
        $guiConfigs = PluginsService::getInstance($context)->getPluginById("gui.ajax")->getConfigs();
        $html = str_replace("AJXP_THEME", $guiConfigs["GUI_THEME"] , $html);

        if (!empty($data["PRELOG_USER"])) {
            $html = str_replace("AJXP_PRELOGED_USER", "ajxp_preloged_user", $html);
        } else if(isSet($data["PRESET_LOGIN"])) {
            $html = str_replace("AJXP_PRELOGED_USER", $data["PRESET_LOGIN"], $html);
        } else{
            $html = str_replace("AJXP_PRELOGED_USER", "ajxp_legacy_minisite", $html);
        }

        if (!empty($data["AJXP_APPLICATION_BASE"])) {
            $tPath = $data["AJXP_APPLICATION_BASE"];
        } else {
            // Replace base uri by .. : /path/to/uri should give ../../..
            $defaultTPath = implode("/", array_map(function ($v) {
                return "..";
            }, explode("/", trim(ConfService::getGlobalConf("PUBLIC_BASEURI"), "/"))));
            $tPath = (!empty($data["TRAVEL_PATH_TO_ROOT"]) ? $data["TRAVEL_PATH_TO_ROOT"] : $defaultTPath);
        }

        $serverBaseUrl = ApplicationState::detectServerURL(true);

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

        HTMLWriter::internetExplorerMainDocumentHeader($responseInterface);
        $responseInterface = $responseInterface->withHeader("Content-type", "text/html; charset:UTF-8");
        $responseInterface->getBody()->write($html);
        return $responseInterface;

    }

}