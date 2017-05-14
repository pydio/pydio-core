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
 * The latest code can be found at <https://pyd.io/>.
 *
 *
 */

namespace Pydio\Core\Utils\Reflection;

//define("JSON_DIR", AJXP_INSTALL_PATH."/core/doc/api");
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\PluginFramework\PluginsService;

define("JSON_DIR", AJXP_INSTALL_PATH."/../api");
define("JSON_URL", "https://pydio.com/static-docs/api");
define("API_DOC_PAGE", "https://pydio.com/en/docs/references/pydio-api#!/");

/**
 * Class PydioSdkGenerator
 * Generate a Swagger file for Rest APIs v1 documentation
 * @package Pydio\Core\Utils\Reflection
 */
class PydioSdkGenerator
{
    static $apiGroups = [
        "fs" => ["access.fs", "index.*", "meta.*", "editor.*", "action.share", "action.powerfs"],
        "conf" => ["access.ajxp_conf", "action.scheduler", "action.updater"],
        "lifecycle" => ["conf.*", "auth.*", "gui.*", "core.*", "action.avatar"],
        "nonfs" => ["access.*"],
        "misc" => ["*"]
    ];

    static $apiGroupsLabels = [
        "fs" => "Most current operations on files and folders, their metadata, and additional sharing features.",
        "conf" => "Administration task : users/groups/workspaces provisionning, maintenance tasks, etc... Generally performed using /settings/ as workspace alias.",
        "lifecycle" => "Application objects lifecycle, like current user access rights and preferences, authentication utils, etc. As they are generally not linked to a specific workspace, these actions can be performed using /pydio/ instead of a workspace alias.",
        "nonfs" => "Non-standard drivers accessing to structured data like IMAP, MySQL, Apis, etc.",
        "misc" => "Other plugins actions."
    ];

    /**
     * @param $pluginId
     * @return int|string
     */
    private static function findApiGroupForPlugin($pluginId){
        list($pType, $pName) = explode(".", $pluginId);
        foreach(self::$apiGroups as $groupName => $pluginPatterns){
            foreach($pluginPatterns as $pattern){
                if($pattern == "*" || $pattern == "$pType.*" || $pattern == "$pType.$pName"){
                    return $groupName;
                }
            }
        }
        return "misc";
    }

