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
 * The latest code can be found at <https://pyd.io/>.
 *
 * Generate a Swagger file for Rest APIs documentation
 */
class PydioSdkGenerator
{
    public static function analyzeRegistry($versionString)
    {
        if(!AJXP_SERVER_DEBUG) return;

        $pServ = AJXP_PluginsService::getInstance();
        $nodes = $pServ->searchAllManifests('//actions/*/processing/serverCallback[@developerComment]', 'node', false, false, true);
        $jsFile = AJXP_DATA_PATH."/public/sdkMethods.js";
        $swaggerJsonDir = AJXP_INSTALL_PATH."/core/doc/api";
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
            if(in_array($actionName, $alreadyParsed)){
                continue;
            }
            $alreadyParsed[] = $actionName;
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
            $prefix = "/default";
            if($pluginName == "access.ajxp_conf"){
                $prefix = "/ajxp_conf";
            }
            $api = array(
                "path"  => $prefix."/".$actionName . (empty($restParams) ? "" : $restParams),
                "operations" => array(
                    array(
                        "method"        => empty($http) ? "POST" : strtoupper($http),
                        "summary"       => substr($comment, 0, 40) . (strlen($comment) > 40 ? "..." : ""),
                        "notes"         => $comment."<br>Sdk name: ". $methodName."()",
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
        foreach($swaggerAPIs as $pluginName => $apis){

            var_dump("Writing file for $pluginName");

            $swaggerJson = array(
                "apiVersion" => $versionString,
                "swaggerVersion" => 1.2,
                "basePath" => "https://pyd.io/resources/serverapi/$versionString/api",
                "resourcePath" => "/api",
                "produces" => array("application/xml"),
                "apis" => $apis
            );
            file_put_contents($swaggerJsonDir."/".$pluginName, json_encode($swaggerJson, JSON_PRETTY_PRINT));
            $p = $pServ->findPluginById($pluginName);
            $apidocs["apis"][] = array(
                "path" => "https://pyd.io/resources/serverapi/$versionString/api/".$pluginName,
                "description" => substr($p->getManifestDescription(), 0, 40)."..."
            );

        }
        file_put_contents($swaggerJsonDir."/api-docs", json_encode($apidocs, JSON_PRETTY_PRINT));
    }

}
