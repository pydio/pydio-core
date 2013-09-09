<?php

interface IWebSocketUser {

    public function __construct($socket, WebSocketServer $server);

    public function getId();

    public function getResource();

    public function setResource($resource);

    public function isAdmin();

    public function hasHandshaked();

    public function setHandshaked();

    public function getHeaders();

    public function setHeaders($headers);

    public function getLastMessage();

    public function addMessage(IWebSocketMessage $msg);

    public function getLastMessageTime();

    public function getSocket();

    public function getServerInstance();

    public function getIp();
}

/**
 * TODO: Getters and setters
 *
 * @author Chris
 *
 */
class WebSocketUser implements IWebSocketUser {

    protected $id;
    private $socket;
    private $resource;
    private $ip;

    /**
     *
     * Enter description here ...
     * @var WebSocketMessage
     */
    private $message;
    private $headers = array();
    private $cookies = array();
    private $isAdmin = false;
    private $lastTime = null;
    private $_server;

    public function __construct($socket, WebSocketServer $server) {
        $this->socket = $socket;

        $address = '';
        if (socket_getpeername($socket, $address) === false)
            throw new Exception();

        $this->ip = $address;
        $this->id = uniqid("u-");

        $this->_server = $server;

        $this->lastTime = time();
    }

    public function getId() {
        return $this->id;
    }

    public function getHeaders() {
        return $this->headers;
    }

    public function setHeaders($headers) {
        $this->headers = $headers;

        if (array_key_exists('Cookie', $this->headers) && is_array($this->headers['Cookie'])) {
            $this->cookie = array();
        } else {
            if (array_key_exists("Cookie", $this->headers)) {
                $this->_cookies = WebSocketFunctions::cookie_parse($this->headers['Cookie']);
            }
            else
                $this->_cookies = array();
        }

        $this->isAdmin = array_key_exists('Admin-Key', $this->headers) && $this->headers['Admin-Key'] == $this->getServerInstance()->getAdminKey();

        // Incorrect admin-key
        if ($this->isAdmin == false && array_key_exists('Admin-Key', $this->headers))
            throw new WebSocketNotAuthorizedException($this);
    }

    public function hasHandshaked() {
        return $this->handshaked;
    }

    public function setHandshaked() {
        $this->handshaked = true;
    }

    public function getCookies() {
        return $this->_cookies;
    }

    public function setProtocolVersion($version) {
        $this->protocol = $version;
    }

    public function getResource() {
        return $this->resource;
    }

    public function setResource($resource) {
        $this->resource = $resource;
    }

    public function isAdmin() {
        return $this->isAdmin;
    }

    public function getLastMessage() {
        return $this->message;
    }

    public function getSocket() {
        return $this->socket;
    }

    public function getLastMessageTime() {
        return $this->lastTime;
    }

    public function getServerInstance() {
        return $this->_server;
    }

    public function getIp() {
        return $this->ip;
    }

}