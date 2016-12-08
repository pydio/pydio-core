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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Mq\Core;

use nsqphp\Message\Message;
use nsqphp\nsqphp;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Filter\AJXP_Permission;
use Pydio\Access\Core\Model\NodesDiff;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Exception\AuthRequiredException;
use Pydio\Core\Exception\PydioException;
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
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\Vars\StringHelper;

use Pydio\Core\Utils\Vars\XMLFilter;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Mq\Core\Message\ConsumeChannelMessage;
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
     * Override parent method
     * @param $configName
     * @param $configValue
     */
    public function exposeConfigInManifest($configName, $configValue)
    {
        // Do not expose those
        if(in_array($configName, ["NSQ_HOST", "NSQ_PORT"])){
            return;
        }
        if(is_array($configValue)){
            $newValue = [];
            foreach($configValue as $key => $val){
                if(strpos($key, "INTERNAL") !== false) continue;
                $newValue[$key] = $val;
            }
            parent::exposeConfigInManifest($configName, $newValue);
        }else{
            parent::exposeConfigInManifest($configName, $configValue);
        }
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
        $diff = new NodesDiff();
        if ($newNode != null) {
            $ctx = $newNode->getContext();
            //$targetUserId = $newNode->getUserId();
            $targetUserId = null;
            $nodePaths[] = $newNode->getPath();
            $update = false;
            if ($origNode != null && !$copy) {
                $update = true;
                $diff->update($newNode, $origNode->getPath());
            } else {
                $diff->add($newNode);
            }
            $content = $diff->toXML();
        }
        if ($origNode != null && ! $update && !$copy) {

            $ctx = $origNode->getContext();
            //$targetUserId = $origNode->getUserId();
            $targetUserId = null;
            $nodePaths[] = $origNode->getPath();
            $diff->remove([$origNode->getPath()]);
            $content = $diff->toXML();

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
            $gPath  = $targetGroupPath;
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

        if($repositoryId === null && (isset($userId) || isSet($gPath))){
            $repositoryId = "*";
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

    /**
     * Send a message to NSQ
     * @param ContextInterface $ctx
     * @param $topic
     * @param $content
     */
    private function _sendMessage(ContextInterface $ctx, $topic, $content) {

        $wsActive = $this->getContextualOption($ctx, "WS_ACTIVE");
        if(!$wsActive){
            return;
        }

        $configs = $this->getConfigs();
        $host = OptionsHelper::getNetworkOption($configs, OptionsHelper::OPTION_HOST, OptionsHelper::FEATURE_MESSAGING, OptionsHelper::SCOPE_EXTERNAL);
        $port = intval(OptionsHelper::getNetworkOption($configs, OptionsHelper::OPTION_PORT, OptionsHelper::FEATURE_MESSAGING, OptionsHelper::SCOPE_EXTERNAL));

        if(!empty($host) && !empty($port)){

            if(empty($this->nsqClient)){
                // Publish on NSQ
                $this->nsqClient = new nsqphp;
                $this->nsqClient->publishTo(join(":", [$host, $port]), 1);
            }
            set_error_handler(function ($errNo, $str) use (&$msg) { $msg = $str; });
            try {
                $this->nsqClient->publish($topic, new Message($content));
                $this->logDebug("core.mq", "Published to NSQ " .$topic." :". $content);
            } catch (\Exception $e) {

                $this->logError("core.mq", "sendMessage " . $topic, $e->getMessage());

                if(ApplicationState::sapiIsCli()){
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

        $apiKeyString = $this->getAdminKeyString();
        $httpVars = $request->getQueryParams();
        if (!isSet($httpVars["key"]) || $httpVars["key"] !== $apiKeyString) {
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
     * Save an admin key to a file
     * @param array $params
     * @param ContextInterface $ctx
     * @return string
     */
    public function generateAdminKey($params, $ctx){
        $u = $ctx->getUser();
        if(!$u->isAdmin()){
            return "ERROR: You are not administrator";
        }
        try{
            $this->getAdminKeyString();
            return "SUCCESS: Nothing to do, a pair already exists";
        }catch(PydioException $e){
            $adminPair = $this->getAdminKeyString($u->getId());
            $pairFile = $this->getPluginWorkDir(true)."/apikey";
            $r = file_put_contents($pairFile, $adminPair);
            if($r === false){
                return "ERROR: Something went wrong";
            }else{
                return "SUCCESS: An API key pair has been written in $pairFile on the server. You can now use it to configure PydioBooster.";
            }
        }
    }

    /**
     * Save an admin key to a file
     * @param array $params
     * @param ContextInterface $ctx
     * @return string
     */
    public function revokeAdminKey($params, $ctx){
        $u = $ctx->getUser();
        if(!$u->isAdmin()){
            return "ERROR: You are not administrator";
        }
        $c = ApiKeysService::revokePairForAdminTask(PYDIO_BOOSTER_TASK_IDENTIFIER);
        if($c > 0){
            return "SUCCESS: Successfully revoked $c pair of keys. You may have to generate new ones and reload PydioBooster.";
        }else{
            return "SUCCESS: Nothing to do, there is no admin key";
        }
    }


    /**
     * @param bool $createIfNotExists
     * @param string $restrictToIp
     * @throws PydioException
     * @return string
     */
    protected function getAdminKeyString($userId = "", $restrictToIp = ""){

        if($userId != ""){
            $adminKey = ApiKeysService::findPairForAdminTask(PYDIO_BOOSTER_TASK_IDENTIFIER);
            if($adminKey === null){
                $adminKey = ApiKeysService::generatePairForAdminTask(PYDIO_BOOSTER_TASK_IDENTIFIER, $userId, $restrictToIp);
            }
            $adminKeyString = $adminKey["t"].":".$adminKey["p"];
        }else{
            $adminKey = ApiKeysService::findPairForAdminTask(PYDIO_BOOSTER_TASK_IDENTIFIER);
            if($adminKey === null){
                throw new PydioException("Cannot find any key pair for admin access, something went wrong!");
            }
            $adminKeyString = $adminKey["t"].":".$adminKey["p"];
        }
        return $adminKeyString;

    }

    /********************************************/
    /* ACTIONS CALLED VIA run_plugin_action API */
    /********************************************/
    /**
     * @param $params
     * @param ContextInterface $ctx
     * @return string
     * @throws \Exception
     */
    public function switchWorkerOn($params, $ctx)
    {
        //$adminKeyString = $this->getAdminKeyString($ctx->getUser()->getId());
        //$res = $this->getBoosterManager()->switchWorkerOn($params, $adminKeyString);
        return  "NOT IMPLEMENTED";//"SUCCESS: ".$res;
    }

    /**
     * @param $params
     * @return string
     * @throws \Exception
     */
    public function switchWorkerOff($params){
        return "NOT IMPLEMENTED"; //$this->getBoosterManager()->switchWorkerOff($params);
    }

    /**
     * @param $params
     * @return string
     */
    public function getWorkerStatus($params){
        return "OFF"; //$this->getBoosterManager()->getWorkerStatus($params);
    }

}
