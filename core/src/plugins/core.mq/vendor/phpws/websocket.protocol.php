<?php

class WebSocketConnectionFactory {

    public static function fromSocketData(WebSocketSocket $socket, $data) {
        $headers = WebSocketFunctions::parseHeaders($data);

        if (isset($headers['Sec-Websocket-Key1'])) {
            $s = new WebSocketConnectionHixie($socket, $headers, $data);
            $s->sendHandshakeResponse();
        } else if (strpos($data, '<policy-file-request/>') === 0) {
            $s = new WebSocketConnectionFlash($socket, $data);
        } else {
            $s = new WebSocketConnectionHybi($socket, $headers);
            $s->sendHandshakeResponse();
        }

        $s->setRole(WebSocketConnectionRole::SERVER);


        return $s;
    }

}

class WebSocketConnectionRole {

    const CLIENT = 0;
    const SERVER = 1;

}

interface IWebSocketConnection {

    public function sendHandshakeResponse();

    public function setRole($role);

    public function readFrame($data);

    public function sendFrame(IWebSocketFrame $frame);

    public function sendMessage(IWebSocketMessage $msg);

    public function sendString($msg);

    public function getHeaders();

    public function getUriRequested();

    public function getCookies();

    public function getIp();

    public function disconnect();
}

abstract class WebSocketConnection implements IWebSocketConnection {

    protected $_headers = array();

    /**
     *
     * @var WebSocketSocket
     */
    protected $_socket = null;
    protected $_cookies = array();
    public $parameters = null;
    protected $_role = WebSocketConnectionRole::CLIENT;

    public function __construct(WebSocketSocket $socket, array $headers) {
        $this->setHeaders($headers);
        $this->_socket = $socket;
    }

    public function getIp() {
        return stream_socket_get_name($this->_socket->getResource(), true);
    }

    public function getId() {
        return (int) $this->_socket->getResource();
    }

    public function sendFrame(IWebSocketFrame $frame) {
        if ($this->_socket->write($frame->encode()) === false)
            return FALSE;
    }

    public function sendMessage(IWebSocketMessage $msg) {
        foreach ($msg->getFrames() as $frame) {
            if ($this->sendFrame($frame) === false)
                return FALSE;
        }

        return TRUE;
    }

    public function getHeaders() {
        return $this->_headers;
    }

    public function setHeaders($headers) {
        $this->_headers = $headers;

        if (array_key_exists('Cookie', $this->_headers) && is_array($this->_headers['Cookie'])) {
            $this->cookie = array();
        } else {
            if (array_key_exists("Cookie", $this->_headers)) {
                $this->_cookies = WebSocketFunctions::cookie_parse($this->_headers['Cookie']);
            }
            else
                $this->_cookies = array();
        }

        $this->getQueryParts();
    }

    public function getCookies() {
        return $this->_cookies;
    }

    public function getUriRequested() {
        if (array_key_exists('GET', $this->_headers))
            return $this->_headers['GET'];
        else
            return null;
    }

    public function setRole($role) {
        $this->_role = $role;
    }

    protected function getQueryParts() {
        $url = $this->getUriRequested();

        // We dont have an URL to process (this is the case for the client)
        if ($url == null)
            return;

        if (($pos = strpos($url, "?")) == -1) {
            $this->parameters = array();
        }

        $q = substr($url, strpos($url, "?") + 1);

        $kvpairs = explode("&", $q);
        $this->parameters = array();

        foreach ($kvpairs as $kv) {
            if (strpos($kv, "=") == -1)
                continue;

            @list($k, $v) = explode("=", $kv);

            $this->parameters[urldecode($k)] = urldecode($v);
        }
    }

    public function getAdminKey() {
        return isset($this->_headers['Admin-Key']) ? $this->_headers['Admin-Key'] : null;
    }

    public function getSocket() {
        return $this->_socket;
    }

}

class WebSocketConnectionFlash {

    public function __construct($socket, $data) {
        $this->_socket = $socket;
        $this->_socket->onFlashXMLRequest($this);
    }

    public function sendString($msg) {
        $this->_socket->write($msg);
    }

    public function disconnect() {
        $this->_socket->disconnect();
    }

}

class WebSocketConnectionHybi extends WebSocketConnection {

    private $_openMessage = null;
    private $lastFrame = null;

    public function sendHandshakeResponse() {
        // Check for newer handshake
        $challenge = isset($this->_headers['Sec-Websocket-Key']) ? $this->_headers['Sec-Websocket-Key'] : null;

        // Build response
        $response = "HTTP/1.1 101 WebSocket Protocol Handshake\r\n" . "Upgrade: WebSocket\r\n" . "Connection: Upgrade\r\n";

        // Build HYBI response
        $response .= "Sec-WebSocket-Accept: " . WebSocketFunctions::calcHybiResponse($challenge) . "\r\n\r\n";

        $this->_socket->write($response);

        WebSocketFunctions::say("HYBI Response SENT!");
    }

