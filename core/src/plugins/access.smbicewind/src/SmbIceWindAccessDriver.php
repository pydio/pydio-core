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
 * The latest code can be found at <http://pyd.io/>.
 *
 */

namespace Pydio\Access\Driver\StreamProvider\SmbIceWind;

use DOMNode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Access\Core\RecycleBinManager;
use Pydio\Access\Driver\StreamProvider\FS\fsAccessDriver;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Utils\Utils;
use Pydio\Core\Controller\XMLWriter;
use Pydio\Core\Utils\TextEncoder;
use Pydio\Tasks\Task;
use Pydio\Tasks\TaskService;

class smbIcewindAccessDriver extends fsAccessDriver{

    protected function initRepository(ContextInterface $contextInterface)
    {
        require_once AJXP_INSTALL_PATH . "/" . AJXP_PLUGINS_FOLDER . "/access.smbicewind/vendor/autoload.php";
        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }
        $this->detectStreamWrapper(true);
        $this->urlBase = $contextInterface->getUrlBase();
        if (strpos($this->urlBase,"ajxp_meta") !== false){
            $test = "tet";
        }
        $recycle = $contextInterface->getRepository()->getContextOption($contextInterface, "RECYCLE_BIN");
        if ($recycle != "") {
            RecycleBinManager::init($contextInterface->getUrlBase(), "/".$recycle);
        }
    }
}