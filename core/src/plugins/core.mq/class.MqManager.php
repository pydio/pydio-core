<?php
/*
 * Copyright 2007-2012 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */

defined('AJXP_EXEC') or die( 'Access not allowed');

// DL and install install vendor (composer?) https://github.com/Devristo/phpws


/**
 * Websocket JS Sample
 *
 * var websocket = new WebSocket("ws://serverURL:8090/echo");
websocket.onmessage = function(event){console.log(event.data);};
 *
 *     new PeriodicalExecuter(function(pe){
     var conn = new Connexion();
     conn.setParameters($H({get_action:'client_consume_channel',channel:'nodes:0',client_id:'toto'}));
     conn.onComplete = function(transport){ajaxplorer.actionBar.parseXmlMessage(transport.responseXML);};
     conn.sendAsync();
     }, 5);
 *
 * @package AjaXplorer_Plugins
 * @subpackage Core
 *
 */
class MqManager extends AJXP_Plugin
{

    private $wsClient;

    /**
     * @var AJXP_MessageExchanger;
     */
    private $msgExchanger = false;

    function init($options){
        parent::init($options);
        try{
            $this->msgExchanger = AJXP_PluginsService::findPlugin("core", "notifications")->getExchanger();
        }catch (Exception $e){}
    }

    /**
     * @param null AJXP_Node $origNode
     * @param null AJXP_Node $newNode
     * @param bool bool $copy
     */
    public function publishNodeChange($origNode = null, $newNode = null, $copy = false){
        $content = "";$repo = "";
        if($newNode != null) {
            $repo = $newNode->getRepositoryId();
            $update = false;
            $data = array();
            if($origNode != null){
                $update = true;
                $data[$origNode->getPath()] = $newNode;
            }else{
                $data[] = $newNode;
            }
            $content = AJXP_XMLWriter::writeNodesDiff(array(($update?"UPDATE":"ADD") => $data));
        }
        if($origNode != null && ! $update){
            $repo = $origNode->getRepositoryId();
            $content = AJXP_XMLWriter::writeNodesDiff(array("REMOVE" => array($origNode->getPath())));
        }
        if(!empty($content) && $repo != ""){

            $this->sendInstantMessage($content, $repo);

        }

    }

    public function sendInstantMessage($xmlContent, $repositoryId){

        $scope = ConfService::getRepositoryById($repositoryId)->securityScope();
        if($scope == "USER"){
            $userId = AuthService::getLoggedUser()->getId();
        }else if($scope == "GROUP"){
            $gPath = AuthService::getLoggedUser()->getGroupPath();
        }

        // Publish for pollers
        $message = new stdClass();
        $message->content = $xmlContent;
        if(isSet($userId)) $message->userId = $userId;
        if(isSet($gPath)) $message->groupPath = $gPath;

        if(isSet($this->msgExchanger)){
            $this->msgExchanger->publishInstantMessage("nodes:$repositoryId", $message);
        }

        // Publish for WebSockets
        $configs = $this->getConfigs();
        if($configs["WS_SERVER_ACTIVE"]){

            require_once(AJXP_INSTALL_PATH."/vendor/phpws/websocket.client.php");
            // Publish for websockets
            $input = array("REPO_ID" => $repositoryId, "CONTENT" => "<tree>".$xmlContent."</tree>");
            if(isSet($userId)) $input["USER_ID"] = $userId;
            else if(isSet($gPath)) $input["GROUP_PATH"] = $gPath;
            $input = serialize($input);
            $msg = WebSocketMessage::create($input);
            if(!isset($this->wsClient)){
                $this->wsClient = new WebSocket("ws://".$configs["WS_SERVER_HOST"].":".$configs["WS_SERVER_PORT"].$configs["WS_SERVER_PATH"]);
                $this->wsClient->addHeader("Admin-Key", $configs["WS_SERVER_ADMIN"]);
                @$this->wsClient->open();
            }
            $this->wsClient->sendMessage($msg);
        }

    }

    /**
     * @param $action
     * @param $httpVars
     * @param $fileVars
     *
     */
    public function clientChannelMethod($action, $httpVars, $fileVars){
        if(!$this->msgExchanger) return;
        switch($action){
            case "client_register_channel":
                $this->msgExchanger->suscribeToChannel($httpVars["channel"], $httpVars["client_id"]);
                break;
            case "client_unregister_channel":
                $this->msgExchanger->unsuscribeFromChannel($httpVars["channel"], $httpVars["client_id"]);
                break;
            case "client_consume_channel":
               $user = AuthService::getLoggedUser();
               if($user == null){
                   //throw new Exception("You must be logged in");
                   AJXP_XMLWriter::header();
                   AJXP_XMLWriter::requireAuth();
                   AJXP_XMLWriter::close();
                   return;
               }
               $GROUP_PATH = $user->getGroupPath();
               if($GROUP_PATH == null) $GROUP_PATH = false;
               $uId = $user->getId();
               //session_write_close();

               $startTime = time();
               $maxTime = $startTime + (30 - 3);

//               while(true){

                   $data = $this->msgExchanger->consumeInstantChannel($httpVars["channel"], $httpVars["client_id"], $uId, $GROUP_PATH);
                   if(count($data)){
                       AJXP_XMLWriter::header();
                       ksort($data);
                       foreach($data as $messageObject){
                           echo $messageObject->content;
                       }
                       AJXP_XMLWriter::close();
                   }
//                       break;
//                   }else if(time() >= $maxTime){
//                       break;
//                   }
//
//                   sleep(3);
//               }


                break;
            default:
                break;
        }
    }

    public function wsAuthenticate($action, $httpVars, $fileVars){

        AJXP_Logger::debug("Entering wsAuthenticate");
        $configs = $this->getConfigs();
        if(!isSet($httpVars["key"]) || $httpVars["key"] != $configs["WS_SERVER_ADMIN"]){
            throw new Exception("Cannot authentify admin key");
        }
        $user = AuthService::getLoggedUser();
        if($user == null){
            AJXP_Logger::debug("Error Authenticating through WebSocket (not logged)");
            throw new Exception("You must be logged in");
        }
        $xml = AJXP_XMLWriter::getUserXML($user);
        // add groupPath
        if($user->getGroupPath() != null){
            $groupString = "groupPath=\"".AJXP_Utils::xmlEntities($user->getGroupPath())."\"";
            $xml = str_replace("<user id=", "<user {$groupString} id=", $xml);
        }
        AJXP_Logger::debug("Authenticating user ".$user->id." through WebSocket");
        AJXP_XMLWriter::header();
        echo $xml;
        AJXP_XMLWriter::close();

    }

}
