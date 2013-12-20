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
 * Serialized file plugin to manage messages queues
 * @package AjaXplorer_Plugins
 * @subpackage Mq
 */
class AJXP_SerialMessageExchanger extends AJXP_Plugin implements AJXP_MessageExchanger
{

    /**
     * @var Array
     */
    private $channels;
    private $clientsGCTime = 10;

    public function loadChannel($channelName, $create = false)
    {
        if (isSet($this->channels) && is_array($this->channels[$channelName])) {
            return;
        }
        if (is_file($this->getPluginWorkDir()."/queues/channel-$channelName")) {
            if(!isset($this->channels)) $this->channels = array();
            $data = AJXP_Utils::loadSerialFile($this->getPluginWorkDir()."/queues/channel-$channelName");
            if (is_array($data)) {
                if(!is_array($data["MESSAGES"])) $data["MESSAGES"] = array();
                if(!is_array($data["CLIENTS"])) $data["CLIENTS"] = array();
                $this->channels[$channelName] = $data;
                return;
            }
        }
        if ($create) {
            if(!isSet($this->channels)) $this->channels = array();
            $this->channels[$channelName] = array("CLIENTS" => array(),
                "MESSAGES" => array());
        }
    }

    public function __destruct()
    {
        if (isSet($this->channels) && is_array($this->channels)) {
            foreach ($this->channels as $channelName => $data) {
                if (is_array($data)) {
                    AJXP_Utils::saveSerialFile($this->getPluginWorkDir()."/queues/channel-$channelName", $data);
                }
            }
        }
    }


    /**
     * @param $channelName
     * @param $clientId
     * @throws Exception
     * @return mixed
     */
    public function suscribeToChannel($channelName, $clientId)
    {
        $this->loadChannel($channelName, true);
        if (AuthService::usersEnabled()) {
            $user = AuthService::getLoggedUser();
            if ($user == null) {
                throw new Exception("You must be logged in");
            }
            $GROUP_PATH = $user->getGroupPath();
            $USER_ID = $user->getId();
        } else {
            $GROUP_PATH = '/';
            $USER_ID = 'shared';
        }
        if($GROUP_PATH == null) $GROUP_PATH = false;
        $this->channels[$channelName]["CLIENTS"][$clientId] = array(
            "ALIVE" => time(),
            "USER_ID" => $USER_ID,
            "GROUP_PATH" => $GROUP_PATH
        );
        foreach ($this->channels[$channelName]["MESSAGES"] as &$object) {
            $object->messageRC[$clientId] = $clientId;
        }
    }

    /**
     * @param $channelName
     * @param $clientId
     * @return mixed
     */
    public function unsuscribeFromChannel($channelName, $clientId)
    {
        $this->loadChannel($channelName);
        if(!isSet($this->channels) || !isSet($this->channels[$channelName])) return;
        if(!array_key_exists($clientId,  $this->channels[$channelName]["CLIENTS"])) return;
        unset($this->channels[$channelName]["CLIENTS"][$clientId]);
        foreach ($this->channels[$channelName]["MESSAGES"] as $index => &$object) {
            unset($object->messageRC[$clientId]);
            if (count($object->messageRC)== 0) {
                unset($this->channels[$channelName]["MESSAGES"][$index]);
            }
        }
    }

    public function publishToChannel($channelName, $messageObject)
    {
        $this->loadChannel($channelName);
        if(!isSet($this->channels) || !isSet($this->channels[$channelName])) return;
        if(!count($this->channels[$channelName]["CLIENTS"])) return;
        $clientIds = array_keys($this->channels[$channelName]["CLIENTS"]);
        $messageObject->messageRC = array_combine($clientIds, $clientIds);
        $messageObject->messageTS = microtime();
        $this->channels[$channelName]["MESSAGES"][] = $messageObject;
    }

