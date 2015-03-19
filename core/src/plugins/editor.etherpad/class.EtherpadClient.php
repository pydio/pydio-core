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

defined('AJXP_EXEC') or die( 'Access not allowed');

class EtherpadClient extends AJXP_Plugin
{
    public $baseURL = "http://localhost:9001";
    public $apiKey = "";

    public function switchAction($actionName, $httpVars, $fileVars)
    {
        $this->baseURL = rtrim($this->getFilteredOption("ETHERPAD_SERVER"), "/");
        $this->apiKey =  $this->getFilteredOption("ETHERPAD_APIKEY");

        $userSelection = new UserSelection(ConfService::getRepository(), $httpVars);
        if ($userSelection->isEmpty()){
            throw new Exception("Empty selection");
        }
        $repository = ConfService::getRepository();
        if (!$repository->detectStreamWrapper(false)) {
            return false;
        }
        $plugin = AJXP_PluginsService::findPlugin("access", $repository->getAccessType());
        $selectedNode = $userSelection->getUniqueNode($plugin);
        $selectedNode->loadNodeInfo();
        if(!$selectedNode->isLeaf()){
            throw new Exception("Cannot handle folders, please select a file!");
        }
        $nodeExtension = strtolower(pathinfo($selectedNode->getPath(), PATHINFO_EXTENSION));

        // Determine pad ID
        if($nodeExtension == "pad"){
            $padID = file_get_contents($selectedNode->getUrl());
        }else{
            // TRY TO LOAD PAD ID FROM NODE SHARED METADATA
            $metadata = $selectedNode->retrieveMetadata("etherpad", AJXP_METADATA_ALLUSERS, AJXP_METADATA_SCOPE_GLOBAL, false);
            if(isSet($metadata["pad_id"])){
                $padID = $metadata["pad_id"];
            }else{
                $padID = AJXP_Utils::generateRandomString();
                $selectedNode->setMetadata("etherpad", array("pad_id" => $padID), AJXP_METADATA_ALLUSERS, AJXP_METADATA_SCOPE_GLOBAL, false);
            }
        }

        require_once("etherpad-client/etherpad-lite-client.php");
        $client = new EtherpadLiteClient($this->apiKey,$this->baseURL."/api");
        $loggedUser = AuthService::getLoggedUser();
        $userName = $loggedUser->getId();
        $userLabel = $loggedUser->mergedRole->filterParameterValue("core.conf", "USER_DISPLAY_NAME", AJXP_REPO_SCOPE_ALL, $userName);
        $res = $client->createAuthorIfNotExistsFor($userName, $userLabel);
        $authorID = $res->authorID;
        $res2 = $client->createGroupIfNotExistsFor($loggedUser->getGroupPath());
        $groupID = $res2->groupID;
        $fullId = $groupID."$".$padID;

        if ($actionName == "etherpad_create") {

            $resP = $client->listPads($groupID);

            $currentContent = file_get_contents($selectedNode->getUrl());
            if($nodeExtension == "html" && strpos($currentContent, "<html>") === false){
               $currentContent = "<html><head></head><body>$currentContent</body></html>";
            }
            if (!in_array($fullId, $resP->padIDs)) {
                $client->createGroupPad($groupID, $padID, null);
                if($nodeExtension == "html" && !empty($currentContent)){
                    $client->setHTML($fullId, $currentContent);
                }else if($nodeExtension != "pad"){
                    $client->setText($fullId, $currentContent);
                }
            } else if($nodeExtension != "pad") {
                // If someone is already connected, do not override.
                $existingAuthors = $client->listAuthorsOfPad($fullId);
                if(!count($existingAuthors->authorIDs)){
                    if($nodeExtension == "html" && !empty($currentContent)){
                        $client->setHTML($fullId, $currentContent);
                    }else{
                        $client->setText($fullId, $currentContent);
                    }
                }
            }

            $res4 = $client->createSession($groupID, $authorID, time()+14400);
            $sessionID = $res4->sessionID;

            setcookie('sessionID', $sessionID, null, "/");

            $padID = $groupID.'$'.$padID;

            $data = array(
                "url" => $this->baseURL."/p/".$padID,
                "padID" => $padID,
                "sessionID" => $sessionID,
            );

            HTMLWriter::charsetHeader('application/json');
            echo(json_encode($data));

        } else if ($actionName == "etherpad_save") {

            $padID = $httpVars["pad_id"];

            if ($nodeExtension == "html" || $nodeExtension == "pad") {
                $res = $client->getHTML($padID);
                $content = $res->html;
            } else {
                $res = $client->getText($padID);
                $content = $res->text;
            }

            if($nodeExtension == "pad"){
                // Create a new file and save the content in it.
                $origUrl = $selectedNode->getUrl();
                $mess = ConfService::getMessages();
                $dateStamp = date(" Y-m-d H:i", time());
                $startUrl = preg_replace('"\.pad$"', $dateStamp.'.html', $origUrl);
                $newNode = new AJXP_Node($startUrl);
                AJXP_Controller::applyHook("node.before_create", array($newNode, strlen($content)));
                file_put_contents($newNode->getUrl(), $content);
                AJXP_Controller::applyHook("node.change", array(null, $newNode));
            }else{
                AJXP_Controller::applyHook("node.before_change", array($selectedNode, strlen($content)));
                file_put_contents($selectedNode->getUrl(), $content);
                clearstatcache(true, $selectedNode->getUrl());
                $selectedNode->loadNodeInfo(true);
                AJXP_Controller::applyHook("node.change", array($selectedNode, $selectedNode));
            }


        } else if ($actionName == "etherpad_close") {

            // WE SHOULD DETECT IF THERE IS NOBODY CONNECTED ANYMORE, AND DELETE THE PAD.
            // BUT SEEMS LIKE THERE'S NO WAY TO PROPERLY REMOVE AN AUTHOR VIA API
            $sessionID = $httpVars["session_id"];
            $client->deleteSession($sessionID);

        } else if ($actionName == "etherpad_proxy_api") {

            if ($httpVars["api_action"] == "list_pads") {
                $res = $client->listPads($groupID);
            } else if ($httpVars["api_action"] == "list_authors_for_pad") {
                $res = $client->listAuthorsOfPad($httpVars["pad_id"]);
            }
            HTMLWriter::charsetHeader("application/json");
            echo(json_encode($res));

        } else if ($actionName == "etherpad_get_content"){

            HTMLWriter::charsetHeader("text/plain");
            echo $client->getText($httpVars["pad_id"])->text;

        }

        return null;

    }

    /**
     * @param AJXP_Node $ajxpNode
     */
    public function hideExtension(&$ajxpNode){
        if($ajxpNode->hasExtension("pad")){
            $baseName = AJXP_Utils::safeBasename($ajxpNode->getPath());
            $ajxpNode->setLabel(str_replace(".pad", "", $baseName));
        }
    }

    /**
     * @param AJXP_Node $fromNode
     * @param AJXP_Node $toNode
     * @param bool $copy
     */
    public function handleNodeChange($fromNode=null, $toNode=null, $copy = false){
        if($fromNode == null) return;
        if($toNode == null){
            $fromNode->removeMetadata("etherpad", AJXP_METADATA_ALLUSERS, AJXP_METADATA_SCOPE_GLOBAL, false);
        }else if(!$copy){
            $toNode->copyOrMoveMetadataFromNode($fromNode, "etherpad", "move", AJXP_METADATA_ALLUSERS, AJXP_METADATA_SCOPE_GLOBAL, false);
        }
    }

}
