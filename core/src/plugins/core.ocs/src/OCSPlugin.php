<?php
/*
 * Copyright 2007-2015 Abstrium <contact (at) pydio.com>
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
namespace Pydio\OCS;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Model\UserInterface;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\ApplicationState;
use Pydio\OCS\Server\Dummy;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class OCSPlugin
 * @package Pydio\OCS
 */
class OCSPlugin extends Plugin {

    /**
     * @var ActionsController $controller
     */
    protected $controller;

    /**
     * @param ContextInterface $ctx
     * @param array $options
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        parent::init($ctx, $options);
        Controller::registerIncludeHook("repository.list", array($this, "populateRemotes"));
        Controller::registerIncludeHook("repository.search", array($this, "remoteRepositoryById"));
    }

    /**
     * @param array $configData
     */
    public function loadConfigs($configData){
        parent::loadConfigs($configData);
        // Parse Trusted Servers replication group
        $baseId = 'TRUSTED_SERVER_ID';
        $base   = 'TRUSTED_SERVER_';
        $index = 0;
        $trustedServers = [];
        $exposedList = [];
        while(isSet($this->pluginConf[$baseId]) && !empty($this->pluginConf[$baseId])){
            $suffix = $index ? '_'.$index : '';
            $serverId = $this->pluginConf[$baseId];
            $serverLabel = $this->pluginConf[$base . 'LABEL' . $suffix];
            $exposedList[$serverId] = $serverLabel;
            $trustedServers[$serverId] = [
                'url' => $this->pluginConf[$base . 'URL' . $suffix],
                'label' => $serverLabel,
                'user' => $this->pluginConf[$base . 'USER' . $suffix],
                'pass' => $this->pluginConf[$base . 'PASS' . $suffix]
            ];
            $index++;
            $baseId = $base .'ID'. ($index ? '_'.$index : '');
        }
        $this->pluginConf['TRUSTED_SERVERS'] = $trustedServers;
        $this->exposeConfigInManifest('TRUSTED_SERVERS', json_encode($exposedList));
    }

    /**
     * @return ActionsController
     */
    protected function getController(){
        if($this->controller == null){
            require_once("ActionsController.php");
            $configs = $this->getConfigs();
            $this->controller = new ActionsController($configs);
        }
        return $this->controller;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return null
     * @throws \Pydio\Core\Exception\PydioException
     */
    public function switchAction(ServerRequestInterface &$request, ResponseInterface &$response) {
        return $this->getController()->switchAction($request, $response);
    }

    /**
     * @return bool
     */
    public function federatedEnabled(){
        return $this->getConfigs()["ENABLE_FEDERATED_SHARING"] === true;
    }

    public function publishServices(){

        $services = array();
        if($this->federatedEnabled()){
            $services["FEDERATED_SHARING"] = array(
                "version" => 1,
                "endpoints" => array(
                    "share" => "/ocs/v2/shares",
                    "webdav" => "/ocs/v2/dav"
                )
            );
        }
        $output = array("version" => 2, "services" => $services);
        header("Content-Type: text/json");
        print(json_encode($output));

    }

    /**
     * @param $baseUri
     * @param $endpoint
     * @param $uriParts
     * @param $parameters
     * @throws Server\InvalidArgumentsException
     */
    public function route($baseUri, $endpoint, $uriParts, $parameters){

        if($endpoint == "dav" && $this->federatedEnabled()) {

            $server = new Server\Dav\Server();
            $server->start($baseUri."/ocs/v2/dav");

        } else if($endpoint == "shares" && $this->federatedEnabled()) {

            $server = new Server\Federated\Server();
            $server->run($uriParts, $parameters);

        } else {
            Dummy::notImplemented($uriParts, $parameters);
        }
    }

    /**
     * Triggered on repository list loading
     * @param array $wsList
     * @param string $scope
     * @param UserInterface $userObject
     * @param bool $includeShared
     */
    public function populateRemotes(&$wsList, $scope = "user", $userObject, $includeShared = true){
        if(!$includeShared || $scope != "user"){
            return;
        }
        if($userObject == null){
            return;
        }
        $store = new Model\SQLStore();
        $shares = $store->remoteSharesForUser($userObject->getId());
        foreach($shares as $share){
            $repo = $share->buildVirtualRepository();
            $userObject->getPersonalRole()->setAcl($repo->getId(), "rw");
            $wsList[$repo->getId()] = $repo;
        }
        if(count($shares)){
            $userObject->recomputeMergedRole();
        }
    }

    /**
     * @param $repositoryId
     * @param $repoObject
     * @throws \Exception
     */
    public function remoteRepositoryById($repositoryId, &$repoObject){
        if(strpos($repositoryId, "ocs_remote_share_") !== 0){
            return;
        }
        $store = new Model\SQLStore();
        $remoteShareId = str_replace("ocs_remote_share_", "", $repositoryId);
        $share = $store->remoteShareById($remoteShareId);
        if($share != null){
            $repoObject = $share->buildVirtualRepository();
            AuthService::updateSessionUserAcl($repositoryId, "rw");
        }
        
    }


    /**
     * @param $base
     * @param $route
     */
    public static function startServer($base, $route) {
        
        $pServ = PluginsService::getInstance(Context::emptyContext());
        ApplicationState::setSapiRestBase($base);

        ConfService::init();
        ConfService::start();

        $pServ->initActivePlugins();

        /**
         * @var OCSPlugin $coreLoader
         */
        $coreLoader = $pServ->getPluginById("core.ocs");

        if ($route == "/ocs-provider") {

            $coreLoader->publishServices();

        } else if ($route == "/ocs") {

            $uri = $_SERVER["REQUEST_URI"];
            $parts = explode("/", trim(parse_url($uri, PHP_URL_PATH), "/"));
            $baseUri = array();
            $root = array_shift($parts);
            while (!in_array($root, array("ocs-provider", "ocs")) && count($parts)) {
                $baseUri[] = $root;
                $root = array_shift($parts);
            }

            if (count($parts) < 2) {
                $d = new Dummy();
                $response = $d->buildResponse("fail", "400", "Wrong URI");
                $d->sendResponse($response);
                return;
            }

            $version = array_shift($parts);
            if ($version != "v2") {
                $d = new Dummy();
                $response = $d->buildResponse("fail", "400", "Api version not supported - Please switch to v2.");
                $d->sendResponse($response);
                return;
            }
            $endpoint = array_shift($parts);
            if (count($baseUri)) {
                $baseUriStr = "/" . implode("/", $baseUri);
            } else {
                $baseUriStr = "";
            }

            $coreLoader->route($baseUriStr, $endpoint, $parts, array_merge($_GET, $_POST));
        }
    }
}