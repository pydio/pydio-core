<?php
/*
 * Copyright 2007-2016 Abstrium <contact (at) pydio.com>
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

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\NodesList;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\LocaleService;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class PluginsManager
 * @package Pydio\Access\Driver\DataProvider\Provisioning
 */
class PluginsManager extends AbstractManager
{

    /**
     * @param array $httpVars Full set of query parameters
     * @param string $rootPath Path to prepend to the resulting nodes
     * @param string $relativePath Specific path part for this function
     * @param string $paginationHash Number added to url#2 for pagination purpose.
     * @param string $findNodePosition Path to a given node to try to find it
     * @param string $aliasedDir Aliased path used for alternative url
     *
     * @return NodesList A populated NodesList object, eventually recursive.
     */
    public function listNodes($httpVars, $rootPath, $relativePath, $paginationHash = null, $findNodePosition = null, $aliasedDir = null)
    {

        $relativePath   = "/$relativePath";
        if($aliasedDir != null && $aliasedDir != "/".$rootPath.$relativePath){
            $baseDir = $aliasedDir;
        }else{
            $baseDir = "/".$rootPath.$relativePath;
        }
        $nodesList      = new NodesList($baseDir);
        $pServ          = PluginsService::getInstance($this->context);
        $types          = $pServ->getDetectedPlugins();
        $mess           = LocaleService::getMessages();
        $uniqTypes      = array("core");
        $coreTypes      = array("auth", "conf", "boot", "feed", "log", "mailer", "mq");

        if ($relativePath == "/plugins" || $relativePath == "/core_plugins" || $relativePath=="/all") {

            if($relativePath == "/core_plugins") $uniqTypes = $coreTypes;
            else if($relativePath == "/plugins") $uniqTypes = array_diff(array_keys($types), $coreTypes);
            else if($relativePath == "/all") $uniqTypes = array_keys($types);
            $nodesList->initColumnsData("filelist", "detail", "ajxp_conf.plugins_folder");
            $nodesList->appendColumn("ajxp_conf.101", "ajxp_label");
            $nodesList->appendColumn("ajxp_conf.103", "plugin_description");
            $nodesList->appendColumn("ajxp_conf.102", "plugin_id");
            ksort($types);

            foreach ($types as $t => $tPlugs) {
                if(!empty($findNodePosition) && $t !== $findNodePosition) continue;
                if(!in_array($t, $uniqTypes))continue;
                if($t == "core") continue;
                $nodeKey = $baseDir."/".$t;
                $meta = array(
                    "icon" 		         => "folder_development.png",
                    "plugin_id"          => $t,
                    "text"               => $mess["plugtype.title.".$t],
                    "plugin_description" => $mess["plugtype.desc.".$t],
                    "is_file"            => false
                );
                $this->appendBookmarkMeta($nodeKey, $meta);
                $nodesList->addBranch(new AJXP_Node($nodeKey, $meta));
            }

        } else if ($relativePath == "/core") {

            $nodesList->initColumnsData("filelist", "detail", "ajxp_conf.plugins");
            $nodesList->appendColumn("ajxp_conf.101", "ajxp_label");
            $nodesList->appendColumn("ajxp_conf.102", "plugin_id");
            $nodesList->appendColumn("ajxp_conf.103", "plugin_description");

            $all = []; $first = null;
            foreach ($uniqTypes as $type) {
                if(!isset($types[$type])) continue;
                /** @var Plugin $pObject */
                foreach ($types[$type] as $pObject) {
                    if(!empty($findNodePosition) && $pObject->getId() !== $findNodePosition) continue;

                    $isMain = ($pObject->getId() == "core.ajaxplorer");
                    $meta = array(
                        "icon" 		         => ($isMain?"preferences_desktop.png":"desktop.png"),
                        "ajxp_mime"          => "ajxp_plugin",
                        "plugin_id"          => $pObject->getId(),
                        "plugin_description" => $pObject->getManifestDescription(),
                        "text"               => $pObject->getManifestLabel()
                    );
                    // Check if there are actually any parameters to display!
                    if($pObject->getManifestRawContent("server_settings", "xml")->length == 0) {
                        continue;
                    }
                    $nodeKey = $baseDir."/".$pObject->getId();
                    $this->appendBookmarkMeta($nodeKey, $meta);
                    $plugNode = new AJXP_Node($nodeKey, $meta);
                    if ($isMain) {
                        $first = $plugNode;
                    } else {
                        $all[] = $plugNode;
                    }
                }
            }

            if($first !== null) $nodesList->addBranch($first);
            foreach($all as $node) $nodesList->addBranch($node);

        } else {
            $split = explode("/", $relativePath);
            if(empty($split[0])) array_shift($split);
            $type = $split[1];

            $nodesList->initColumnsData("filelist", "full", "ajxp_conf.plugin_detail");
            $nodesList->appendColumn("ajxp_conf.101", "ajxp_label", "String", "10%");
            $nodesList->appendColumn("ajxp_conf.102", "plugin_id", "String", "10%");
            $nodesList->appendColumn("ajxp_conf.103", "plugin_description", "String", "60%");
            $nodesList->appendColumn("ajxp_conf.104", "enabled", "String", "10%");
            $nodesList->appendColumn("ajxp_conf.105", "can_active", "String", "10%");

            $mess = LocaleService::getMessages();
            /** @var Plugin $pObject */
            foreach ($types[$type] as $pObject) {
                if(!empty($findNodePosition) && $pObject->getId() !== $findNodePosition) continue;
                $errors = "OK";
                try {
                    $pObject->performChecks();
                } catch (\Exception $e) {
                    $errors = "ERROR : ".$e->getMessage();
                }
                $meta = array(
                    "icon" 		    => "preferences_plugin.png",
                    "text"          => $pObject->getManifestLabel(),
                    "ajxp_mime"     => "ajxp_plugin",
                    "can_active"	=> $errors,
                    "enabled"	    => ($pObject->isEnabled()?$mess[440]:$mess[441]),
                    "plugin_id"     => $pObject->getId(),
                    "plugin_description" => $pObject->getManifestDescription()
                );
                $nodeKey = $baseDir."/".$pObject->getId();
                $this->appendBookmarkMeta($nodeKey, $meta);
                $nodesList->addBranch(new AJXP_Node($nodeKey, $meta));
            }
        }
        return $nodesList;

    }
}