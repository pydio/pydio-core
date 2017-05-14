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
 */
namespace Pydio\Access\Meta\Lock;

use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\NodesDiff;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\UsersService;
use Pydio\Access\Meta\Core\AbstractMetaSource;
use Pydio\Access\Metastore\Core\IMetaStoreProvider;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Locks a folder manually
 * @package AjaXplorer_Plugins
 * @subpackage Meta
 */
class SimpleLockManager extends AbstractMetaSource
{
    const METADATA_LOCK_NAMESPACE = "simple_lock";
    /**
    * @var IMetaStoreProvider
    */
    protected $metaStore;

    /**
     * @param ContextInterface $ctx
     * @param AbstractAccessDriver $accessDriver
     * @throws PydioException
     */
    public function initMeta(ContextInterface $ctx, AbstractAccessDriver $accessDriver)
    {
        parent::initMeta($ctx, $accessDriver);
        $store = PluginsService::getInstance($ctx)->getUniqueActivePluginForType("metastore");
        if ($store === false) {
            throw new PydioException("The 'meta.simple_lock' plugin requires at least one active 'metastore' plugin");
        }
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @throws PydioException
     */
    public function applyChangeLock(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {
        $httpVars = $requestInterface->getParsedBody();
        /** @var ContextInterface $ctx */
        $ctx = $requestInterface->getAttribute("ctx");

        if ($ctx->getRepository()->getDriverInstance($ctx) instanceof \Pydio\Access\Driver\StreamProvider\FS\DemoAccessDriver) {
            throw new PydioException("Write actions are disabled in demo mode!");
        }
        $repo = $ctx->getRepository();
        $user = $ctx->getUser();
        if (!UsersService::usersEnabled() && $user!=null && !$user->canWrite($repo->getId())) {
            throw new PydioException("You have no right on this action.");
        }
        $selection = UserSelection::fromContext($ctx, $httpVars);

        $unlock = (isSet($httpVars["unlock"])?true:false);
        $node = $selection->getUniqueNode();
        if ($unlock) {
            $node->removeMetadata(self::METADATA_LOCK_NAMESPACE, false, AJXP_METADATA_SCOPE_GLOBAL);
        } else {
            $node->setMetadata(
                SimpleLockManager::METADATA_LOCK_NAMESPACE,
                array("lock_user" => $ctx->getUser()->getId()),
                false,
                AJXP_METADATA_SCOPE_GLOBAL
            );
        }
        $x = new SerializableResponseStream();
        $diff = new NodesDiff();
        $diff->update($selection->getUniqueNode());
        $x->addChunk($diff);
        $responseInterface = $responseInterface->withBody($x);
    }

    /**
     * @param AJXP_Node $node
     */
    public function processLockMeta($node)
    {
        // Transform meta into overlay_icon
        // $this->logDebug("SHOULD PROCESS METADATA FOR ", $node->getLabel());
        $lock = $node->retrieveMetadata(
           SimpleLockManager::METADATA_LOCK_NAMESPACE,
           false,
           AJXP_METADATA_SCOPE_GLOBAL);
        if(is_array($lock)
            && array_key_exists("lock_user", $lock)){
            if ($lock["lock_user"] != $node->getContext()->getUser()->getId()) {
                $displayName = UsersService::getUserPersonalParameter("USER_DISPLAY_NAME", $lock["lock_user"], "core.conf", $lock["lock_user"]);
                $node->setLabel($node->getLabel() . " (locked by ".$displayName.")");
                $node->mergeMetadata(array(
                    "sl_locked" => "true",
                    "overlay_icon" => "meta_simple_lock/ICON_SIZE/lock.png",
                    "overlay_class" => "icon-lock"
                ), true);
            } else {
                $node->setLabel($node->getLabel() . " (locked by you)");
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
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     * @throws PydioException
     */
    public function checkFileLock($node)
    {
        $this->logDebug("SHOULD CHECK LOCK METADATA FOR ", $node->getLabel());
        $lock = $node->retrieveMetadata(
           SimpleLockManager::METADATA_LOCK_NAMESPACE,
           false,
           AJXP_METADATA_SCOPE_GLOBAL);
        if(is_array($lock)
            && array_key_exists("lock_user", $lock)
            && $lock["lock_user"] != $node->getUserId()){
            $mess = LocaleService::getMessages();
            throw new PydioException($mess["meta.simple_lock.5"]);
        }
    }
}
