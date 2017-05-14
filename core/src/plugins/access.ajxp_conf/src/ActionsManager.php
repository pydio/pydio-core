<?php
/*
 * Copyright 2007-2017 Abstrium <contact (at) pydio.com>
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
 * The latest code can be found at <https://pydio.com/>.
 */
namespace Pydio\Access\Driver\DataProvider\Provisioning;

use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\NodesList;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\LocaleService;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class ActionsManager
 * @package Pydio\Access\Driver\DataProvider\Provisioning
 */
class ActionsManager extends AbstractManager
{

    /**
     * @param ServerRequestInterface $requestInterface Full set of query parameters
     * @param string $rootPath Path to prepend to the resulting nodes
     * @param string $relativePath Specific path part for this function
     * @param string $paginationHash Number added to url#2 for pagination purpose.
     * @param string $findNodePosition Path to a given node to try to find it
     * @param string $aliasedDir Aliased path used for alternative url
     * @return NodesList A populated NodesList object, eventually recursive.
     * @throws \Pydio\Core\Exception\PydioException
     */
    public function listNodes(ServerRequestInterface $requestInterface, $rootPath, $relativePath, $paginationHash = null, $findNodePosition = null, $aliasedDir = null)
    {
        $nodesList  = new NodesList("/$rootPath/$relativePath");
        $parts      = explode("/",$relativePath);
        $pServ      = PluginsService::getInstance($this->context);
        $types      = $pServ->getDetectedPlugins();

        if (count($parts) == 1) {

            $nodesList->initColumnsData("filelist", "list", "ajxp_conf.actions_list");
            $nodesList->appendColumn("ajxp_conf.101", "ajxp_label");
            ksort($types);
            foreach ($types as $t => $tPlugs) {
                if(!empty($findNodePosition) && $t !== $findNodePosition) continue;

                $meta = array(
                    "icon" 		=> "folder_development.png",
                    "plugin_id" => $t,
                    "text"      => $t,
                    "is_file"   => false
                );
                $nodeKey = "/$rootPath/actions/".$t;
                $this->appendBookmarkMeta($nodeKey, $meta);
                $nodesList->addBranch(new AJXP_Node($nodeKey, $meta));
            }

        } else if (count($parts) == 2) {
            // list plugs
            $type = $parts[1];
            $nodesList->initColumnsData("filelist", "detail", "ajxp_conf.actions_list_plug");
            $nodesList->appendColumn("ajxp_conf.101", "ajxp_label");
            $nodesList->appendColumn("ajxp_conf.103", "actions");
            /** @var Plugin $pObject */
            foreach ($types[$type] as $pObject) {
                if(!empty($findNodePosition) && $pObject->getName() !== $findNodePosition) continue;

                $actions = $pObject->getManifestRawContent("//action/@name", "xml", true);
                $actLabel = array();
                if ($actions->length) {
                    foreach ($actions as $node) {
                        $actLabel[] = $node->nodeValue;
                    }
                }
                $meta = array(
                    "icon" 		=> "preferences_plugin.png",
                    "text"      => $pObject->getManifestLabel(),
                    "plugin_id" => $pObject->getId(),
                    "is_file"   => false,
                    "actions"   => implode(", ", $actLabel)
                );
                $nodeKey = "/$rootPath/actions/$type/".$pObject->getName();
                $this->appendBookmarkMeta($nodeKey, $meta);
                $nodesList->addBranch(new AJXP_Node($nodeKey, $meta));
            }

        } else if (count($parts) == 3) {
            // list actions
            $type = $parts[1];
            $name = $parts[2];
            $mess = LocaleService::getMessages();

            $nodesList->initColumnsData("full", "full", "ajxp_conf.actions_list_plugs");
            $nodesList->appendColumn("ajxp_conf.101", "ajxp_label", "String", "10%");
            $nodesList->appendColumn("ajxp_conf.103", "parameters", "String", "30%");
            $nodesList->appendColumn("Rest API", "rest_params", "String", "30%");

            /** @var Plugin $pObject */
            $pObject = $types[$type][$name];

            $actions = $pObject->getManifestRawContent("//action", "xml", true);
            $allNodesAcc = array();
            if ($actions->length) {
                foreach ($actions as $node) {
                    $xPath = new \DOMXPath($node->ownerDocument);
                    $callbacks = $xPath->query("processing/serverCallback", $node);
                    if(!$callbacks->length) continue;
                    /** @var \DOMElement $callback */
                    $callback = $callbacks->item(0);

                    $actName = $actLabel = $node->attributes->getNamedItem("name")->nodeValue;
                    if(!empty($findNodePosition) && $actName !== $findNodePosition) continue;

                    $text = $xPath->query("gui/@text", $node);
                    if ($text->length) {
                        $actLabel = $actName ." (" . $mess[$text->item(0)->nodeValue].")";
                    }
                    $params = $xPath->query("processing/serverCallback/input_param", $node);
                    $paramLabel = array();
                    if ($callback->getAttribute("developerComment") != "") {
                        $paramLabel[] = "<span class='developerComment'>".$callback->getAttribute("developerComment")."</span>";
                    }
                    $restPath = "";
                    if ($callback->getAttribute("restParams")) {
                        $restPath = "/api/$actName/". ltrim($callback->getAttribute("restParams"), "/");
                    }
                    if ($restPath != null) {
                        $paramLabel[] = "<span class='developerApiAccess'>"."API Access : ".$restPath."</span>";
                    }
                    if ($params->length) {
                        $paramLabel[] = "Expected Parameters :";
                        /** @var \DOMElement $param */
                        foreach ($params as $param) {
                            $paramLabel[]= '. ['.$param->getAttribute("type").'] <b>'.$param->getAttribute("name").($param->getAttribute("mandatory") == "true" ? '*':'').'</b> : '.$param->getAttribute("description");
                        }
                    }
                    $meta = array(
                        "icon" 		    => "preferences_plugin.png",
                        "text"          => $actLabel,
                        "action_id"     => $actName,
                        "parameters"    => '<div class="developerDoc">'.implode("<br/>", $paramLabel).'</div>',
                        "rest_params"   => $restPath
                    );
                    $nodeKey = "/$rootPath/actions/$type/".$pObject->getName()."/$actName";
                    $this->appendBookmarkMeta($nodeKey, $meta);
                    $node = new AJXP_Node($nodeKey, $meta);
                    $allNodesAcc[$actName] = $node;
                }
                ksort($allNodesAcc);
                foreach($allNodesAcc as $path => $node){
                    $nodesList->addBranch($node);
                }
            }

        }
        return $nodesList;

    }
}