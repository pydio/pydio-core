<?php
/*
 * Copyright 2007-2015 Abstrium <contact (at) pydio.com>
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

namespace Pydio\OCS\Model;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\ConfService;
use Pydio\Share\Model\ShareLink;
use Pydio\Share\View\PublicAccessManager;

defined('AJXP_EXEC') or die('Access not allowed');

require_once(AJXP_INSTALL_PATH . "/" . AJXP_PLUGINS_FOLDER . "/action.share/vendor/autoload.php");

/**
 * Class TargettedLink
 * @package Pydio\OCS\Model
 */
class TargettedLink extends ShareLink
{
    /**
     * @var array
     */
    protected $pendingInvitation;

    /**
     * TargettedLink constructor.
     * @param $store
     * @param array $storeData
     */
    public function __construct($store, $storeData = array()){
        parent::__construct($store, $storeData);
        $this->store = $store;
        $this->internal = $storeData;
        $this->internal["TARGET"] = "remote";
    }

    /**
     * @param string $remoteServer
     * @param string $remoteUser
     * @param string $documentName
     * @return ShareInvitation
     */
    public function createInvitation($remoteServer, $remoteUser, $documentName){

        $invitation = new ShareInvitation();
        $invitation->setStatus(OCS_INVITATION_STATUS_PENDING);
        $invitation->setLinkHash($this->getHash());
        $invitation->setOwner($this->getOwnerId());
        $invitation->setTargetHost($remoteServer);
        $invitation->setTargetUser($remoteUser);
        $invitation->setDocumentName($documentName);

        return $invitation;

    }

    /**
     * @param string $remoteServer
     * @param string $remoteUser
     * @param $documentName
     * @throws PydioException
     */
    public function prepareInvitation($remoteServer, $remoteUser, $documentName){

        $scheme = parse_url($remoteServer, PHP_URL_SCHEME);
        if($scheme === 'trusted'){
            $trustedServerId = parse_url($remoteServer, PHP_URL_HOST);
            $configs = ConfService::getGlobalConf('TRUSTED_SERVERS', 'ocs');
            if(isSet($configs) && isSet($configs[$trustedServerId])){
                $remoteServer = $configs[$trustedServerId]['url'];
            }else{
                throw new PydioException('Cannot find trusted server with id ' . $trustedServerId);
            }
        }

        $this->pendingInvitation = array("host" => $remoteServer, "user" => $remoteUser, "documentName" => $documentName);
    }

    /**
     * @return null|ShareInvitation
     */
    public function getPendingInvitation(){
        if(isSet($this->pendingInvitation)){
            return $this->createInvitation($this->pendingInvitation["host"], $this->pendingInvitation["user"], $this->pendingInvitation["documentName"]);
        }
        return null;
    }

    /**
     * @param PublicAccessManager $publicAccessManager
     * @param array $messages
     * @return mixed
     */
    public function getJsonData($publicAccessManager, $messages){
        $jsonData = parent::getJsonData($publicAccessManager, $messages);
        $ocsStore = new SQLStore();
        $invitations = $ocsStore->invitationsForLink($this->getHash());
        if(count($invitations)){
            $jsonData["invitation"] = array_shift($invitations)->jsonSerialize();
        }
        return $jsonData;
    }

}