    public function readFrame($data) {
        $frames = array();
        while (!empty($data)) {
            $frame = WebSocketFrame::decode($data, $this->lastFrame);
            if ($frame->isReady()) {

                if (WebSocketOpcode::isControlFrame($frame->getType()))
                    $this->processControlFrame($frame);
                else
                    $this->processMessageFrame($frame);

                $this->lastFrame = null;
            } else {
                $this->lastFrame = $frame;
            }

            $frames[] = $frame;
        }

        return $frames;
    }

    public function sendFrame(IWebSocketFrame $frame) {
        /**
         * @var WebSocketFrame
         */
        $hybiFrame = $frame;

        // Mask IFF client!
        $hybiFrame->setMasked($this->_role == WebSocketConnectionRole::CLIENT);

        parent::sendFrame($hybiFrame);
    }

    /**
     * Process a Message Frame
     *
     * Appends or creates a new message and attaches it to the user sending it.
     *
     * When the last frame of a message is received, the message is sent for processing to the
     * abstract WebSocket::onMessage() method.
     *
     * @param IWebSocketUser $user
     * @param WebSocketFrame $frame
     */
    protected function processMessageFrame(WebSocketFrame $frame) {
        if ($this->_openMessage && $this->_openMessage->isFinalised() == false) {
            $this->_openMessage->takeFrame($frame);
        } else {
            $this->_openMessage = WebSocketMessage::fromFrame($frame);
        }

        if ($this->_openMessage && $this->_openMessage->isFinalised()) {
            $this->_socket->onMessage($this->_openMessage);
            $this->_openMessage = null;
        }
    }

    /**
     * Handle incoming control frames
     *
     * Sends Pong on Ping and closes the connection after a Close request.
     *
     * @param IWebSocketUser $user
     * @param WebSocketFrame $frame
     */
    protected function processControlFrame(WebSocketFrame $frame) {
        switch ($frame->getType()) {
            case WebSocketOpcode::CloseFrame :
                $frame = WebSocketFrame::create(WebSocketOpcode::CloseFrame);
                $this->sendFrame($frame);

                $this->_socket->disconnect();
                break;
            case WebSocketOpcode::PingFrame :
                $frame = WebSocketFrame::create(WebSocketOpcode::PongFrame);
                $this->sendFrame($frame);
                break;
        }
    }

    public function sendString($msg) {
        try {
            $m = WebSocketMessage::create($msg);

            return $this->sendMessage($m);
        } catch (Exception $e) {
            $this->disconnect();
        }
    }

    public function disconnect() {
        $f = WebSocketFrame::create(WebSocketOpcode::CloseFrame);
        $this->sendFrame($f);

        $this->_socket->disconnect();
    }

}

class WebSocketConnectionHixie extends WebSocketConnection {

    private $_clientHandshake;

    public function __construct(WebSocketSocket $socket, array $headers, $clientHandshake) {
        $this->_clientHandshake = $clientHandshake;
        parent::__construct($socket, $headers);
    }

    public function sendHandshakeResponse() {
        // Last 8 bytes of the client's handshake are used for key calculation later
        $l8b = substr($this->_clientHandshake, -8);

        // Check for 2-key based handshake (Hixie protocol draft)
        $key1 = isset($this->_headers['Sec-Websocket-Key1']) ? $this->_headers['Sec-Websocket-Key1'] : null;
        $key2 = isset($this->_headers['Sec-Websocket-Key2']) ? $this->_headers['Sec-Websocket-Key2'] : null;

        // Origin checking (TODO)
        $origin = isset($this->_headers['Origin']) ? $this->_headers['Origin'] : null;
        $host = $this->_headers['Host'];
        $location = $this->_headers['GET'];

        // Build response
        $response = "HTTP/1.1 101 WebSocket Protocol Handshake\r\n" . "Upgrade: WebSocket\r\n" . "Connection: Upgrade\r\n";

        // Build HIXIE response
        $response .= "Sec-WebSocket-Origin: $origin\r\n" . "Sec-WebSocket-Location: ws://{$host}$location\r\n";
        $response .= "\r\n" . WebSocketFunctions::calcHixieResponse($key1, $key2, $l8b);

        $this->_socket->write($response);
        echo "HIXIE Response SENT!";
    }

    public function readFrame($data) {
        $f = WebSocketFrame76::decode($data);
        $m = WebSocketMessage76::fromFrame($f);

        $this->_socket->onMessage($m);

        return array($f);
    }

    public function sendString($msg) {
        $m = WebSocketMessage76::create($msg);

        return $this->sendMessage($m);
    }

    public function disconnect() {
        $this->_socket->disconnect();
    }

}