    /**
     * @param string $versionString
     */
    public static function analyzeRegistry($versionString)
    {
        if(!AJXP_SERVER_DEBUG) {
            echo "Please switch the server to debug mode to use this API.";
            return;
        }

        $pServ = PluginsService::getInstance();
        $nodes = $pServ->searchAllManifests('//actions/*/processing/serverCallback[@developerComment]', 'node', false, false, true);
        $jsFile = AJXP_DATA_PATH."/public/sdkMethods.js";
        $swaggerJsonDir = JSON_DIR."/".$versionString;
        $swaggerAPIs = array();
        $methods = array();
        $alreadyParsed = array();
        foreach ($nodes as $callbackNode) {
            $params = array();
            $swaggerParams = array();
            $pluginName = $callbackNode->parentNode->parentNode->parentNode->parentNode->parentNode->getAttribute("id");
            $actionName = $callbackNode->parentNode->parentNode->getAttribute("name");
            $methodName = $callbackNode->getAttribute("sdkMethodName");
            if(empty($methodName)){
                $methodName = $actionName;
            }
            $outputType = 'xml';
            /*
            if(in_array($actionName, $alreadyParsed)){
                continue;
            }
            $alreadyParsed[] = $actionName;
            */
            if(!isset($swaggerAPIs[$pluginName])) $swaggerAPIs[$pluginName] = array();

            foreach ($callbackNode->childNodes as $child) {
                if($child->nodeType != XML_ELEMENT_NODE)continue;
                if ($child->nodeName == "input_param") {
                    $params[$child->getAttribute("name")] = array(
                        "name" => $child->getAttribute("name"),
                        "type" => $child->getAttribute("type"),
                        "mandatory" => $child->getAttribute("mandatory") === "true",
                        "default" => $child->getAttribute("default"),
                    );
                    $default = $child->getAttribute("default");
                    $swaggerParams[] = array(
                        "name" => $child->getAttribute("name"),
                        "description" => $child->getAttribute("description"). "<br>".(!empty($default) ? "Default: $default" : ""),
                        "required" => ($child->getAttribute("mandatory") === "true"),
                        "allowMultiple" => (strpos($child->getAttribute("type"), "[]") !== false),
                        "dataType" => (strpos($child->getAttribute("type"), "[]") !== false) ? "array":$child->getAttribute("type"),
                        "paramType" => "query"
                    );

                } else if ($child->nodeName=="output") {
                    $outputType = $child->getAttribute("type");
                }
            }
            $methods[$methodName] = array(
                "action" => $actionName,
                "params" => $params,
                "output" => $outputType
            );
            $comment = $callbackNode->getAttribute("developerComment");
            $http = $callbackNode->getAttribute("preferredHttp");
            $restParams = $callbackNode->getAttribute("restParams");
            $prefix = "/workspace_alias";
            $apiGroup = self::findApiGroupForPlugin($pluginName);
            if($apiGroup == "conf"){
                $prefix = "/settings";
            }else if($apiGroup == "lifecycle"){
                $prefix = "/pydio";
            }
            $api = array(
                "path"  => $prefix."/".$actionName . (empty($restParams) ? "" : $restParams),
                "operations" => array(
                    array(
                        "method"        => empty($http) ? "POST" : strtoupper($http),
                        "summary"       => substr($comment, 0, 80) . (strlen($comment) > 80 ? "..." : ""),
                        "notes"         => $comment,
                        "responseClass" => $outputType,
                        "nickname"      => $methodName,
                        "parameters"    => $swaggerParams
                    )
                )
            );
            $swaggerAPIs[$pluginName][] = $api;
        }

        file_put_contents($jsFile, "window.sdkMethods = ".json_encode($methods, JSON_PRETTY_PRINT));

        $apidocs = array(
            "apiVersion" => $versionString,
            "swaggerVersion" => "1.2",
            "apis" => array()
        );
        $allDocs = array();
        $markdowns = array();

        foreach($swaggerAPIs as $pluginName => $apis){

            echo("Writing file for $pluginName");

            $swaggerJson = array(
                "apiVersion" => $versionString,
                "swaggerVersion" => 1.2,
                "basePath" => JSON_URL."/$versionString",
                "resourcePath" => "/api",
                "produces" => array("application/xml"),
                "apis" => $apis
            );
            file_put_contents($swaggerJsonDir."/".$pluginName, json_encode($swaggerJson, JSON_PRETTY_PRINT));
            $p = $pServ->getPluginById($pluginName);
            $apiGroup = self::findApiGroupForPlugin($pluginName);
            if(!isset($allDocs[$apiGroup])) {
                $allDocs[$apiGroup] = array();
                $markdowns[$apiGroup] = array();
            }
            $markdowns[$apiGroup][] = self::makeMarkdown($p, $apis);
            $allDocs[$apiGroup][] = array(
                "path" => JSON_URL."/$versionString/".$pluginName,
                "description" => $p->getManifestDescription()
            );
            $apidocs["apis"][] = array(
                "path" => JSON_URL."/$versionString/".$pluginName,
                "description" => $p->getManifestDescription()
            );

        }
        foreach($allDocs as $apiGroupName => $groupApis){
            $groupApiDocs = array(
                "apiVersion" => $versionString,
                "swaggerVersion" => "1.2",
                "apis" => $groupApis
            );
            file_put_contents($swaggerJsonDir."/api-docs-".$apiGroupName, json_encode($groupApiDocs, JSON_PRETTY_PRINT));
            file_put_contents($swaggerJsonDir."/api-md-".$apiGroupName, self::$apiGroupsLabels[$apiGroupName]."\n\n".implode("", $markdowns[$apiGroupName]));
        }
        // Store file with all apis.
        file_put_contents($swaggerJsonDir."/api-docs", json_encode($apidocs, JSON_PRETTY_PRINT));
    }

    /**
     * @param Plugin $plugin
     * @param array $apis
     * @return string
     */
    private static function makeMarkdown($plugin, $apis){

        $md = "\n\n";
        $md .= "## ".$plugin->getManifestLabel()."  ";
        $md .= "\n".$plugin->getManifestDescription()."\n\n";
        $id = $plugin->getId();
        foreach($apis as $index => $api) {
            $md .= "\n";
            $md .= "- **".$api["path"]."**  \n";
            $md .= "  ".$api["operations"][0]["notes"]."  \n";
            $md .= "  [Details](".API_DOC_PAGE."".$id."/".$api["operations"][0]["nickname"]."_".strtolower($api["operations"][0]["method"])."_".$index.")";
        }

        return $md;

    }

}