    /**
     * @param $channelName
     * @param $clientId
     * @param $userId
     * @param $userGroup
     * @return mixed
     */
    public function consumeInstantChannel($channelName, $clientId, $userId, $userGroup)
    {
        // Force refresh
        //$this->channels = null;
        $this->loadChannel($channelName);
        if(!isSet($this->channels) || !isSet($this->channels[$channelName])) return;
        // Check dead clients
        if (is_array($this->channels[$channelName]["CLIENTS"])) {
            $toRemove = array();
            foreach ($this->channels[$channelName]["CLIENTS"] as $cId => $cData) {
                $cAlive = $cData["ALIVE"];
                if( $cId != $clientId &&  time() - $cAlive > $this->clientsGCTime * 60) $toRemove[] = $cId;
            }
            if(count($toRemove)) foreach($toRemove as $c) $this->unsuscribeFromChannel($channelName, $c);
        }
        if (!array_key_exists($clientId,  $this->channels[$channelName]["CLIENTS"])) {
            // Auto Suscribe
            $this->suscribeToChannel($channelName, $clientId);
        }
        $this->channels[$channelName]["CLIENTS"][$clientId]["ALIVE"] = time();

        //$user = AuthService::getLoggedUser();
       // if ($user == null) {
       //     throw new Exception("You must be logged in");
       // }
        //$GROUP_PATH = $user->getGroupPath();
       // if($GROUP_PATH == null) $GROUP_PATH = false;

        $result = array();
        foreach ($this->channels[$channelName]["MESSAGES"] as $index => $object) {
            if (!isSet($object->messageRC[$clientId])) {
                continue;
            }
            if (isSet($object->userId) && $object->userId != $userId) {
                // Skipping, restricted to userId
                continue;
            }
            if (isSet($object->groupPath) && $object->groupPath != $userGroup) {
                // Skipping, restricted to groupPath
                continue;
            }
            $result[] = $object;
            unset($object->messageRC[$clientId]);
            if (count($object->messageRC) <= 0) {
                unset($this->channels[$channelName]["MESSAGES"][$index]);
            } else {
                $this->channels[$channelName]["MESSAGES"][$index] = $object;
            }
        }
        return $result;
    }





    /**
     * @param $channelName
     * @param $filter
     * @return mixed
     */
    public function consumeWorkerChannel($channelName, $filter = null)
    {
        $data = array();
        if (file_exists($this->getPluginWorkDir()."/worker/$channelName.ser")) {
            $data = unserialize(file_get_contents($this->getPluginWorkDir()."/worker/$channelName.ser"));
            file_put_contents($this->getPluginWorkDir()."/worker/$channelName.ser", array(), LOCK_EX);
        }
        return $data;
    }

    /**
     * @param string $channel Name of the persistant queue to create
     * @param object $message Message to send
     * @return mixed
     */
    public function publishWorkerMessage($channel, $message)
    {
        $data = array();
        $fExists = false;
        if (file_exists($this->getPluginWorkDir()."/worker/$channel.ser")) {
            $fExists = true;
            $data = unserialize(file_get_contents($this->getPluginWorkDir()."/worker/$channel.ser"));
        }
        $data[] = $message;
        if (!$fExists) {
            if (!is_dir($this->getPluginWorkDir()."/queues")) {
                mkdir($this->getPluginWorkDir()."/queues", 0755, true);
            }
        }
        $res = file_put_contents($this->getPluginWorkDir()."/worker/$channel.ser", serialize($data), LOCK_EX);
        return $res;
    }

    /**
     * @param $channel
     * @param $message
     * @return Object
     */
    public function publishInstantMessage($channel, $message)
    {
        $this->loadChannel($channel);
        if(!isSet($this->channels) || !isSet($this->channels[$channel])) return;
        if(!count($this->channels[$channel]["CLIENTS"])) return;
        $clientIds = array_keys($this->channels[$channel]["CLIENTS"]);
        $message->messageRC = array_combine($clientIds, $clientIds);
        $message->messageTS = microtime();
        $this->channels[$channel]["MESSAGES"][] = $message;
    }
}
