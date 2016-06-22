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
namespace Pydio\Mq\Implementation;

use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\Utils;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Notification\Core\IMessageExchanger;

defined('AJXP_EXEC') or die('Access not allowed');
/**
 * Serialized file plugin to manage messages queues
 * @package AjaXplorer_Plugins
 * @subpackage Mq
 */
class SerialMessageExchanger extends Plugin implements IMessageExchanger
{

    /**
     * @var array
     */
    private $channels;
    private $clientsGCTime = 10;

    /**
     * @param $channelName
     * @param bool $create
     * @throws \Exception
     */
    public function loadChannel($channelName, $create = false)
    {
        if (isSet($this->channels) && is_array($this->channels[$channelName])) {
            return;
        }
        if (is_file($this->getPluginWorkDir()."/queues/channel-$channelName")) {
            if(!isset($this->channels)) $this->channels = array();
            $data = Utils::loadSerialFile($this->getPluginWorkDir()."/queues/channel-$channelName");
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
                    Utils::saveSerialFile($this->getPluginWorkDir()."/queues/channel-$channelName", $data);
                }
            }
        }
    }


    /**
     * @param ContextInterface $ctx
     * @param $channelName
     * @param $clientId
     * @throws \Exception
     * @return mixed
     */
    public function suscribeToChannel(ContextInterface $ctx, $channelName, $clientId)
    {
        $this->loadChannel($channelName, true);
        if (UsersService::usersEnabled()) {
            $user = $ctx->getUser();
            if ($user == null) {
                throw new \Exception("You must be logged in");
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
     * @param ContextInterface $ctx
     * @param $channelName
     * @param $clientId
     * @return mixed
     */
    public function unsuscribeFromChannel(ContextInterface $ctx, $channelName, $clientId)
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

    /**
     * @param ContextInterface $ctx
     * @param $channelName
     * @param $messageObject
     */
    public function publishToChannel(ContextInterface $ctx, $channelName, $messageObject)
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
     * @param ContextInterface $ctx
     * @param $channelName
     * @param $clientId
     * @param $userId
     * @param $userGroup
     * @return mixed
     */
    public function consumeInstantChannel(ContextInterface $ctx, $channelName, $clientId, $userId, $userGroup)
    {
        // Force refresh
        //$this->channels = null;
        $this->loadChannel($channelName);
        if(!isSet($this->channels) || !isSet($this->channels[$channelName])) {
            return null;
        }
        // Check dead clients
        if (is_array($this->channels[$channelName]["CLIENTS"])) {
            $toRemove = array();
            foreach ($this->channels[$channelName]["CLIENTS"] as $cId => $cData) {
                $cAlive = $cData["ALIVE"];
                if( $cId != $clientId &&  time() - $cAlive > $this->clientsGCTime * 60) $toRemove[] = $cId;
            }
            if(count($toRemove)) {
                foreach($toRemove as $c) {
                    $this->unsuscribeFromChannel($ctx, $channelName, $c);
                }
            }
        }
        if (!array_key_exists($clientId,  $this->channels[$channelName]["CLIENTS"])) {
            // Auto Suscribe
            $this->suscribeToChannel($ctx, $channelName, $clientId);
        }
        $this->channels[$channelName]["CLIENTS"][$clientId]["ALIVE"] = time();
        
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
     * @param ContextInterface $ctx
     * @param $channelName
     * @param $filter
     * @return mixed
     */
    public function consumeWorkerChannel(ContextInterface $ctx, $channelName, $filter = null)
    {
        $data = array();
        if (file_exists($this->getPluginWorkDir()."/worker/$channelName.ser")) {
            $data = unserialize(file_get_contents($this->getPluginWorkDir()."/worker/$channelName.ser"));
            file_put_contents($this->getPluginWorkDir()."/worker/$channelName.ser", array(), LOCK_EX);
        }
        return $data;
    }

    /**
     * @param ContextInterface $ctx
     * @param string $channel Name of the persistant queue to create
     * @param object $message Message to send
     * @return mixed
     */
    public function publishWorkerMessage(ContextInterface $ctx, $channel, $message)
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
     * @param ContextInterface $ctx
     * @param $channel
     * @param $message
     * @return Object
     */
    public function publishInstantMessage(ContextInterface $ctx, $channel, $message)
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
