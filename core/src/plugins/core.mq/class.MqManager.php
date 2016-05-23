<?php
/*
 * Copyright 2007-2012 Charles du Jeu <contact (at) cdujeu.me>
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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Filter\AJXP_Permission;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Utils\Utils;
use Pydio\Core\Controller\XMLWriter;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\Utils\UnixProcess;

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
     conn.onComplete = function(transport){PydioApi.getClient().parseXmlMessage(transport.responseXML);};
     conn.sendAsync();
     }, 5);
 *
 * @package AjaXplorer_Plugins
 * @subpackage Core
 *
 */
class MqManager extends Plugin
{

    private $wsClient;

    /**
     * @var AJXP_MessageExchanger;
     */
    private $msgExchanger = false;
    private $useQueue = false ;
    private $hasPendingMessage = false;


    public function init($options)
    {
        parent::init($options);
        $this->useQueue = $this->pluginConf["USE_QUEUE"];
        try {
            $this->msgExchanger = ConfService::instanciatePluginFromGlobalParams($this->pluginConf["UNIQUE_MS_INSTANCE"], "AJXP_MessageExchanger");
            if(AuthService::$bufferedMessage != null && AuthService::getLoggedUser() != null){
                $this->sendInstantMessage(AuthService::$bufferedMessage, ConfService::getCurrentRepositoryId(), AuthService::getLoggedUser()->getId());
                AuthService::$bufferedMessage = null;
            }
        } catch (Exception $e) {}
    }


    public function sendToQueue(AJXP_Notification $notification)
    {
        if (!$this->useQueue) {
            $this->logDebug("SHOULD DISPATCH NOTIFICATION ON ".$notification->getNode()->getUrl()." ACTION ".$notification->getAction());
            Controller::applyHook("msg.notification", array(&$notification));
        } else {
            if($this->msgExchanger) $this->msgExchanger->publishWorkerMessage("user_notifications", $notification);
        }
    }

    public function consumeQueue($action, $httpVars, $fileVars)
    {
        if($action != "consume_notification_queue" || $this->msgExchanger === false) return;
        $queueObjects = $this->msgExchanger->consumeWorkerChannel("user_notifications");
        if (is_array($queueObjects)) {
            $this->logDebug("Processing notification queue, ".count($queueObjects)." notifs to handle");
            foreach ($queueObjects as $notification) {
                Controller::applyHook("msg.notification", array(&$notification));
            }
        }
    }

    /**
     * @param AJXP_Node $origNode
     * @param AJXP_Node $newNode
     * @param bool $copy
     */
    public function publishNodeChange($origNode = null, $newNode = null, $copy = false)
    {
        $content = "";$repo = "";$targetUserId=null; $nodePathes = array();
        $update = false;
        if ($newNode != null) {
            $repo = $newNode->getRepositoryId();
            $targetUserId = $newNode->getUser();
            $nodePathes[] = $newNode->getPath();
            $update = false;
            $data = array();
            if ($origNode != null && !$copy) {
                $update = true;
                $data[$origNode->getPath()] = $newNode;
            } else {
                $data[] = $newNode;
            }
            $content = XMLWriter::writeNodesDiff(array(($update?"UPDATE":"ADD") => $data));
        }
        if ($origNode != null && ! $update && !$copy) {

            $repo = $origNode->getRepositoryId();
            $targetUserId = $origNode->getUser();
            $nodePathes[] = $origNode->getPath();
            $content = XMLWriter::writeNodesDiff(array("REMOVE" => array($origNode->getPath())));

        }
        if (!empty($content) && $repo != "") {

            $this->sendInstantMessage($content, $repo, $targetUserId, null, $nodePathes);

        }

    }

