<?php

require_once("websocket.client.php");

class WebSocketAdminClient extends WebSocket {

    protected $adminKey = null;

    public function __construct($url, $adminKey) {
        parent::__construct($url);

        $this->adminKey = $adminKey;

        $this->addHeader("Admin-Key", $adminKey);
    }

    public function sendMessage($msg) {
        $wsmsg = WebSocketMessage::create(json_encode($msg));

        parent::sendMessage($wsmsg);
    }

}

/**
 * Helper class to send Admin Messages to the WebSocketServer
 *
 * Makes the server execute onAdminXXXX() events
 *
 * @author Chris
 *
 */
class WebSocketAdminMessage extends stdClass {

    public $task = null;

    private function __construct() {
        
    }

    /**
     * Create a message that will be send to the instance of the WebSocketServer
     *
     * @param string $task
     * @return WebSocketAdminMessage
     */
    public static function create($task) {
        $o = new self();
        $o->task = $task;

        return $o;
    }

}