<?php
/**
 * Created by PhpStorm.
 * User: charles
 * Date: 21/11/2013
 * Time: 19:54
 */

class PydioSdkGenerator
{
    public static function analyzeRegistry()
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
            "apiVersion" => AJXP_VERSION,
            "swaggerVersion" => "1.2",
            "apis" => array()
        );
        foreach($swaggerAPIs as $pluginName => $apis){

            $swaggerJson = array(
                "apiVersion" => 1.0,
                "swaggerVersion" => 1.2,
                "basePath" => "http://localhost/api",
                "resourcePath" => "/api",
                "produces" => array("application/xml"),
                "apis" => $apis
            );
            file_put_contents($swaggerJsonDir."/".$pluginName, json_encode($swaggerJson, JSON_PRETTY_PRINT));
            $p = $pServ->findPluginById($pluginName);
            $apidocs["apis"][] = array(
                "path" => "http://localhost/core/doc/api/".$pluginName,
                "description" => substr($p->getManifestDescription(), 0, 40)."..."
            );

        }
        file_put_contents($swaggerJsonDir."/api-docs", json_encode($apidocs, JSON_PRETTY_PRINT));
    }

}