    public function sendInstantMessage($xmlContent, $repositoryId, $targetUserId = null, $targetGroupPath = null, $nodePathes = array())
    {
        if ($repositoryId === AJXP_REPO_SCOPE_ALL) {
            $userId = $targetUserId;
        } else {
            $scope = ConfService::getRepositoryById($repositoryId)->securityScope();
            if ($scope == "USER") {
                if($targetUserId) $userId = $targetUserId;
                else $userId = AuthService::getLoggedUser()->getId();
            } else if ($scope == "GROUP") {
                $gPath = AuthService::getLoggedUser()->getGroupPath();
            } else if (isSet($targetUserId)) {
                $userId = $targetUserId;
            } else if (isSet($targetGroupPath)) {
                $gPath = $targetGroupPath;
            }
        }

        // Publish for pollers
        $message = new stdClass();
        $message->content = $xmlContent;
        if(isSet($userId)) {
            $message->userId = $userId;
        }
        if(isSet($gPath)) {
            $message->groupPath = $gPath;
        }
        if(count($nodePathes)) {
            $message->nodePathes = $nodePathes;
        }

        if ($this->msgExchanger) {
            $this->msgExchanger->publishInstantMessage("nodes:$repositoryId", $message);
        }

        /*
        // Publish on NSQ
        require_once(AJXP_INSTALL_PATH."core/classes/vendor/autoload.php");
        $nsq = new nsqphp\nsqphp;
        $nsq->publishTo("localhost", 1);
        $nsq->publish('pydio', new nsqphp\Message\Message(json_encode(array('msg' => 'im', 'run' => $message))));
        */

        // Publish for WebSockets
        $configs = $this->getConfigs();
        if ($configs["WS_SERVER_ACTIVE"]) {

            require_once($this->getBaseDir()."/vendor/phpws/websocket.client.php");
            // Publish for websockets
            $input = array("REPO_ID" => $repositoryId, "CONTENT" => "<tree>".$xmlContent."</tree>");
            if(isSet($userId)){
                $input["USER_ID"] = $userId;
            } else if(isSet($gPath)) {
                $input["GROUP_PATH"] = $gPath;
            }
            if(count($nodePathes)) {
                $input["NODE_PATHES"] = $nodePathes;
            }
            $input = serialize($input);
            $msg = WebSocketMessage::create($input);
            if (!isset($this->wsClient)) {
                $this->wsClient = new WebSocket("ws://".$configs["WS_SERVER_BIND_HOST"].":".$configs["WS_SERVER_BIND_PORT"].$configs["WS_SERVER_PATH"]);
                $this->wsClient->addHeader("Admin-Key", $configs["WS_SERVER_ADMIN"]);
                @$this->wsClient->open();
            }
            @$this->wsClient->sendMessage($msg);
        }

        $this->hasPendingMessage = true;

    }

    public function appendRefreshInstruction(ResponseInterface &$responseInterface){
        if(! $this->hasPendingMessage ){
            return;
        }
        $respType = &$responseInterface->getBody();
        if(!$respType instanceof \Pydio\Core\Http\Response\SerializableResponseStream && !$respType->getSize()){
            $respType = new \Pydio\Core\Http\Response\SerializableResponseStream();
            $responseInterface = $responseInterface->withBody($respType);
        }
        if($respType instanceof \Pydio\Core\Http\Response\SerializableResponseStream){
            require_once("ConsumeChannelMessage.php");
            $respType->addChunk(new ConsumeChannelMessage());
        }
    }

    /**
     * @param $request
     * @param $response
     */
    public function clientChannelMethod(ServerRequestInterface $request, ResponseInterface &$response)
    {
        if(!$this->msgExchanger) return;
        $action = $request->getAttribute("action");
        $httpVars = $request->getParsedBody();
        switch ($action) {
            case "client_register_channel":
                $this->msgExchanger->suscribeToChannel($httpVars["channel"], $httpVars["client_id"]);
                break;
            case "client_unregister_channel":
                $this->msgExchanger->unsuscribeFromChannel($httpVars["channel"], $httpVars["client_id"]);
                break;
            case "client_consume_channel":
                if (AuthService::usersEnabled()) {
                    $user = AuthService::getLoggedUser();
                    if ($user == null) {
                        throw new \Pydio\Core\Exception\AuthRequiredException();
                    }
                    $GROUP_PATH = $user->getGroupPath();
                    if($GROUP_PATH == null) $GROUP_PATH = false;
                    $uId = $user->getId();
                } else {
                    $GROUP_PATH = '/';
                    $uId = 'shared';
                }
                $currentRepository = ConfService::getCurrentRepositoryId();
                $currentRepoMasks = array(); $regexp = null;
                Controller::applyHook("role.masks", array($currentRepository, &$currentRepoMasks, AJXP_Permission::READ));
                if(count($currentRepoMasks)){
                    $regexps = array();
                    foreach($currentRepoMasks as $path){
                        $regexps[] = '^'.preg_quote($path, '/');
                    }
                    $regexp = '/'.implode("|", $regexps).'/';
                }
                $channelRepository = str_replace("nodes:", "", $httpVars["channel"]);
                $serialBody = new \Pydio\Core\Http\Response\SerializableResponseStream();
                $response = $response->withBody($serialBody);
                if($channelRepository != $currentRepository){
                    $serialBody->addChunk(new \Pydio\Core\Http\Message\XMLMessage("<require_registry_reload repositoryId=\"$currentRepository\"/>"));
                    return;
                }

                $data = $this->msgExchanger->consumeInstantChannel($httpVars["channel"], $httpVars["client_id"], $uId, $GROUP_PATH);
                if (count($data)) {
                   ksort($data);
                   foreach ($data as $messageObject) {
                       if(isSet($regexp) && isSet($messageObject->nodePathes)){
                           $pathIncluded = false;
                           foreach($messageObject->nodePathes as $nodePath){
                               if(preg_match($regexp, $nodePath)){
                                   $pathIncluded = true;
                                   break;
                               }
                           }
                           if(!$pathIncluded) continue;
                       }
                       $serialBody->addChunk(new \Pydio\Core\Http\Message\XMLMessage($messageObject->content));
                   }
                }

                break;
            default:
                break;
        }
    }

