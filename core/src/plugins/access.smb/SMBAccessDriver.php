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
namespace Pydio\Access\Driver\StreamProvider\SMB;

use DOMNode;


use Pydio\Access\Core\RecycleBinManager;
use Pydio\Access\Core\Model\Repository;
use Pydio\Access\Driver\StreamProvider\FS\FsAccessDriver;

use Pydio\Core\Model\ContextInterface;

use Pydio\Core\Exception\PydioException;


defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Plugin to access a samba server
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class SMBAccessDriver extends FsAccessDriver
{
    /**
    * @var Repository
    */
    public $repository;
    public $driverConf;
    protected $wrapperClassName;
    protected $urlBase;

    /**
     * @param ContextInterface $contextInterface
     * @throws PydioException
     * @throws \Exception
     */
    protected function initRepository(ContextInterface $contextInterface)
    {
        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }
        $smbclientPath = $this->driverConf["SMBCLIENT"];
        define ('SMB4PHP_SMBCLIENT', $smbclientPath);

        $smbtmpPath = $this->driverConf["SMB_PATH_TMP"];
        define ('SMB4PHP_SMBTMP', $smbtmpPath);
		
        require_once($this->getBaseDir()."/smb.php");

        //$create = $this->repository->getOption("CREATE");
        $recycle = $this->repository->getContextOption($contextInterface, "RECYCLE_BIN");

        $this->urlBase = $contextInterface->getUrlBase();

        if ($recycle!= "" && !is_dir($contextInterface->getUrlBase()."/".$recycle)) {
            @mkdir($contextInterface->getUrlBase()."/".$recycle);
            if (!is_dir($contextInterface->getUrlBase()."/".$recycle)) {
                throw new PydioException("Cannot create recycle bin folder. Please check repository configuration or that your folder is writeable!");
            }
        }
        if ($recycle != "") {
            RecycleBinManager::init($contextInterface->getUrlBase(), "/".$recycle);
        }

    }

    public function detectStreamWrapper($register = false, ContextInterface $ctx = null)
    {
        if ($register) {
            require_once($this->getBaseDir()."/smb.php");
        }
        return parent::detectStreamWrapper($register, $ctx);
    }

    /**
     * @inheritdoc
     */
    protected function parseSpecificContributions(ContextInterface $ctx, \DOMNode &$contribNode)
    {
        parent::parseSpecificContributions($ctx, $contribNode);
        if ($contribNode->nodeName != "actions" || (isSet($this->pluginConf["SMB_ENABLE_ZIP"]) && $this->pluginConf["SMB_ENABLE_ZIP"] == true)) {
            return ;
        }
        $this->disableArchiveBrowsingContributions($contribNode);
    }

    public function isWriteable($dir, $type="dir")
    {
        if(substr_count($dir, '/') <= 3) $rc = true;
    	else $rc = is_writable($dir);
    	return $rc;
    }
}
