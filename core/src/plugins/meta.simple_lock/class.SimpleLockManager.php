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
 */

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Locks a folder manually
 * @package AjaXplorer_Plugins
 * @subpackage Meta
 */
class SimpleLockManager extends AJXP_AbstractMetaSource
{
    const METADATA_LOCK_NAMESPACE = "simple_lock";
    /**
    * @var MetaStoreProvider
    */
    protected $metaStore;

    public function initMeta($accessDriver)
    {
        parent::initMeta($accessDriver);
        $store = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("metastore");
        if ($store === false) {
            throw new Exception("The 'meta.simple_lock' plugin requires at least one active 'metastore' plugin");
        }
        $this->metaStore = $store;
        $this->metaStore->initMeta($accessDriver);
    }

    /**
     * @param string $action
     * @param array $httpVars
     * @param array $fileVars
     */
    public function applyChangeLock($actionName, $httpVars, $fileVars)
    {
        if(!isSet($this->actions[$actionName])) return;
        if (is_a($this->accessDriver, "demoAccessDriver")) {
            throw new Exception("Write actions are disabled in demo mode!");
        }
        $repo = $this->accessDriver->repository;
        $user = AuthService::getLoggedUser();
        if (!AuthService::usersEnabled() && $user!=null && !$user->canWrite($repo->getId())) {
            throw new Exception("You have no right on this action.");
        }
        $selection = new UserSelection();
        $selection->initFromHttpVars($httpVars);
        $currentFile = $selection->getUniqueFile();
        $wrapperData = $this->accessDriver->detectStreamWrapper(false);
        $urlBase = $wrapperData["protocol"]."://".$this->accessDriver->repository->getId();

        $unlock = (isSet($httpVars["unlock"])?true:false);
        $ajxpNode = new AJXP_Node($urlBase.$currentFile);
        if ($unlock) {
            $this->metaStore->removeMetadata($ajxpNode, self::METADATA_LOCK_NAMESPACE, false, AJXP_METADATA_SCOPE_GLOBAL);
        } else {
            $this->metaStore->setMetadata(
                $ajxpNode,
                SimpleLockManager::METADATA_LOCK_NAMESPACE,
                array("lock_user" => AuthService::getLoggedUser()->getId()),
                false,
                AJXP_METADATA_SCOPE_GLOBAL
            );
        }
        AJXP_XMLWriter::header();
        AJXP_XMLWriter::reloadDataNode();
        AJXP_XMLWriter::close();
    }

    /**
     * @param AJXP_Node $node
     */
    public function processLockMeta($node)
    {
        // Transform meta into overlay_icon
        // $this->logDebug("SHOULD PROCESS METADATA FOR ", $node->getLabel());
        $lock = $this->metaStore->retrieveMetadata(
           $node,
           SimpleLockManager::METADATA_LOCK_NAMESPACE,
           false,
           AJXP_METADATA_SCOPE_GLOBAL);
        if(is_array($lock)
            && array_key_exists("lock_user", $lock)){
            if ($lock["lock_user"] != AuthService::getLoggedUser()->getId()) {
                $node->mergeMetadata(array(
                    "sl_locked" => "true",
                    "overlay_icon" => "meta_simple_lock/ICON_SIZE/lock.png",
                    "overlay_class" => "icon-lock"
                ), true);
            } else {
                $node->mergeMetadata(array(
                    "sl_locked" => "true",
                    "sl_mylock" => "true",
                    "overlay_icon" => "meta_simple_lock/ICON_SIZE/lock_my.png",
                    "overlay_class" => "icon-lock"
                ), true);
            }
        }
    }

    /**
     * @param AJXP_Node $node
     */
    public function checkFileLock($node)
    {
        $this->logDebug("SHOULD CHECK LOCK METADATA FOR ", $node->getLabel());
        $lock = $this->metaStore->retrieveMetadata(
           $node,
           SimpleLockManager::METADATA_LOCK_NAMESPACE,
           false,
           AJXP_METADATA_SCOPE_GLOBAL);
        if(is_array($lock)
            && array_key_exists("lock_user", $lock)
            && $lock["lock_user"] != AuthService::getLoggedUser()->getId()){
            $mess = ConfService::getMessages();
            throw new Exception($mess["meta.simple_lock.5"]);
        }
    }
}
