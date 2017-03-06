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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Gui;

use DOMXPath;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Pydio\Core\Http\Middleware\SecureTokenMiddleware;
use Pydio\Core\Http\Response\FileReaderResponse;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\SessionService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\Reflection\DiagnosticRunner;
use Pydio\Core\Utils\Reflection\DocsParser;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Reflection\LocaleExtractor;

use Pydio\Core\Utils\Vars\XMLFilter;
use Pydio\Core\Controller\HTMLWriter;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\PluginFramework\PluginsService;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Diactoros\Response\JsonResponse;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Class RichClient
 * Serves the GUI
 * @package Pydio\Gui
 */
class RichClient extends Plugin
{
    private static $loadedBookmarks;

    /**
     * Utilitary to pass some parameters directly at startup :
     * + repository_id / folder
     * + compile & skipDebug
     * + update_i18n, extract, create
     * + external_selector_type
     * + skipIOS
     * + gui
     * @static
     * @param ContextInterface $ctx
     * @param $parameters
     * @param $output
     * @param $session
     * @return void
     */
    public static function parseApplicationGetParameters(ContextInterface $ctx, $parameters, &$output, &$session)
    {
        $output["EXT_REP"] = "/";

        if (isSet($parameters["repository_id"]) && isSet($parameters["folder"]) || isSet($parameters["goto"])) {
            if (isSet($parameters["goto"])) {
                $explode = explode("/", ltrim($parameters["goto"], "/"));
                $repoId = array_shift($explode);
                $parameters["folder"] = str_replace($repoId, "", ltrim($parameters["goto"], "/"));
            } else {
                $repoId = $parameters["repository_id"];
            }
            $repository = RepositoryService::getRepositoryById($repoId);
            if ($repository == null) {
                $repository = RepositoryService::getRepositoryByAlias($repoId);
                if ($repository != null) {
                    $parameters["repository_id"] = $repository->getId();
                }
            } else {
                $parameters["repository_id"] = $repository->getId();
            }
            if (UsersService::usersEnabled()) {
                $loggedUser = $ctx->getUser();
                if ($loggedUser != null && $loggedUser->canSwitchTo($parameters["repository_id"])) {
                    $output["FORCE_REGISTRY_RELOAD"] = true;
                    $output["EXT_REP"] = urldecode($parameters["folder"]);
                    $loggedUser->setArrayPref("history", "last_repository", $parameters["repository_id"]);
                    $loggedUser->setPref("pending_folder", InputFilter::decodeSecureMagic($parameters["folder"]));
                    AuthService::updateSessionUser($loggedUser);
                } else {
                    $session["PENDING_REPOSITORY_ID"] = $parameters["repository_id"];
                    $session["PENDING_FOLDER"] = InputFilter::decodeSecureMagic($parameters["folder"]);
                }
            } else {
                //ConfService::switchRootDir($parameters["repository_id"]);
                $output["EXT_REP"] = urldecode($parameters["folder"]);
            }
        }


        if (isSet($parameters["skipDebug"])) {
            ConfService::setConf("JS_DEBUG", false);
        }
        if (ConfService::getConf("JS_DEBUG") && isSet($parameters["update_i18n"])) {
            if (isSet($parameters["extract"])) {
                LocaleExtractor::extractConfStringsFromManifests();
            }
            LocaleExtractor::updateAllI18nLibraries((isSet($parameters["create"]) ? $parameters["create"] : ""), (isSet($parameters["plugin"]) ? $parameters["plugin"] : ""));
        }
        if (ConfService::getConf("JS_DEBUG") && isSet($parameters["clear_plugins_cache"])) {
            @unlink(AJXP_PLUGINS_CACHE_FILE);
            @unlink(AJXP_PLUGINS_REQUIRES_FILE);
        }
        if (AJXP_SERVER_DEBUG && isSet($parameters["extract_application_hooks"])) {
            DocsParser::extractHooksToDoc();
        }

        if (isSet($parameters["external_selector_type"])) {
            $output["SELECTOR_DATA"] = array("type" => $parameters["external_selector_type"], "data" => $parameters);
        }

        if (isSet($parameters["skipIOS"])) {
            setcookie("SKIP_IOS", "true");
        }
        if (isSet($parameters["skipANDROID"])) {
            setcookie("SKIP_ANDROID", "true");
        }
        if (isSet($parameters["gui"])) {
            setcookie("AJXP_GUI", $parameters["gui"]);
            if ($parameters["gui"] == "light") $session["USE_EXISTING_TOKEN_IF_EXISTS"] = true;
        } else {
            if (isSet($session["USE_EXISTING_TOKEN_IF_EXISTS"])) {
                unset($session["USE_EXISTING_TOKEN_IF_EXISTS"]);
            }
            setcookie("AJXP_GUI", null);
        }
        if (isSet($session["OVERRIDE_GUI_START_PARAMETERS"])) {
            $output = array_merge($output, $session["OVERRIDE_GUI_START_PARAMETERS"]);
        }
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return true;
    }