    public function wsAuthenticate($action, $httpVars, $fileVars)
    {
        $this->logDebug("Entering wsAuthenticate");
        $configs = $this->getConfigs();
        /*if (!isSet($httpVars["key"]) || $httpVars["key"] != $configs["WS_SERVER_ADMIN"]) {
            throw new Exception("Cannot authentify admin key");
        }*/
        $user = AuthService::getLoggedUser();
        if ($user == null) {
            $this->logDebug("Error Authenticating through WebSocket (not logged)");
            throw new Exception("You must be logged in");
        }
        $xml = XMLWriter::getUserXML($user);
        // add groupPath
        if ($user->getGroupPath() != null) {
            $groupString = "groupPath=\"".Utils::xmlEntities($user->getGroupPath())."\"";
            $xml = str_replace("<user id=", "<user {$groupString} id=", $xml);
        }
        $this->logDebug("Authenticating user ".$user->id." through WebSocket");
        XMLWriter::header();
        echo $xml;
        XMLWriter::close();

    }

    public function switchWebSocketOn($params)
    {
        $wDir = $this->getPluginWorkDir(true);
        $pidFile = $wDir.DIRECTORY_SEPARATOR."ws-pid";
        if (file_exists($pidFile)) {
            $pId = file_get_contents($pidFile);
            $unixProcess = new UnixProcess();
            $unixProcess->setPid($pId);
            $status = $unixProcess->status();
            if ($status) {
                throw new Exception("Web Socket server seems to already be running!");
            }
        }
        $host = escapeshellarg($params["WS_SERVER_BIND_HOST"]);
        $port = escapeshellarg($params["WS_SERVER_BIND_PORT"]);
        $path = escapeshellarg($params["WS_SERVER_PATH"]);
        $cmd = ConfService::getCoreConf("CLI_PHP")." ws-server.php -host=".$host." -port=".$port." -path=".$path;
        chdir(AJXP_INSTALL_PATH.DIRECTORY_SEPARATOR.AJXP_PLUGINS_FOLDER.DIRECTORY_SEPARATOR."core.mq");
        $process = Controller::runCommandInBackground($cmd, null);
        if ($process != null) {
            $pId = $process->getPid();
            $wDir = $this->getPluginWorkDir(true);
            file_put_contents($wDir.DIRECTORY_SEPARATOR."ws-pid", $pId);
            return "SUCCESS: Started WebSocket Server with process ID $pId";
        }
        return "SUCCESS: Started WebSocket Server";
    }

    public function switchWebSocketOff($params)
    {
        $wDir = $this->getPluginWorkDir(true);
        $pidFile = $wDir.DIRECTORY_SEPARATOR."ws-pid";
        if (!file_exists($pidFile)) {
            throw new Exception("No information found about WebSocket server");
        } else {
            $pId = file_get_contents($pidFile);
            $unixProcess = new UnixProcess();
            $unixProcess->setPid($pId);
            $unixProcess->stop();
            unlink($pidFile);
        }
        return "SUCCESS: Killed WebSocket Server";
    }

    public function getWebSocketStatus()
    {
        $wDir = $this->getPluginWorkDir(true);
        $pidFile = $wDir.DIRECTORY_SEPARATOR."ws-pid";
        if (!file_exists($pidFile)) {
            return "OFF";
        } else {
            $pId = file_get_contents($pidFile);
            $unixProcess = new UnixProcess();
            $unixProcess->setPid($pId);
            $status = $unixProcess->status();
            if($status) return "ON";
            else return "OFF";
        }

    }

}
