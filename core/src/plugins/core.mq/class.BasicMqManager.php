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



class BasicMqManager extends AJXP_Plugin
{

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
        if($newNode != null) {
            $repo = $newNode->getRepositoryId();
            $message = new stdClass();
            $update = false;
            if($origNode != null && $origNode->getPath() == $newNode->getPath()){
                $update = true;
            }
            $message->content = AJXP_XMLWriter::writeNodesDiff(array(($update?"UPDATE":"ADD") => array($newNode)));
            $this->publishToChannel("nodes:$repo", $message);
            if($update) return;
        }
        if($origNode != null){
            $repo = $origNode->getRepositoryId();
            $message = new stdClass();
            $message->content = AJXP_XMLWriter::writeNodesDiff(array("REMOVE" => array($origNode->getPath())));
            $this->publishToChannel("nodes:$repo", $message);
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
     */

    public function clientChannelMethod($action, $httpVars, $fileVars){
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
        $this->channels[$channelName]["CLIENTS"][$clientId] = $clientId;
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
        if(!array_key_exists($clientId,  $this->channels[$channelName]["CLIENTS"])) return;
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
