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
namespace Pydio\Core\Serializer;

use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Model\RepositoryInterface;
use Pydio\Core\Model\UserInterface;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\Vars\StringHelper;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class RepositoryXML
 * @package Pydio\Core\Serializer
 */
class RepositoryXML
{
    /**
     * @var array
     */
    protected $exposed;

    /**
     * @var array
     */
    protected $streams;

    /**
     * @var UserInterface
     */
    protected $loggedUser;

    /**
     * RepositoryXML constructor.
     * @param array $exposed
     * @param array $streams
     * @param UserInterface $loggedUser
     */
    public function __construct($exposed, $streams, $loggedUser = null)
    {
        $this->exposed = $exposed;
        $this->streams = $streams;
        $this->loggedUser = $loggedUser;
    }

    /**
     * @param RepositoryInterface $repository
     * @param $isActive
     * @param $accessStatus
     * @return string
     */
    public function serialize(RepositoryInterface $repository, $isActive, $accessStatus){
        return $this->repositoryToXML($repository, $isActive, $this->exposed, $this->streams, $this->loggedUser, $accessStatus);
    }


    /**
     * @param RepositoryInterface $repoObject
     * @param bool $isActive
     * @param array $exposed
     * @param array $streams
     * @param UserInterface $loggedUser
     * @param string $accessStatus
     * @return string
     * @throws \Exception
     */
    protected function repositoryToXML($repoObject, $isActive, $exposed, $streams, $loggedUser, $accessStatus = ""){

        $repoId = $repoObject->getId();
        $statusString = " repository_type=\"".$repoObject->getRepositoryType()."\"";
        if(empty($accessStatus)){
            $accessStatus = $repoObject->getAccessStatus();
        }
        if(!empty($accessStatus)){
            $statusString .= " access_status=\"$accessStatus\" ";
        }else if($loggedUser != null){
            $lastConnected = $loggedUser->getArrayPref("repository_last_connected", $repoId);
            if(!empty($lastConnected)) $statusString .= " last_connection=\"$lastConnected\" ";
        }

        $streamString = "";
        if (in_array($repoObject->getAccessType(), $streams)) {
            $streamString = "allowCrossRepositoryCopy=\"true\"";
        }
        if ($repoObject->getUniqueUser()) {
            $streamString .= " user_editable_repository=\"true\" ";
        }
        if ($repoObject->hasContentFilter()){
            $streamString .= " hasContentFilter=\"true\"";
        }
        $slugString = "";
        $slug = $repoObject->getSlug();
        if (!empty($slug)) {
            $slugString = "repositorySlug=\"$slug\"";
        }
        $isSharedString = "";
        $currentUserIsOwner = false;
        $ownerLabel = null;
        if ($repoObject->hasOwner()) {
            if(strpos($repoId, 'ocs_remote_share_') === 0){
                $ownerLabel = $uId = $repoObject->getOwner();
            }else{
                $uId = $repoObject->getOwner();
                if($loggedUser != null && $loggedUser->getId() == $uId){
                    $currentUserIsOwner = true;
                }
                $ownerLabel = UsersService::getUserPersonalParameter("USER_DISPLAY_NAME", $uId, "core.conf", $uId);
            }
            $isSharedString =  'owner="'. StringHelper::xmlEntities($ownerLabel) .'"';
        }
        if ($repoObject->securityScope() == "USER" || $currentUserIsOwner){
            $streamString .= " userScope=\"true\"";
        }

        $descTag = "";
        $description = $repoObject->getDescription(ApplicationState::hasMinisiteHash(), $ownerLabel);
        if (!empty($description)) {
            $descTag = '<description>'. StringHelper::xmlEntities($description, true) .'</description>';
        }
        $ctx = Context::contextWithObjects($loggedUser, $repoObject);
        $roleString="";
        if($loggedUser != null){
            $merged = $loggedUser->getMergedRole();
            $params = array();
            foreach($exposed as $exposed_prop){
                $metaOptions = $repoObject->getContextOption($ctx, "META_SOURCES");
                if(!isSet($metaOptions[$exposed_prop["PLUGIN_ID"]])){
                    continue;
                }
                $value = $exposed_prop["DEFAULT"];
                if(isSet($metaOptions[$exposed_prop["PLUGIN_ID"]][$exposed_prop["NAME"]])){
                    $value = $metaOptions[$exposed_prop["PLUGIN_ID"]][$exposed_prop["NAME"]];
                }
                $value = $merged->filterParameterValue($exposed_prop["PLUGIN_ID"], $exposed_prop["NAME"], $repoId, $value);
                if($value !== null){
                    if($value === true  || $value === false) $value = ($value === true ?"true":"false");
                    $params[] = '<repository_plugin_param plugin_id="'.$exposed_prop["PLUGIN_ID"].'" name="'.$exposed_prop["NAME"].'" value="'. StringHelper::xmlEntities($value) .'"/>';
                    $roleString .= str_replace(".", "_",$exposed_prop["PLUGIN_ID"])."_".$exposed_prop["NAME"].'="'. StringHelper::xmlEntities($value) .'" ';
                }
            }
            $roleString.='acl="'.$merged->getAcl($repoId).'"';
            if($merged->hasMask($repoId)){
                $roleString.= ' hasMask="true" ';
            }
        }
        $clientSettings = (!$isActive ? "" : $this->repositoryClientSettings($repoObject, $ctx));
        return "<repo access_type=\"".$repoObject->getAccessType()."\" id=\"".$repoId."\"$statusString $streamString $slugString $isSharedString $roleString><label>".StringHelper::xmlEntities($repoObject->getDisplay())."</label>".$descTag.$clientSettings."</repo>";

    }

    /**
     * @param RepositoryInterface $repoObject
     * @param ContextInterface $ctx
     * @return string
     */
    protected function repositoryClientSettings($repoObject, $ctx){

        $plugin = $repoObject->getDriverInstance($ctx);
        if(empty($plugin)){
            $plugin = PluginsService::getInstance($ctx)->getPluginByTypeName("access", $repoObject->getAccessType());
        }
        if(empty($plugin)){
            return "";
        }
        if ($repoObject->hasParent()) {
            $parentObject = $repoObject->getParentRepository();
            if ($parentObject != null && $parentObject->isTemplate()) {
                $ic = $parentObject->getContextOption($ctx, "TPL_ICON_SMALL");
                $settings = $plugin->getManifestRawContent("//client_settings", "node");
                if (!empty($ic) && $settings->length) {
                    $newAttr = $settings->item(0)->ownerDocument->createAttribute("icon_tpl_id");
                    $newAttr->nodeValue = $repoObject->getParentId();
                    $settings->item(0)->appendChild($newAttr);
                    return $settings->item(0)->ownerDocument->saveXML($settings->item(0));
                }
            }
        }
        return $plugin->getManifestRawContent("//client_settings", "string");

    }


}