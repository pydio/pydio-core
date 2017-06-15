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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\NodesList;
use Pydio\Core\Utils\Reflection\DocsParser;
use Pydio\Core\Utils\Reflection\PydioSdkGenerator;
use Zend\Diactoros\Response\JsonResponse;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class HooksManager
 * @package Pydio\Access\Driver\DataProvider\Provisioning
 */
class DocumentationManager extends AbstractManager
{

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @return ResponseInterface
     */
    public function docActions(ServerRequestInterface $requestInterface, ResponseInterface $responseInterface){

        PydioSdkGenerator::analyzeRegistry(isSet($httpVars["version"])?$httpVars["version"]:AJXP_VERSION);

        return new JsonResponse(["result"=>"ok"]);
    }

    /**
     * @param ServerRequestInterface $requestInterface Full set of query parameters
     * @param string $rootPath Path to prepend to the resulting nodes
     * @param string $relativePath Specific path part for this function
     * @param string $paginationHash Number added to url#2 for pagination purpose.
     * @param string $findNodePosition Path to a given node to try to find it
     * @param string $aliasedDir Aliased path used for alternative url
     * @return NodesList A populated NodesList object, eventually recursive.
     */
    public function listNodes(ServerRequestInterface $requestInterface, $rootPath, $relativePath, $paginationHash = null, $findNodePosition = null, $aliasedDir = null)
    {
        $nodesList      = new NodesList("/$rootPath/$relativePath");
        $jsonContent    = json_decode(file_get_contents(DocsParser::getHooksFile()), true);
        $nodesList->initColumnsData("full", "full", "hooks.list");
        $nodesList->appendColumn("ajxp_conf.17", "ajxp_label", "String", "20%");
        $nodesList->appendColumn("ajxp_conf.18", "description", "String", "20%");
        $nodesList->appendColumn("ajxp_conf.19", "triggers", "String", "25%");
        $nodesList->appendColumn("ajxp_conf.20", "listeners", "String", "25%");
        $nodesList->appendColumn("ajxp_conf.21", "sample", "String", "10%");

        foreach ($jsonContent as $hookName => $hookData) {
            $metadata = array(
                "icon"          => "preferences_plugin.png",
                "text"          => $hookName,
                "description"   => $hookData["DESCRIPTION"],
                "sample"        => $hookData["PARAMETER_SAMPLE"],
            );
            $trigs = array();
            foreach ($hookData["TRIGGERS"] as $trigger) {
                $trigs[] = "<span>".$trigger["FILE"]." (".$trigger["LINE"].")</span>";
            }
            $metadata["triggers"] = implode("<br/>", $trigs);
            $listeners = array();
            foreach ($hookData["LISTENERS"] as $listener) {
                $listeners[] = "<span>Plugin ".$listener["PLUGIN_ID"].", in method ".$listener["METHOD"]."</span>";
            }
            $metadata["listeners"] = implode("<br/>", $listeners);
            $nodeKey = "/$rootPath/hooks/$hookName/$hookName";
            $this->appendBookmarkMeta($nodeKey, $meta);
            $nodesList->addBranch(new AJXP_Node($nodeKey, $meta));
        }

        return $nodesList;

    }
}