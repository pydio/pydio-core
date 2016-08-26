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
namespace Pydio\Mq\Core;

use nsqphp\Message\Message;
use nsqphp\nsqphp;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Filter\AJXP_Permission;
use Pydio\Core\Controller\CliRunner;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Exception\AuthRequiredException;
use Pydio\Core\Http\Message\XMLMessage;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Serializer\UserXML;
use Pydio\Core\Services\ApiKeysService;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\ApplicationState;
use Pydio\Core\Utils\Vars\StringHelper;

use Pydio\Core\Controller\XMLWriter;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\Controller\UnixProcess;
use Pydio\Notification\Core\IMessageExchanger;
use Pydio\Notification\Core\Notification;

defined('AJXP_EXEC') or die( 'Access not allowed');

// DL and install install vendor (composer?) https://github.com/Devristo/phpws


/**
 * MqManager
 *
 * @package AjaXplorer_Plugins
 * @subpackage Core
 *
 */
class MqManager extends Plugin
{

    /**
     * @var nsqphp
     */
    private $nsqClient;

    /**
     * @var IMessageExchanger;
     */
    private $msgExchanger = false;
    private $useQueue = false ;
    private $hasPendingMessage = false;


    /**
     * @param ContextInterface $ctx
     * @param array $options
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        parent::init($ctx, $options);
        $this->useQueue = $this->pluginConf["USE_QUEUE"];
        try {
            $pService = PluginsService::getInstance($ctx);
            $this->msgExchanger = ConfService::instanciatePluginFromGlobalParams($this->pluginConf["UNIQUE_MS_INSTANCE"], "Pydio\\Notification\\Core\\IMessageExchanger", $pService);
            if(!empty($this->msgExchanger)){
                $pService->setPluginActive($this->msgExchanger->getType(), $this->msgExchanger->getName(), true, $this->msgExchanger);
            }
            if(AuthService::$bufferedMessage != null && $ctx->hasUser()){
                $this->sendInstantMessage($ctx, AuthService::$bufferedMessage, $ctx->getUser()->getId());
                AuthService::$bufferedMessage = null;
            }
        } catch (\Exception $e) {}
    }


    /**
     * @param Notification $notification
     * @throws \Exception
     */
    public function sendToQueue(Notification $notification)
    {
        if (!$this->useQueue) {
            $this->logDebug("SHOULD DISPATCH NOTIFICATION ON ".$notification->getNode()->getUrl()." ACTION ".$notification->getAction());
            Controller::applyHook("msg.notification", array(&$notification));
        } else {
            if($this->msgExchanger) {
                $this->msgExchanger->publishWorkerMessage(
                    $notification->getNode()->getContext(),
                    "user_notifications",
                    $notification);
            }
        }
    }

    /**
     * @param $action
     * @param $httpVars
     * @param $fileVars
     * @param ContextInterface $ctx
     * @throws \Exception
     */
    public function consumeQueue($action, $httpVars, $fileVars, ContextInterface $ctx)
    {
        if($action != "consume_notification_queue" || $this->msgExchanger === false) return;
        $queueObjects = $this->msgExchanger->consumeWorkerChannel($ctx, "user_notifications");
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
        $content = "";$targetUserId=null; $nodePaths = array();
        $update = false;
        $ctx = null;
        if ($newNode != null) {
            $ctx = $newNode->getContext();
            $targetUserId = $newNode->getUserId();
            $nodePaths[] = $newNode->getPath();
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

            $ctx = $origNode->getContext();
            $targetUserId = $origNode->getUserId();
            $nodePaths[] = $origNode->getPath();
            $content = XMLWriter::writeNodesDiff(array("REMOVE" => array($origNode->getPath())));

        }
        if (!empty($content) && !empty($ctx)) {

            $this->sendInstantMessage($ctx, $content, $targetUserId, null, $nodePaths);

        }

    }

