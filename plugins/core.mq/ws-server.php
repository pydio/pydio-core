#!/php -q
<?php
// Run from command prompt > php demo.php
require_once("../../vendor/phpws/websocket.server.php");
require_once("../../core/classes/class.HttpClient.php");
/**
 * This demo resource handler will respond to all messages sent to /echo/ on the socketserver below
 *
 * All this handler does is echoing the responds to the user
 * @author Chris
 *
 */
class DemoEchoHandler extends WebSocketUriHandler {

    public function onMessage(IWebSocketConnection $user, IWebSocketMessage $msg) {
        $this->say("[DEMO] " . strlen($msg->getData()) . " bytes");
        $data = $msg->getData();
        if(strpos($data, "register:") === 0){
            $regId = substr($data, strlen("register:"));
            if(is_array($user->ajxpRepositories) && in_array($regId, $user->ajxpRepositories)){
                $user->currentRepository = $regId;
                $this->say("User is registered on channel ".$user->currentRepository);
            }
        }else if(strpos($data, "unregister:") === 0){
            unset($user->currentRepository);
        }
    }

    public function onAdminMessage(IWebSocketConnection $user, IWebSocketMessage $msg) {
        $this->say("[DEMO] Admin message received!");

        // Echo
        // $user->sendMessage($msg);
        $data = unserialize($msg->getData());
        $repoId = $data["REPO_ID"];
        $userId = isSet($data["USER_ID"]) ? $data["USER_ID"] : false;
        $userGroupPath = isSet($data["GROUP_PATH"]) ? $data["GROUP_PATH"] : false;
        $msg->setData($data["CONTENT"]);
        foreach($this->getConnections() as $conn){
            if($conn == $user) continue;
            if(!isSet($conn->currentRepository) || $conn->currentRepository != $repoId) {
                $this->say("Skipping, not the same repository");
                continue;
            }
            if($userId !== false && $conn->ajxpId != $userId) {
                $this->say("Skipping, not the same userId");
                continue;
            }
            if($userGroupPath != false && (!isSet($conn->ajxpGroupPath) || $conn->ajxpGroupPath!=$userGroupPath)) {
                $this->say("Skipping, not the same groupPath");
                continue;
            }
            $this->say("Should dispatch to user ".$conn->ajxpId);
            $conn->sendMessage($msg);
        }


        //$frame = WebSocketFrame::create(WebSocketOpcode::PongFrame);
        //$user->sendFrame($frame);
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
    static $ADMIN_KEY = "adminsecretkey";

    public function __construct() {
        $this->server = new WebSocketServer("tcp://192.168.0.18:8090", self::$ADMIN_KEY);
        $this->server->addObserver($this);

        $this->server->addUriHandler("ajaxplorer", new DemoEchoHandler());
    }

    public function onConnect(IWebSocketConnection $user) {

        if($user->getAdminKey() == self::$ADMIN_KEY){
            $this->say("[ECHO] Admin user connected");
            return;
        }

        $h = $user->getHeaders();
        $c = WebSocketFunctions::cookie_parse($h["Cookie"]);

        $client = new HttpClient("192.168.0.18");
        $client->cookies = $c;
        $client->get("/ajaxplorer/?get_action=ws_authenticate&key=".self::$ADMIN_KEY);
        $registry = $client->getContent();
        $xml = new DOMDocument();
        $xml->loadXML($registry);
        $xPath = new DOMXPath($xml);
        $err = $xPath->query("//message[@type='ERROR']");
        if($err->length){
            $this->say($err->item(0)->firstChild->nodeValue);
            $user->disconnect();
        }else{
            $userRepositories = array();
            $repos = $xPath->query("/tree/user/repositories/repo");
            foreach($repos as $repo){
                $repoId = $repo->attributes->getNamedItem("id")->nodeValue;
                $userRepositories[] = $repoId;
            }
            $user->ajxpRepositories = $userRepositories;
            $user->ajxpId = $xPath->query("/tree/user/@id")->item(0)->nodeValue;
            $groupPath = $xPath->query("/tree/user/@groupPath")->item(0)->nodeValue;
            if(!empty($groupPath)) $user->ajxpGroupPath = $groupPath;
        }
        $this->say("[ECHO] User connected with registered repositories : ". print_r($user->ajxpRepositories, true));
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

}

// Start server
$server = new DemoSocketServer();
$server->run();