    /**
     * Load the configs passed as parameter. This method will
     * + Parse the config definitions and load the default values
     * + Merge these values with the $configData parameter
     * + Publish their value in the manifest if the global_param is "exposed" to the client.
     * @param array $configData
     * @return void
     */
    public function loadConfigs($configData)
    {
        parent::loadConfigs($configData);
        $this->defineThemeConstants($this->pluginConf);
        if (!isSet($configData["CLIENT_TIMEOUT_TIME"])) {
            $this->pluginConf["CLIENT_TIMEOUT_TIME"] = intval(ini_get("session.gc_maxlifetime"));
        }
    }

    /*******************
     * ACTIONS METHODS
     *******************/
    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     */
    public function getBootConf(ServerRequestInterface &$request, ResponseInterface &$response){

        /** @var ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");
        $out = array();
        self::parseApplicationGetParameters($ctx, $request->getQueryParams(), $out, $_SESSION);
        $config = $this->computeBootConf($ctx);
        $response = new JsonResponse($config);

    }
        /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     */
    public function getBootGui(ServerRequestInterface &$request, ResponseInterface &$response){

        $this->defineThemeConstants($this->pluginConf);
        $mess = LocaleService::getMessages();
        /** @var ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");

        $httpVars = $request->getParsedBody();
        HTMLWriter::internetExplorerMainDocumentHeader($response);

        if (!is_file(TESTS_RESULT_FILE) && !is_file(TESTS_RESULT_FILE_LEGACY)) {
            $outputArray = array();
            $testedParams = array();
            $passed = DiagnosticRunner::runTests($outputArray, $testedParams);
            if (!$passed && !isset($httpVars["ignore_tests"])) {
                $html = DiagnosticRunner::testResultsToTable($outputArray, $testedParams);
                $response = new HtmlResponse($html);
                return;
            } else {
                DiagnosticRunner::testResultsToFile($outputArray, $testedParams);
            }
        }

        $root = parse_url($request->getServerParams()['REQUEST_URI'], PHP_URL_PATH);
        $configUrl = ConfService::getGlobalConf("SERVER_URL");
        if(!empty($configUrl)){
            $root = '/'.ltrim(parse_url($configUrl, PHP_URL_PATH), '/');
            if(strlen($root) > 1) $root = rtrim($root, '/').'/';
        }else{
            preg_match ('/ws-(.)*\/|settings|dashboard|welcome|user/', $root, $matches, PREG_OFFSET_CAPTURE);
            if(count($matches)){
                $capture = $matches[0][1];
                $root = substr($root, 0, $capture);
            }
        }
        $START_PARAMETERS = array(
            "BOOTER_URL"        =>"index.php?get_action=get_boot_conf",
            "MAIN_ELEMENT"      => "ajxp_desktop",
            "APPLICATION_ROOT"  => $root,
            "REBASE"            => $root
        );
        if($request->getAttribute("flash") !== null){
            $START_PARAMETERS["ALERT"] = $request->getAttribute("flash");
        }

        self::parseApplicationGetParameters($ctx, $request->getQueryParams(), $START_PARAMETERS, $_SESSION);

        $confErrors = ConfService::getErrors();
        if (count($confErrors)) {
            $START_PARAMETERS["ALERT"] = implode(", ", array_values($confErrors));
        }
        // PRECOMPUTE BOOT CONF
        $userAgent = $request->getServerParams()['HTTP_USER_AGENT'];
        if (!preg_match('/MSIE 7/',$userAgent) && !preg_match('/MSIE 8/',$userAgent)) {
            $preloadedBootConf = $this->computeBootConf($ctx);
            Controller::applyHook("loader.filter_boot_conf", array($ctx, &$preloadedBootConf));
            $START_PARAMETERS["PRELOADED_BOOT_CONF"] = $preloadedBootConf;
        }

        // PRECOMPUTE REGISTRY
        if (!isSet($START_PARAMETERS["FORCE_REGISTRY_RELOAD"])) {
            $clone = PluginsService::getInstance($ctx)->getFilteredXMLRegistry(true, true);
            if(!AJXP_SERVER_DEBUG){
                $clonePath = new DOMXPath($clone);
                $serverCallbacks = $clonePath->query("//serverCallback|hooks");
                foreach ($serverCallbacks as $callback) {
                    $callback->parentNode->removeChild($callback);
                }
            }
            $START_PARAMETERS["PRELOADED_REGISTRY"] = XMLFilter::resolveKeywords($clone->saveXML());
        }

        $JSON_START_PARAMETERS = json_encode($START_PARAMETERS);
        $crtTheme = $this->pluginConf["GUI_THEME"];
        $additionalFrameworks = $this->getContextualOption($ctx, "JS_RESOURCES_BEFORE");
        $ADDITIONAL_FRAMEWORKS = "";
        if( !empty($additionalFrameworks) ){
            $frameworkList = explode(",", $additionalFrameworks);
            foreach($frameworkList as $index => $framework){
                $frameworkList[$index] = '<script language="javascript" type="text/javascript" src="'.$framework.'"></script>'."\n";
            }
            $ADDITIONAL_FRAMEWORKS = implode("", $frameworkList);
        }
        if (ConfService::getConf("JS_DEBUG")) {
            if (!isSet($mess)) {
                $mess = LocaleService::getMessages();
            }
            if (is_file(AJXP_INSTALL_PATH."/plugins/gui.ajax/res/themes/$crtTheme/html/gui_debug.html")) {
                include(AJXP_INSTALL_PATH."/plugins/gui.ajax/res/themes/$crtTheme/html/gui_debug.html");
            } else {
                include(AJXP_INSTALL_PATH."/plugins/gui.ajax/res/html/gui_debug.html");
            }
        } else {
            if (is_file(AJXP_INSTALL_PATH."/plugins/gui.ajax/res/themes/$crtTheme/html/gui.html")) {
                $content = file_get_contents(AJXP_INSTALL_PATH."/plugins/gui.ajax/res/themes/$crtTheme/html/gui.html");
            } else {
                $content = file_get_contents(AJXP_INSTALL_PATH."/plugins/gui.ajax/res/html/gui.html");
            }
            if (preg_match('/MSIE 7/',$userAgent)){
                $ADDITIONAL_FRAMEWORKS = "";
            }
            $content = str_replace("AJXP_ADDITIONAL_JS_FRAMEWORKS", $ADDITIONAL_FRAMEWORKS, $content);
            $content = XMLFilter::resolveKeywords($content, false);
            $content = str_replace("AJXP_REBASE", isSet($START_PARAMETERS["REBASE"])?'<base href="'.$START_PARAMETERS["REBASE"].'"/>':"", $content);
            if ($JSON_START_PARAMETERS) {
                $content = str_replace("//AJXP_JSON_START_PARAMETERS", "startParameters = ".$JSON_START_PARAMETERS.";", $content);
            }
            Controller::applyHook("tpl.filter_html", [$ctx, &$content]);
            $response->getBody()->write($content);
        }

    }
    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @return bool
     */
    public function switchAction(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface)
    {
        $this->defineThemeConstants($this->pluginConf);

        switch ($requestInterface->getAttribute("action")) {

            case "serve_favicon":

                $image = AJXP_THEME_FOLDER."/images/html-folder.png";
                $reader = new FileReaderResponse($image);
                $reader->setHeaderType("image");
                $responseInterface = $responseInterface->withBody($reader);

                break;

            //------------------------------------
            //	GET I18N MESSAGES
            //------------------------------------
            case "get_i18n_messages":

                $refresh = false;
                $httpVars = $requestInterface->getParsedBody();
                if (isSet($httpVars["lang"])) {
                    LocaleService::setLanguage($httpVars["lang"]);
                    $refresh = true;
                }
                $responseInterface = new JsonResponse(LocaleService::getMessages($refresh));

            break;

            //------------------------------------
            //	DISPLAY DOC
            //------------------------------------
            case "display_doc":

                $responseInterface = $responseInterface->withHeader("Content-type", "text/plain;charset=UTF-8");
                $docPath = InputFilter::securePath(htmlentities($requestInterface->getParsedBody()["doc_file"]));
                $responseInterface->getBody()->write(HTMLWriter::getDocFile($docPath));

            break;


            default;
            break;
        }

        return false;
    }

    /************************
     * HOOKS FOR BOOKMARKS
     ************************/
    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $ajxpNode
     * @return void
     */
    public function nodeBookmarkMetadata(&$ajxpNode)
    {
        $user = $ajxpNode->getContext()->getUser();
        if(empty($user)) return;
        $metadata = $ajxpNode->retrieveMetadata("ajxp_bookmarked", true, AJXP_METADATA_SCOPE_REPOSITORY, true);
        if (is_array($metadata) && count($metadata)) {
            $ajxpNode->mergeMetadata(array(
                     "ajxp_bookmarked" => "true",
                     "overlay_icon"  => "bookmark.png",
                     "overlay_class" => "icon-bookmark-empty"
                ), true);
            return;
        }
        if (!isSet(self::$loadedBookmarks)) {
            self::$loadedBookmarks = $user->getBookmarks($ajxpNode->getRepositoryId());
        }
        foreach (self::$loadedBookmarks as $bm) {
            if ($bm["PATH"] == $ajxpNode->getPath()) {
                $ajxpNode->mergeMetadata(array(
                         "ajxp_bookmarked" => "true",
                         "overlay_icon"  => "bookmark.png",
                        "overlay_class" => "icon-bookmark-empty"
                    ), true);
                $ajxpNode->setMetadata("ajxp_bookmarked", array("ajxp_bookmarked"=> "true"), true, AJXP_METADATA_SCOPE_REPOSITORY, true);
            }
        }
    }
    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $fromNode
     * @param \Pydio\Access\Core\Model\AJXP_Node $toNode
     * @param bool $copy
     */
    public function nodeChangeBookmarkMetadata($fromNode=null, $toNode=null, $copy=false){
        if($copy || $fromNode == null) return;
        $user = $fromNode->getContext()->getUser();
        if($user == null) return;
        if (!isSet(self::$loadedBookmarks)) {
            self::$loadedBookmarks = $user->getBookmarks($fromNode->getRepositoryId());
        }
        if($toNode == null) {
            $fromNode->removeMetadata("ajxp_bookmarked", true, AJXP_METADATA_SCOPE_REPOSITORY, true);
        } else {
            $toNode->copyOrMoveMetadataFromNode($fromNode, "ajxp_bookmarked", "move", true, AJXP_METADATA_SCOPE_REPOSITORY, true);
        }
        Controller::applyHook("msg.instant", array($fromNode->getContext(), "<reload_bookmarks/>", $user->getId()));
    }


    /************************
     * XML.FILTER INCLUDE HOOK
     ************************/
    public static function filterXml(&$value)
    {
        /**
         * @var RichClient
         */
        $instance = PluginsService::getInstance(Context::emptyContext())->getPluginByTypeName("gui", "ajax");
        if($instance === false) return null;
        $confs = $instance->getConfigs();
        $instance->defineThemeConstants($confs);
        $value = str_replace(array("AJXP_CLIENT_RESOURCES_FOLDER", "AJXP_CURRENT_VERSION"), array(CLIENT_RESOURCES_FOLDER, AJXP_VERSION), $value);
        if (SessionService::has('PYDIO-SERVER-URI-PREFIX')) {
            $value = str_replace("AJXP_THEME_FOLDER", SessionService::fetch('PYDIO-SERVER-URI-PREFIX').AJXP_THEME_FOLDER, $value);
            $value = str_replace("AJXP_IMAGES_FOLDER", SessionService::fetch('PYDIO-SERVER-URI-PREFIX').AJXP_IMAGES_FOLDER, $value);
        } else {
            $value = str_replace("AJXP_THEME_FOLDER", AJXP_THEME_FOLDER, $value);
            $value = str_replace("AJXP_IMAGES_FOLDER", AJXP_IMAGES_FOLDER, $value);
        }
        return $value;
    }

    /************************
     * PRIVATE FUNCTIONS
     ************************/
    /**
     * @param array $configs
     */
    private function defineThemeConstants($configs){
        if (defined("AJXP_THEME_FOLDER")) return;
        $theme = $configs["GUI_THEME"];
        define("CLIENT_RESOURCES_FOLDER", AJXP_PLUGINS_FOLDER."/gui.ajax/res");
        define("AJXP_THEME_FOLDER", CLIENT_RESOURCES_FOLDER."/themes/".$theme);
        $imagesFolder = AJXP_THEME_FOLDER."/images";
        if(file_exists(AJXP_INSTALL_PATH . "." . $imagesFolder)){
            define("AJXP_IMAGES_FOLDER", $imagesFolder);
        }else{
            define("AJXP_IMAGES_FOLDER", CLIENT_RESOURCES_FOLDER."/themes/common/images");
        }
    }

    /**
     * @param ContextInterface $ctx
     * @return array
     */
    private function computeBootConf(ContextInterface $ctx)
    {
        if (isSet($_GET["server_prefix_uri"])) {
            SessionService::save('PYDIO-SERVER-URI-PREFIX', str_replace("_UP_", "..", $_GET["server_prefix_uri"]));
        }
        $currentIsMinisite = (strpos(session_name(), "AjaXplorer_Shared") === 0);
        $config = array();
        $config["ajxpResourcesFolder"] = "plugins/gui.ajax/res";
        if ($currentIsMinisite) {
            $config["ajxpServerAccess"] = "public/";
        } else {
            $config["ajxpServerAccess"] = AJXP_SERVER_ACCESS;
        }
        $config["zipEnabled"] = ConfService::zipBrowsingEnabled();
        $config["multipleFilesDownloadEnabled"] = ConfService::zipCreationEnabled();
        $customIcon = $this->getContextualOption($ctx, "CUSTOM_ICON");
        self::filterXml($customIcon);
        $config["customWording"] = array(
            "welcomeMessage" => $this->getContextualOption($ctx, "CUSTOM_WELCOME_MESSAGE"),
            "title"			 => ConfService::getGlobalConf("APPLICATION_TITLE"),
            "icon"			 => $customIcon,
            "iconWidth"		 => $this->getContextualOption($ctx, "CUSTOM_ICON_WIDTH"),
            "iconHeight"     => $this->getContextualOption($ctx, "CUSTOM_ICON_HEIGHT"),
            "iconOnly"       => $this->getContextualOption($ctx, "CUSTOM_ICON_ONLY"),
            "titleFontSize"	 => $this->getContextualOption($ctx, "CUSTOM_FONT_SIZE")
        );
        $cIcBin = $this->getContextualOption($ctx, "CUSTOM_ICON_BINARY");
        if (!empty($cIcBin)) {
            $config["customWording"]["icon_binary_url"] = "get_action=get_global_binary_param&binary_id=".$cIcBin;
        }
        $config["usersEnabled"] = UsersService::usersEnabled();
        $config["loggedUser"] = ($ctx->hasUser());
        $config["currentLanguage"] = LocaleService::getLanguage();
        $config["session_timeout"] = intval(ini_get("session.gc_maxlifetime"));
        $timeoutTime = $this->getContextualOption($ctx, "CLIENT_TIMEOUT_TIME");
        if (empty($timeoutTime)) {
            $to = $config["session_timeout"];
        } else {
            $to = $timeoutTime;
        }
        if($currentIsMinisite) $to = -1;
        $config["client_timeout"] = intval($to);
        $config["client_timeout_warning"] = floatval($this->getContextualOption($ctx, "CLIENT_TIMEOUT_WARN"));
        $config["availableLanguages"] = LocaleService::listAvailableLanguages();
        $config["usersEditable"] = ConfService::getAuthDriverImpl()->usersEditable();
        $config["ajxpVersion"] = AJXP_VERSION;
        $config["ajxpVersionDate"] = AJXP_VERSION_DATE;
        $analytic = $this->getContextualOption($ctx, 'GOOGLE_ANALYTICS_ID');
        if (!empty($analytic)) {
            $config["googleAnalyticsData"] = array(
                "id"=> 		$analytic,
                "domain" => $this->getContextualOption($ctx, 'GOOGLE_ANALYTICS_DOMAIN'),
                "event" => 	$this->getContextualOption($ctx, 'GOOGLE_ANALYTICS_EVENT')
            );
        }
        $config["i18nMessages"] = LocaleService::getMessages();
        $config["SECURE_TOKEN"] = SecureTokenMiddleware::generateSecureToken();
        $config["streaming_supported"] = "true";
        $config["theme"] = $this->pluginConf["GUI_THEME"];
        $themeImages = AJXP_INSTALL_PATH.'/plugins/gui.ajax/res/themes/'.$config["theme"].'/images';
        if(!file_exists($themeImages)) {
            $config["ajxpImagesCommon"] = true;
        }
        return $config;
    }

}

Controller::registerIncludeHook("xml.filter", array("Pydio\\Gui\\RichClient", "filterXml"));
