<?php
/*
Timestamp plugin by Ffdecourt (fdecourt@gmail.com) for Pydio
This plugin allows you to add a certified timestamp by Universign.eu on your documents.
v0.1
*/

namespace Pydio\Action\Timestamp;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\MetaStreamWrapper;
use Pydio\Access\Core\Model\NodesDiff;
use Pydio\Access\Core\Model\UserSelection;

use Pydio\Core\Exception\PydioException;

use Pydio\Core\Http\Message\UserMessage;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\Services\LocaleService;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Class TimestampCreator
 * @package Pydio\Action\Timestamp
 */
class TimestampCreator extends Plugin
{
    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @throws PydioException
     */
    public function switchAction(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface)
    {
        $mess = LocaleService::getMessages();
        $ctx = $requestInterface->getAttribute("ctx");

        $timestamp_url = $this->getContextualOption($ctx, "TIMESTAMP_URL");
        $timestamp_login = $this->getContextualOption($ctx, "USER");
        $timestamp_password = $this->getContextualOption($ctx, "PASS");

        //Check if the configuration has been initiated
        if (empty($timestamp_url) || empty($timestamp_login) || empty($timestamp_password)) {
            $this->logError("Config", "TimeStamp : configuration is needed");
            throw new PydioException($mess["timestamp.4"]);
        }


        //Check if after being initiated, conf. fields have some values
        if (strlen($timestamp_url) < 2 || strlen($timestamp_login) < 2 || strlen($timestamp_password) < 2) {
            $this->logError("Config", "TimeStamp : configuration is incorrect");
            throw new PydioException($mess["timestamp.4"]);
        }

        //Get active repository
        $ctx = $requestInterface->getAttribute("ctx");
        $selection = UserSelection::fromContext($ctx, $requestInterface->getParsedBody());

        $selectedNode = $selection->getUniqueNode();
        $file = $selectedNode->getRealFile();

        //Hash the file, to send it to Universign
        $hashedDataToTimestamp = hash_file('sha256', $file);

        //Check that a tokken is not going to be timestamped !
        if (substr("$file", -4) != '.ers') {
            if (file_exists($file . '.ers')) {
                throw new PydioException($mess["timestamp.1"]);
            } else {
                //Prepare the query that will be sent to Universign
                $dataToSend = array('hashAlgo' => 'SHA256', 'withCert' => 'true', 'hashValue' => $hashedDataToTimestamp);
                $dataQuery = http_build_query($dataToSend);

                //Check if allow_url_fopen is allowed on the server. If not, it will use cUrl
                if (ini_get('allow_url_fopen')) {
                    $context_options = array(
                        'http' => array(
                            'method' => 'POST',
                            'header' => "Content-type: application/x-www-form-urlencoded\r\n"
                                . "Content-Length: " . strlen($dataQuery) . "\r\n"
                                . "Authorization: Basic " . base64_encode($timestamp_login . ':' . $timestamp_password) . "\r\n",
                            'content' => $dataQuery
                        )
                    );

                    //Get the result from Universign
                    $context = stream_context_create($context_options);
                    $fp = fopen($timestamp_url, 'r', false, $context);
                    $tsp = stream_get_contents($fp);
                } //Use Curl if allow_url_fopen is not available
                else {

                    $timestamp_header = array("Content-type: application/x-www-form-urlencoded", "Content-Length: " . strlen($dataQuery), "Authorization: Basic " . base64_encode($timestamp_login . ':' . $timestamp_password));
                    $timeout = 5;
                    $ch = curl_init($timestamp_url);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $dataQuery);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $timestamp_header);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    //Get the result from Universign
                    $tsp = curl_exec($ch);
                    curl_close($ch);
                }

                //Save the result to a file
                file_put_contents($file . '.ers', $tsp);

                //Send the succesful message
                $this->logInfo("TimeStamp", array("files" => $file, "destination" => $file . '.ers'));

                $bodyStream = new SerializableResponseStream();
                $nodesDiff = new NodesDiff();
                $timeStampNode = $selectedNode->getParent()->createChildNode($selectedNode->getLabel().'.ers');
                $nodesDiff->add($timeStampNode);
                $bodyStream->addChunk($nodesDiff);
                $bodyStream->addChunk(new UserMessage($mess["timestamp.3"] . $selectedNode->getLabel()));
                $responseInterface = $responseInterface->withBody($bodyStream);
            }

        } else {
            throw new PydioException($mess["timestamp.2"]);
        }
    }
}
