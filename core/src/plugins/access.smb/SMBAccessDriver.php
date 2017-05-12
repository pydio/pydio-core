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
 * The latest code can be found at <https://pydio.com>.
 *
 */
namespace Pydio\Access\Driver\StreamProvider\SMB;

use DOMNode;
use PclZip;
use Pydio\Access\Core\MetaStreamWrapper;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Access\Core\RecycleBinManager;
use Pydio\Access\Core\Model\Repository;
use Pydio\Access\Driver\StreamProvider\FS\FsAccessDriver;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Utils\TextEncoder;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Tasks\Task;
use Pydio\Tasks\TaskService;


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

    protected function loadExternalWrapper(){
        if(!empty($this->pluginConf['SMBCLIENT']) && !defined('SMB4PHP_SMBCLIENT')){
            define ('SMB4PHP_SMBCLIENT', $this->pluginConf["SMBCLIENT"]);
        }
        if(!empty($this->pluginConf['SMB_PATH_TMP']) && !defined('SMB_PATH_TMP')){
            define ('SMB4PHP_SMBTMP', $this->pluginConf["SMB_PATH_TMP"]);
        }
        require_once($this->getBaseDir()."/smb.php");
    }

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
        $this->loadExternalWrapper();

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

    /**
     * @param bool $register
     * @param ContextInterface|null $ctx
     * @return array|bool
     */
    public function detectStreamWrapper($register = false, ContextInterface $ctx = null)
    {
        if ($register) {
            $this->loadExternalWrapper();
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

    /**
     * @param AJXP_Node $node
     * @return bool
     */
    public function isWriteable(AJXP_Node $node)
    {
        $dir = $node->getUrl();
        if(substr_count($dir, '/') <= 3) $rc = true;
    	else $rc = is_writable($dir);
    	return $rc;
    }
}
