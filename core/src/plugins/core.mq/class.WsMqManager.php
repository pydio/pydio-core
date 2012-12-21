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


require_once(AJXP_INSTALL_PATH."/vendor/phpws/websocket.client.php");

/**
 * Websocket JS Sample
 *
 * var websocket = new WebSocket("ws://serverURL:8090/echo");
websocket.onmessage = function(event){console.log(event.data);};
 *
 * @todo : HOW TO AUTHENTICATE USER???
 */

class WsMqManager extends AJXP_Plugin
{

    private $clientsGCTime = 10;
    /**
     * @var Array
     */
    private $channels;

    function loadChannel($channelName, $create = false){
        if(isSet($this->channels) && is_array($this->channels[$channelName])) {
            return;
        }
        if(is_file($this->getPluginWorkDir()."/queues/channel-$channelName")){
            if(!isset($this->channels)) $this->channels = array();
            $data = AJXP_Utils::loadSerialFile($this->getPluginWorkDir()."/queues/channel-$channelName");
            if(is_array($data)) {
                if(!is_array($data["MESSAGES"])) $data["MESSAGES"] = array();
                if(!is_array($data["CLIENTS"])) $data["CLIENTS"] = array();
                $this->channels[$channelName] = $data;
                return;
            }
        }
        if($create){
            if(!isSet($this->channels)) $this->channels = array();
            $this->channels[$channelName] = array("CLIENTS" => array(),
                "MESSAGES" => array());
        }
    }

    function __destruct(){
        if(isSet($this->channels) && is_array($this->channels)){
            foreach($this->channels as $channelName => $data){
                if(is_array($data)){
                    AJXP_Utils::saveSerialFile($this->getPluginWorkDir()."/queues/channel-$channelName", $data);
                }
            }
        }
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
            $message = new stdClass();
            $update = false;
            $data = array();
            if($origNode != null){
                $update = true;
                $data[$origNode->getPath()] = $newNode;
            }else{
                $data[] = $newNode;
            }
            $content = AJXP_XMLWriter::writeNodesDiff(array(($update?"UPDATE":"ADD") => $data));
            //$this->publishToChannel("nodes:$repo", $message);
        }
        if($origNode != null && ! $update){
            $repo = $origNode->getRepositoryId();
            $message = new stdClass();
            $content = AJXP_XMLWriter::writeNodesDiff(array("REMOVE" => array($origNode->getPath())));
            //$this->publishToChannel("nodes:$repo", $message);
        }
        if(!empty($content) && !empty($repo)){

            $input = serialize(array("REPO_ID" => $repo, "CONTENT" => $content));
            $msg = WebSocketMessage::create($input);
            $client = new WebSocket("ws://192.168.1.119:8090/echo/");
            $client->open();
            $client->sendMessage($msg);
            $client->close();

        }

    }

    /**
     * @param $action
     * @param $httpVars
     * @param $fileVars
     *
     * JS SAMPLE
     *
    new PeriodicalExecuter(function(pe){
    var conn = new Connexion();
    conn.setParameters($H({get_action:'client_consume_channel',channel:'nodes:0',client_id:'toto'}));
    conn.onComplete = function(transport){ajaxplorer.actionBar.parseXmlMessage(transport.responseXML);};
    conn.sendAsync();
    }, 5);
     *
     * W
     *
     *
     *
     */

    public function clientChannelMethod($action, $httpVars, $fileVars){
        return;
        switch($action){
            case "client_register_channel":
                $this->suscribeToChannel($httpVars["channel"], $httpVars["client_id"]);
                break;
            case "client_unregister_channel":
                $this->unsuscribeFromChannel($httpVars["channel"], $httpVars["client_id"]);
                break;
            case "client_consume_channel":
                $data = $this->consumeChannel($httpVars["channel"], $httpVars["client_id"]);
                if(count($data)){
                    AJXP_XMLWriter::header();
                    ksort($data);
                    foreach($data as $messageObject){
                        echo $messageObject->content;
                    }
                    AJXP_XMLWriter::close();
                }
                break;
            default:
                break;
        }
    }

    function suscribeToChannel($channelName, $clientId){
        $this->loadChannel($channelName, true);
        $this->channels[$channelName]["CLIENTS"][$clientId] = time();
        foreach($this->channels[$channelName]["MESSAGES"] as &$object){
            $object->messageRC[$clientId] = $clientId;
        }
    }

    function unsuscribeFromChannel($channelName, $clientId){
        $this->loadChannel($channelName);
        if(!isSet($this->channels) || !isSet($this->channels[$channelName])) return;
        if(!array_key_exists($clientId,  $this->channels[$channelName]["CLIENTS"])) return;
        unset($this->channels[$channelName]["CLIENTS"][$clientId]);
        foreach($this->channels[$channelName]["MESSAGES"] as $index => &$object){
            unset($object->messageRC[$clientId]);
            if(count($object->messageRC)== 0){
                unset($this->channels[$channelName]["MESSAGES"][$index]);
            }
        }
    }

    function publishToChannel($channelName, $messageObject){
        $this->loadChannel($channelName);
        if(!isSet($this->channels) || !isSet($this->channels[$channelName])) return;
        if(!count($this->channels[$channelName]["CLIENTS"])) return;
        $messageObject->messageRC = $this->channels[$channelName]["CLIENTS"];
        $messageObject->messageTS = microtime();
        $this->channels[$channelName]["MESSAGES"][] = $messageObject;
    }

    function consumeChannel($channelName, $clientId){
        $this->loadChannel($channelName);
        if(!isSet($this->channels) || !isSet($this->channels[$channelName])) return;
        // Check dead clients
        if(is_array($this->channels[$channelName]["CLIENTS"])){
            $toRemove = array();
            foreach($this->channels[$channelName]["CLIENTS"] as $cId => $cAlive){
                if( $cId != $clientId &&  time() - $cAlive > $this->clientsGCTime * 60) $toRemove[] = $cId;
            }
            if(count($toRemove)) foreach($toRemove as $c) $this->unsuscribeFromChannel($channelName, $c);
        }
        if(!array_key_exists($clientId,  $this->channels[$channelName]["CLIENTS"])) {
            // Auto Suscribe
            $this->suscribeToChannel($channelName, $clientId);
        }
        $this->channels[$channelName]["CLIENTS"][$clientId] = time();
        $result = array();
        foreach($this->channels[$channelName]["MESSAGES"] as $index => $object){
            if(!isSet($object->messageRC[$clientId])) continue;
            $result[] = $object;
            unset($object->messageRC[$clientId]);
            if(count($object->messageRC) <= 0){
                unset($this->channels[$channelName]["MESSAGES"][$index]);
            }else{
                $this->channels[$channelName]["MESSAGES"][$index] = $object;
            }
        }
        return $result;
    }

}