    /**
     * @param ContextInterface $ctx
     * @param $xmlContent
     * @param null $targetUserId
     * @param null $targetGroupPath
     * @param array $nodePaths
     */
    public function sendInstantMessage(ContextInterface $ctx, $xmlContent, $targetUserId = null, $targetGroupPath = null, $nodePaths = array())
    {
        $currentUser = $ctx->getUser();
        $repositoryId = $ctx->getRepositoryId();

        if (!$ctx->hasRepository()) {
            $userId = $targetUserId;
        } else {
            $scope = RepositoryService::getRepositoryById($repositoryId)->securityScope();
            if ($scope == "USER") {
                if($targetUserId) $userId = $targetUserId;
                else $userId = $currentUser->getId();
            } else if ($scope == "GROUP") {
                $gPath = $currentUser->getGroupPath();
            } else if (isSet($targetUserId)) {
                $userId = $targetUserId;
            } else if (isSet($targetGroupPath)) {
                $gPath = $targetGroupPath;
            }
        }

        // Publish for pollers
        $message = new \stdClass();
        $message->content = $xmlContent;
        if(isSet($userId)) {
            $message->userId = $userId;
        }
        if(isSet($gPath)) {
            $message->groupPath = $gPath;
        }
        if(count($nodePaths)) {
            $message->nodePaths = $nodePaths;
        }

        if ($this->msgExchanger) {
            $this->msgExchanger->publishInstantMessage($ctx, "nodes:$repositoryId", $message);
        }

        // Publish for websockets
        $input = array("REPO_ID" => "$repositoryId", "CONTENT" => "<tree>".$xmlContent."</tree>");
        if(isSet($userId)){
            $input["USER_ID"] = "$userId";
        } else if(isSet($gPath)) {
            $input["GROUP_PATH"] = "$gPath";
        }
        if(count($nodePaths)) {
            $input["NODE_PATHES"] = $nodePaths;
        }

        $this->_sendMessage($ctx, 'im', json_encode($input));

        $this->hasPendingMessage = true;
    }

    /**
     * @param ContextInterface $ctx
     * @param $content
     */
    public function sendTaskMessage(ContextInterface $ctx, $content){

        $this->logInfo("Core.mq", "Should now publish a message to NSQ :". json_encode($content));

        $this->_sendMessage($ctx, 'task', json_encode($content));
    }

    private function _sendMessage(ContextInterface $ctx, $topic, $content) {

        $host = $this->getContextualOption($ctx, "NSQ_HOST");
        $port = $this->getContextualOption($ctx, "NSQ_PORT");

        if(!empty($host) && !empty($port)){

            if(empty($this->nsqClient)){

                // Publish on NSQ
                $this->nsqClient = new nsqphp;
                $this->nsqClient->publishTo(join(":", [$host, $port]), 1);

                $this->logInfo("core.mq", "Published to NSQ " .$topic." :". $content);
            }

            set_error_handler(function ($errNo, $str) use (&$msg) { $msg = $str; });
            try {
                $this->nsqClient->publish($topic, new Message($content));
            } catch (\Exception $e) {

                $this->logError("core.mq", "sendMessage " . $topic, $e->getMessage());

                if(ConfService::currentContextIsCommandLine()){
                    print("Error while trying to send a ".$topic." message ".$content." : ".$e->getMessage());
                }
            }
            restore_error_handler();
        }
    }

