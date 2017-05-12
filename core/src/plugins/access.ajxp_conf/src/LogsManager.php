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
use Pydio\Log\Core\Logger;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class LogsManager
 * @package Pydio\Access\Driver\DataProvider\Provisioning
 */
class LogsManager extends AbstractManager
{

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
        $relativePath   = "/$relativePath";
        $nodesList      = new NodesList("/".$rootPath.$relativePath);
        $logger         = Logger::getInstance();
        $parts          = explode("/", $relativePath);
        $leaf           = false;

        if (count($parts)>4) {

            $nodesList->initColumnsData("filelist", "list", "ajxp_conf.logs");
            $nodesList->appendColumn("ajxp_conf.17", "date", "MyDate", "18%");
            $nodesList->appendColumn("ajxp_conf.18", "ip", "String", "5%");
            $nodesList->appendColumn("ajxp_conf.19", "level", "String", "10%");
            $nodesList->appendColumn("ajxp_conf.20", "user", "String", "5%");
            $nodesList->appendColumn("ajxp_conf.124", "source", "String", "5%");
            $nodesList->appendColumn("ajxp_conf.21", "action", "String", "7%");
            $nodesList->appendColumn("ajxp_conf.22", "params", "String", "50%");

            $leaf = true;
            $date = $parts[count($parts)-1];
            $logs = $logger->listLogs($relativePath, $date, "tree", "/" . $rootPath . "/logs", isSet($_POST["cursor"]) ? intval($_POST["cursor"]) : -1);

        } else {

            $nodesList->initColumnsData("filelist", "list", "ajxp_conf.logs");
            $nodesList->appendColumn("ajxp_conf.16", "ajxp_label");
            if(count($parts) > 3) $leaf = true;
            $logs = $logger->listLogFiles("tree", (count($parts) > 2 ? $parts[2] : null), (count($parts) > 3 ? $parts[3] : null), "/" . $rootPath . "/logs");

        }

        foreach($logs as $path => $meta){

            if(!empty($findNodePosition)  && basename($path) !== $findNodePosition){
                continue;
            }
            if($leaf) $meta["is_file"] = true;
            $this->appendBookmarkMeta($path, $meta);
            $nodesList->addBranch(new AJXP_Node($path, $meta));

        }

        return $nodesList;

    }
}