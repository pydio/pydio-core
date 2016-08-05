<?php

require_once(__DIR__ . '/vendor/phpws/autoload.php');

$ADMIN_KEY = 'adminsecretkey';

$optArgs = array();
$options = array();
$regex = '/^-(-?)([a-zA-z0-9_]*)=(.*)/';
foreach ($argv as $key => $argument) {
    //echo("$key => $argument \n");
    if (preg_match($regex, $argument, $matches)) {
        if ($matches[1] == "-") {
            $optArgs[trim($matches[2])] = trim($matches[3]);
        } else {
            $options[trim($matches[2])] = trim($matches[3]);
        }
    }
}

if (!isSet($options["host"]) || !isSet($options["port"])) {
    echo 'You must use the following command: \n > php ws-server.php -host=HOST_IP -port=HOST_PORT [-path=PATH]\n\n';
    exit(0);
}

class AjaXplorerHandler extends \Devristo\Phpws\Server\UriHandler\WebSocketUriHandler {

    private $ADMIN_KEY;

    public function __construct($logger, $ADMIN_KEY)
    {
        parent::__construct($logger);
        $this->ADMIN_KEY = $ADMIN_KEY;
    }

    public function onMessage(Devristo\Phpws\Protocol\WebSocketTransportInterface $user, Devristo\Phpws\Messaging\WebSocketMessageInterface $msg) {
        $this->logger->notice('got message from client');
        $h = $user->getHandshakeRequest()->getHeaders()->toArray();
        if (array_key_exists('Admin-Key',$h) && $h['Admin-Key'] == $this->ADMIN_KEY) {

            $data = unserialize($msg->getData());
            $repoId = $data['REPO_ID'];
            $userId = isSet($data['USER_ID']) ? $data['USER_ID'] : false;
            $userGroupPath = isSet($data['GROUP_PATH']) ? $data['GROUP_PATH'] : false;
            foreach ($this->getConnections() as $conn) {
                if($conn == $user) continue;
                if ($repoId != 'AJXP_REPO_SCOPE_ALL' && (!isSet($conn->currentRepository) || $conn->currentRepository != $repoId)) {
                    $this->logger->notice('Skipping, not the same repository');
                    continue;
                }
                if ($userId !== false && $conn->ajxpId != $userId) {
                    $this->logger->notice('Skipping, not the same userId');
                    continue;
                }
                if ($userGroupPath != false && (!isSet($conn->ajxpGroupPath) || $conn->ajxpGroupPath!=$userGroupPath)) {
                    $this->logger->notice('Skipping, not the same groupPath');
                    continue;
                }
                $this->logger->notice('Should dispatch to user '.$conn->ajxpId);
                $conn->sendString($data['CONTENT']);
            }
        }else{
            $data = $msg->getData();
            if (strpos($data, 'register:') === 0) {
                $regId = substr($data, strlen('register:'));
                if (is_array($user->ajxpRepositories) && in_array($regId, $user->ajxpRepositories)) {
                    $user->currentRepository = $regId;
                    $this->logger->notice('User is registered on channel '.$user->currentRepository);
                }
            } else if (strpos($data, 'unregister:') === 0) {
                unset($user->currentRepository);
            }
        }
    }
}

$loop = \React\EventLoop\Factory::create();

$logger = new \Zend\Log\Logger();
if (array_key_exists('verbose', $options) && isSet($options["verbose"])) {
    $writer = new Zend\Log\Writer\Stream("php://output");
}else {
    $writer = new Zend\Log\Writer\Noop;
}
$logger->addWriter($writer);

$server = new \Devristo\Phpws\Server\WebSocketServer("tcp://{$options["host"]}:{$options["port"]}", $loop, $logger);
$router = new \Devristo\Phpws\Server\UriHandler\ClientRouter($server, $logger);
$router->addRoute('#^'.$options["path"].'$#i', new AjaXplorerHandler($logger, $ADMIN_KEY));

$server->on('connect', function(Devristo\Phpws\Protocol\WebSocketTransportHybi $user) use ($logger,$options, $ADMIN_KEY){

	$originHeader  = $user->getHandshakeRequest()->getHeader('Origin', null);
    $host = $user->getHandshakeRequest()->getHeader('Host')->getFieldValue();

    if ($originHeader != null) {
        $address = "https://".$host;
        if (strpos($address, $originHeader->getFieldValue()) !== 0) {
            $logger->err('CSRF protection in connection: detected invalid Origin header: '.$originHeader->getFieldValue());
            $user->close();
            return;
        }
    }
    
    $h = $user->getHandshakeRequest()->getHeaders()->toArray();
    if (array_key_exists('Admin-Key',$h) && $h['Admin-Key'] == $ADMIN_KEY) {
        $logger->notice('[ECHO] Admin user connected');
        return;
    }

    /*
     * @todo
     * Handle a REST auth instead of cookie based.
     */
    if($user->getHandshakeRequest()->getCookie() === false){
        return;
    }
    $c = $user->getHandshakeRequest()->getCookie()->getArrayCopy();

    $registry= null;
    //prefer curl if installed
    if(function_exists('curl_version')) {
        // make local call
        $curl = curl_init('http://'.$options['host'].'/index.php?get_action=ws_authenticate&key='.$ADMIN_KEY);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_COOKIESESSION, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $h);
        curl_setopt($curl, CURLOPT_COOKIE, 'AjaXplorer='.$c['AjaXplorer']);
        $registry = curl_exec($curl);
        curl_close($curl);
    }else{
        require_once('../../core/classes/class.HttpClient.php');
        $client = new HttpClient($options['host']);
        $client->cookies = $c;
        $client->get('/index.php/?get_action=ws_authenticate&key='.$ADMIN_KEY);
        $registry = $client->getContent();
    }
    $xml = new DOMDocument();
    $xml->loadXML($registry);
    $xPath = new DOMXPath($xml);
    $err = $xPath->query("//message[@type='ERROR']");
    if ($err->length) {
        //$this->say($err->item(0)->firstChild->nodeValue);
        $user->close();
    } else {
        $userRepositories = array();
        $repos = $xPath->query('/tree/user/repositories/repo');
        foreach ($repos as $repo) {
            $repoId = $repo->attributes->getNamedItem("id")->nodeValue;
            $userRepositories[] = $repoId;
        }
        $user->ajxpRepositories = $userRepositories;
        $user->ajxpId = $xPath->query("/tree/user/@id")->item(0)->nodeValue;
        if ($xPath->query("/tree/user/@groupPath")->length) {
            $groupPath = $xPath->query("/tree/user/@groupPath")->item(0)->nodeValue;
            if (!empty($groupPath)) $user->ajxpGroupPath = $groupPath;
        }
        $logger->notice('[ECHO] User \'' . $user->ajxpId . '\' connected with ' . count($user->ajxpRepositories) . ' registered repositories ');
    }
});

// Bind the server
$server->bind();

// Start the event loop
$loop->run();
