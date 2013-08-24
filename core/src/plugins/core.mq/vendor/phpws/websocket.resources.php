<?php

interface IWebSocketUriHandler {

    public function addConnection(IWebSocketConnection $user);

    public function removeConnection(IWebSocketConnection $user);

    public function onMessage(IWebSocketConnection $user, IWebSocketMessage $msg);

    public function onAdminMessage(IWebSocketConnection $user, IWebSocketMessage $msg);

    public function setServer(WebSocketServer $server);

    public function getConnections();
}

abstract class WebSocketUriHandler implements IWebSocketUriHandler {

    /**
     *
     * Enter description here ...
     * @var SplObjectStorage
     */
    protected $users;

    /**
     *
     * Enter description here ...
     * @var WebSocketServer
     */
    protected $server;

    public function __construct() {
        $this->users = new SplObjectStorage();
    }

    public function addConnection(IWebSocketConnection $user) {
        $this->users->attach($user);
    }

    public function removeConnection(IWebSocketConnection $user) {
        $this->users->detach($user);
        $this->onDisconnect($user);
    }

    public function setServer(WebSocketServer $server) {
        $this->server = $server;
    }

    public function say($msg = '') {
        return $this->server->say($msg);
    }

    public function send(IWebSocketConnection $client, $str) {
        return $client->sendString($str);
    }

    public function onDisconnect(IWebSocketConnection $user) {
        
    }

    public function onMessage(IWebSocketConnection $user, IWebSocketMessage $msg) {
        
    }

    public function onAdminMessage(IWebSocketConnection $user, IWebSocketMessage $msg) {
        
    }

    //abstract public function onMessage(WebSocketUser $user, IWebSocketMessage $msg);

    public function getConnections() {
        return $this->users;
    }

}