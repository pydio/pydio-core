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
 * The latest code can be found at <http://pyd.io/>.
 */

namespace Pydio\OCS\Model;
defined('AJXP_EXEC') or die('Access not allowed');

require_once(AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/action.share/class.ShareLink.php");
class TargettedLink extends \ShareLink
{
    /**
     * @var array
     */
    protected $pendingInvitation;

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
     */
    public function prepareInvitation($remoteServer, $remoteUser, $documentName){
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
     * @param \PublicAccessManager $publicAccessManager
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