    /**
     * @param ContextInterface $ctx
     * @param ResponseInterface $responseInterface
     */
    public function appendRefreshInstruction(ContextInterface $ctx, ResponseInterface &$responseInterface){
        if(! $this->hasPendingMessage ){
            return;
        }
        $respType = &$responseInterface->getBody();
        if(!$respType instanceof SerializableResponseStream && !$respType->getSize()){
            $respType = new SerializableResponseStream();
            $responseInterface = $responseInterface->withBody($respType);
        }
        if($respType instanceof SerializableResponseStream){
            require_once("ConsumeChannelMessage.php");
            $respType->addChunk(new ConsumeChannelMessage());
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @throws \Pydio\Core\Exception\AuthRequiredException
     */
    public function clientChannelMethod(ServerRequestInterface $request, ResponseInterface &$response)
    {
        if(!$this->msgExchanger) return;
        $action = $request->getAttribute("action");
        $httpVars = $request->getParsedBody();

        /** @var \Pydio\Core\Model\ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");

        switch ($action) {
            case "client_register_channel":
                
                $this->msgExchanger->suscribeToChannel($ctx, $httpVars["channel"], $httpVars["client_id"]);
                break;
            
            case "client_unregister_channel":
                
                $this->msgExchanger->unsuscribeFromChannel($ctx, $httpVars["channel"], $httpVars["client_id"]);
                break;
            
            case "client_consume_channel":

                if (UsersService::usersEnabled()) {
                    $user = $ctx->getUser();
                    if ($user == null) {
                        throw new AuthRequiredException();
                    }
                    $GROUP_PATH = $user->getGroupPath();
                    if($GROUP_PATH == null) $GROUP_PATH = false;
                    $uId = $user->getId();
                } else {
                    $GROUP_PATH = '/';
                    $uId = 'shared';
                }

                $currentRepository = $ctx->getRepositoryId();
                $currentRepoMasks = array(); $regexp = null;
                Controller::applyHook("role.masks", array($ctx, &$currentRepoMasks, AJXP_Permission::READ));
                if(count($currentRepoMasks)){
                    $regexps = array();
                    foreach($currentRepoMasks as $path){
                        $regexps[] = '^'.preg_quote($path, '/');
                    }
                    $regexp = '/'.implode("|", $regexps).'/';
                }

                $channelRepository = str_replace("nodes:", "", $httpVars["channel"]);
                $serialBody = new SerializableResponseStream();
                $response = $response->withBody($serialBody);
                if($channelRepository != $currentRepository){
                    $serialBody->addChunk(new XMLMessage("<require_registry_reload repositoryId=\"$currentRepository\"/>"));
                    return;
                }

                $data = $this->msgExchanger->consumeInstantChannel($ctx, $httpVars["channel"], $httpVars["client_id"], $uId, $GROUP_PATH);
                if (count($data)) {
                   ksort($data);
                   foreach ($data as $messageObject) {
                       if(isSet($regexp) && isSet($messageObject->nodePaths)){
                           $pathIncluded = false;
                           foreach($messageObject->nodePaths as $nodePath){
                               if(preg_match($regexp, $nodePath)){
                                   $pathIncluded = true;
                                   break;
                               }
                           }
                           if(!$pathIncluded) continue;
                       }
                       $serialBody->addChunk(new XMLMessage($messageObject->content));
                   }
                }

                break;
            default:
                break;
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @throws \Exception
     */
    public function wsAuthenticate(ServerRequestInterface $request, ResponseInterface &$response)
    {
        $this->logDebug("Entering wsAuthenticate");

        $configs = $this->getConfigs();
        $httpVars = $request->getQueryParams();
        if (!isSet($httpVars["key"]) || $httpVars["key"] != $configs["WS_SERVER_ADMIN"]) {
            throw new \Exception("Cannot authentify admin key");
        }

        /** @var \Pydio\Core\Model\ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");
        $user = $ctx->getUser();
        if ($user == null) {
            $this->logDebug("Error Authenticating through WebSocket (not logged)");
            throw new \Exception("You must be logged in");
        }

        $serializer = new UserXML();
        $xml = $serializer->serialize($ctx);
        // add groupPath
        if ($user->getGroupPath() != null) {
            $groupString = "groupPath=\"". StringHelper::xmlEntities($user->getGroupPath()) ."\"";
            $xml = str_replace("<user id=", "<user {$groupString} id=", $xml);
        }

        $this->logDebug("Authenticating user ".$user->getId()." through WebSocket");
        $x = new SerializableResponseStream();
        $x->addChunk(new XMLMessage($xml));
        $response = $response->withBody($x);

    }

    /**
     * @param $params
     * @return string
     * @throws \Exception
     */
    public function switchWorkerOn($params)
    {
        $wDir = $this->getPluginWorkDir(true);
        $pidFile = $wDir.DIRECTORY_SEPARATOR."worker-pid";
        if (file_exists($pidFile)) {
            $pId = file_get_contents($pidFile);
            $unixProcess = new UnixProcess();
            $unixProcess->setPid($pId);
            $status = $unixProcess->status();
            if ($status) {
                throw new \Exception("Worker seems to already be running!");
            }
        }
        $cmd = ConfService::getGlobalConf("CLI_PHP")." worker.php";
        chdir(AJXP_INSTALL_PATH);
        $process = CliRunner::runCommandInBackground($cmd, AJXP_CACHE_DIR . "/cmd_outputs/worker.log");
        if ($process != null) {
            $pId = $process->getPid();
            file_put_contents($pidFile, $pId);
            return "SUCCESS: Started worker with process ID $pId";
        }
        return "SUCCESS: Started worker Server";
    }

    /**
     * @param $params
     * @return string
     * @throws \Exception
     */
    public function switchWorkerOff($params){
        return $this->switchOff($params, "worker");
    }

    /**
     * @param $params
     * @return string
     */
    public function getWorkerStatus($params){
        return $this->getStatus($params, "worker");
    }

    // Handler testing the generation of the caddy file to spot any error
    /**
     * @param $params
     * @return string
     */
    public function getCaddyFile($params) {
        $error = "OK";

        set_error_handler(function ($e) use (&$error) {
            $error = $e;
        }, E_WARNING);

        $data = $this->generateCaddyFile($params);

        // Generate the caddyfile
        file_put_contents("/tmp/testcaddy", $data);

        restore_error_handler();

        return $error;
    }

    /**
     * @param $params
     * @return string
     */
    public function generateCaddyFile($params) {
        $data = "";

        $hosts = [];

        $configs = $this->getConfigs();

        // Getting URLs of the Pydio system
        $serverURL = ApplicationState::detectServerURL();
        $tokenURL = $serverURL . "?get_action=keystore_generate_auth_token";

        // Websocket Server Config
        $active = $params["WS_ACTIVE"];

        if ($active) {

            $authURL = $serverURL . "/api/pydio/ws_authenticate?key=" . $configs["WS_SERVER_ADMIN"];

            $host = $params["WS_HOST"];
            $port = $params["WS_PORT"];
            $secure = $params["WS_SECURE"];
            $path = "/" . trim($params["WS_PATH"], "/");

            $key = "http" . ($secure ? "s" : "") . "://" . $host . ":" . $port;
            $hosts[$key] = array_merge(
                (array)$hosts[$key],
                [
                    "pydioauth " . $path => [$tokenURL . "&device=websocket"],
                    "pydiopre " . $path => [$authURL],
                    "pydiows " . $path => []
                ]
            );
        }

        // Upload Server Config
        $active = $params["UPLOAD_ACTIVE"];

        if ($active) {

            $authURL = $serverURL . "/api/{repo}/upload/put/{nodedir}?xhr_uploader=true";

            $host = $params["UPLOAD_HOST"];
            $port = $params["UPLOAD_PORT"];
            $secure = $params["UPLOAD_SECURE"];
            $path = "/" . trim($params["UPLOAD_PATH"], "/");

            // WE SHOULD HAVE A CONTEXT AT THIS POINT, INSTEAD OF CALLING ::getLoggedUser()
            $adminKey = ApiKeysService::findPairForAdminTask("go-upload", AuthService::getLoggedUser()->getId());
            if($adminKey === null){
                $adminKey = ApiKeysService::generatePairForAdminTask("go-upload", AuthService::getLoggedUser()->getId(), $host);
            }
            $adminKeyString = $adminKey["t"].":".$adminKey["p"];

            $key = "http" . ($secure ? "s" : "") . "://" . $host . ":" . $port;
            $hosts[$key] = array_merge(
                (array)$hosts[$key],
                [
                    "header " . $path => ["{\n" .
                        "\t\tAccess-Control-Allow-Origin " . $serverURL . "\n" .
                        "\t\tAccess-Control-Request-Headers *\n" .
                        "\t\tAccess-Control-Allow-Methods POST\n" .
                        "\t\tAccess-Control-Allow-Headers Range\n" .
                        "\t\tAccess-Control-Allow-Credentials true\n" .
                        "\t}"
                    ],
                    "pydioauth " . $path => [$tokenURL . "&device=upload"],
                    "pydiopre " . $path => [$authURL, "{\n" .
                        "\t\theader X-File-Direct-Upload request-options\n" .
                        "\t\theader X-Pydio-Admin-Auth $adminKeyString\n" .
                        "\t}"
                    ],
                    "pydioupload " . $path => [],
                    "pydiopost " . $path => [$authURL, "{\n" .
                        "\t\theader X-File-Direct-Upload upload-finished\n" .
                        "\t\theader X-File-Name {nodename}\n" .
                        "\t}"
                    ],
                ]
            );
        }

        foreach ($hosts as $host => $config) {
            $data .= $host . " {\n";

            foreach ($config as $key => $value) {
                $data .= "\t" . $key . " " . join($value, " ") . "\n";
            }

            $data .= "}\n";
        }

        return $data;
    }

    /**
     * @param $params
     * @return string
     * @throws \Exception
     */
    public function saveCaddyFile($params) {
        $data = $this->generateCaddyFile($params);

        $wDir = $this->getPluginWorkDir(true);
        $caddyFile = $wDir.DIRECTORY_SEPARATOR."pydiocaddy";

        // Generate the caddyfile
        file_put_contents($caddyFile, $data);

        return $caddyFile;
    }

    /**
     * @param $params
     * @return string
     * @throws \Exception
     */
    public function switchCaddyOn($params) {

        $caddyFile = $this->saveCaddyFile($params);

        $wDir = $this->getPluginWorkDir(true);
        $pidFile = $wDir.DIRECTORY_SEPARATOR."caddy-pid";
        if (file_exists($pidFile)) {
            $pId = file_get_contents($pidFile);
            $unixProcess = new UnixProcess();
            $unixProcess->setPid($pId);
            $status = $unixProcess->status();
            if ($status) {
                throw new \Exception("Caddy server seems to already be running!");
            }
        }

        chdir($wDir);

        $cmd = "env TMPDIR=/tmp ". ConfService::getGlobalConf("CLI_PYDIO")." -conf ".$caddyFile . " 2>&1 | tee pydio.out";

        $process = CliRunner::runCommandInBackground($cmd, null);
        if ($process != null) {
            $pId = $process->getPid();
            file_put_contents($pidFile, $pId);
            return "SUCCESS: Started WebSocket Server with process ID $pId";
        }
        return "SUCCESS: Started WebSocket Server";
    }

    /**
     * @param $params
     * @return string
     * @throws \Exception
     */
    public function switchCaddyOff($params){
        return $this->switchOff($params, "caddy");
    }

    /**
     * @param $params
     * @return string
     */
    public function getCaddyStatus($params){
        return $this->getStatus($params, "caddy");
    }

    /**
     * @param $params
     * @param string $type
     * @return string
     * @throws \Exception
     */
    public function switchOff($params, $type = "ws")
    {
        $wDir = $this->getPluginWorkDir(true);
        $pidFile = $wDir.DIRECTORY_SEPARATOR."$type-pid";
        if (!file_exists($pidFile)) {
            throw new \Exception("No information found about $type server");
        } else {
            $pId = file_get_contents($pidFile);
            $unixProcess = new UnixProcess();
            $unixProcess->setPid($pId);
            $unixProcess->stop();
            unlink($pidFile);
        }
        return "SUCCESS: Killed $type Server";
    }

    /**
     * @param $params
     * @param string $type
     * @return string
     * @throws \Exception
     */
    public function getStatus($params, $type = "ws")
    {
        $wDir = $this->getPluginWorkDir(true);
        $pidFile = $wDir.DIRECTORY_SEPARATOR."$type-pid";
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
