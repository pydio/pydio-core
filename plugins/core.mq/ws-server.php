#!/php -q
<?php
// Run from command prompt > php demo.php
require_once("../../vendor/phpws/websocket.server.php");

/**
 * This demo resource handler will respond to all messages sent to /echo/ on the socketserver below
 *
 * All this handler does is echoing the responds to the user
 * @author Chris
 *
 */
class DemoEchoHandler extends WebSocketUriHandler {

    public function onMessage(IWebSocketConnection $user, IWebSocketMessage $msg) {
        $this->say("[ECHO] " . strlen($msg->getData()) . " bytes");
        // Echo
        // $user->sendMessage($msg);
        $data = unserialize($msg->getData());
        $msg->setData($data["CONTENT"]);
        foreach($this->getConnections() as $conn){
            if($conn == $user) continue;
            $this->say("Should dispatch to another user");
            $conn->sendMessage($msg);
        }
    }

    public function onAdminMessage(IWebSocketConnection $user, IWebSocketMessage $obj) {
        $this->say("[DEMO] Admin TEST received!");

        $frame = WebSocketFrame::create(WebSocketOpcode::PongFrame);
        $user->sendFrame($frame);
    }

}

/**
 * Demo socket server. Implements the basic eventlisteners and attaches a resource handler for /echo/ urls.
 *
 *
 * @author Chris
 *
 */
class DemoSocketServer implements IWebSocketServerObserver {

    protected $debug = true;
    protected $server;

    public function __construct() {
        $this->server = new WebSocketServer("tcp://192.168.0.18:8090", 'adminsecretkey');
        $this->server->addObserver($this);

        $this->server->addUriHandler("echo", new DemoEchoHandler());
    }

    public function onConnect(IWebSocketConnection $user) {
        $h = $user->getHeaders();
        $c = WebSocketFunctions::cookie_parse($h["Cookie"]);
        $sessId = $c["AjaXplorer"];
        $file = session_save_path().DIRECTORY_SEPARATOR."sess_".$sessId;
        $data = (string)file_get_contents($file);
        try{
            global $_SESSION;
            $_SESSION = array();
            session_decode($data);
            $this->say("[ECHO] {$user->getId()} connected with session ". print_r($_SESSION, true));
        }catch (Exception $e){
            $this->say($e->getMessage());
            $user->disconnect();
        }
    }

    public function onMessage(IWebSocketConnection $user, IWebSocketMessage $msg) {
        $this->say("[DEMO] {$user->getId()} says '{$msg->getData()}'");
    }

    public function onDisconnect(IWebSocketConnection $user) {
        $this->say("[DEMO] {$user->getId()} disconnected");
    }

    public function onAdminMessage(IWebSocketConnection $user, IWebSocketMessage $msg) {
        $this->say("[DEMO] Admin Message received!");

        $frame = WebSocketFrame::create(WebSocketOpcode::PongFrame);
        $user->sendFrame($frame);
    }

    public function say($msg) {
        echo "$msg \r\n";
    }

    public function run() {
        $this->server->run();
    }

    function unserializesession( $data )
    {
        if(  strlen( $data) == 0)
        {
            return array();
        }

        // match all the session keys and offsets
        preg_match_all('/(^|;|\})([a-zA-Z0-9_]+)\|/i', $data, $matchesarray, PREG_OFFSET_CAPTURE);

        $returnArray = array();

        $lastOffset = null;
        $currentKey = '';
        foreach ( $matchesarray[2] as $value )
        {
            $offset = $value[1];
            if(!is_null( $lastOffset))
            {
                $valueText = substr($data, $lastOffset, $offset - $lastOffset );
                $returnArray[$currentKey] = unserialize($valueText);
            }
            $currentKey = $value[0];

            $lastOffset = $offset + strlen( $currentKey )+1;
        }

        $valueText = substr($data, $lastOffset );
        $returnArray[$currentKey] = unserialize($valueText);

        return $returnArray;
    }

}

// Start server
$server = new DemoSocketServer();
$server->run();
