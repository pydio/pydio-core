<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
namespace Pydio\Access\Meta\Hash;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\MetaStreamWrapper;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Access\Meta\Core\IFileHasher;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\LocalCache;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Services\ApplicationState;

use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Access\Meta\Core\AbstractMetaSource;
use Pydio\Access\Metastore\Core\IMetaStoreProvider;
use Zend\Diactoros\Response\JsonResponse;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Generates and caches and md5 hash of each file
 * @package Pydio\Access\Meta\Hash
 */
class FileHasher extends AbstractMetaSource implements IFileHasher
{
    const METADATA_HASH_NAMESPACE = "file_hahser";
    /**
     * @var IMetaStoreProvider
     */
    protected $metaStore;

    /**
     * @return bool
     */
    public static function rsyncEnabled()
    {
        return function_exists("rsync_generate_signature");
    }

    /**
     * @return array
     */
    public function getConfigs()
    {
        $data = parent::getConfigs();
        $this->filterData($data);
        return $data;
    }

    /**
     * @param array $data
     */
    public function loadConfigs($data)
    {
        $this->filterData($data);
        parent::loadConfigs($data);

    }

    /**
     * @param $data
     */
    private function filterData(&$data)
    {
        $data["RSYNC_SUPPORTED"] = self::rsyncEnabled();
    }


    /**
     * @param ContextInterface $ctx
     * @param \DOMNode $contribNode
     */
    public function parseSpecificContributions(ContextInterface $ctx, \DOMNode &$contribNode)
    {
        parent::parseSpecificContributions($ctx, $contribNode);
        if (!self::rsyncEnabled() && $contribNode->nodeName == "actions") {
            // REMOVE rsync actions, this will advertise the fact that
            // rsync is not enabled.
            $xp = new \DOMXPath($contribNode->ownerDocument);
            $children = $xp->query("action[contains(@name, 'filehasher')]", $contribNode);
            foreach ($children as $child) {
                $contribNode->removeChild($child);
            }
        }
        if ($this->getContextualOption($ctx, "CACHE_XML_TREE") !== true && $contribNode->nodeName == "actions") {
            // REMOVE pre and post process on LS action
            $xp = new \DOMXPath($contribNode->ownerDocument);
            $children = $xp->query("action[@name='ls']", $contribNode);
            foreach ($children as $child) {
                $contribNode->removeChild($child);
            }
        }
    }

    /**
     * @param ContextInterface $ctx
     * @param AbstractAccessDriver $accessDriver
     * @throws PydioException
     */
    public function initMeta(ContextInterface $ctx, AbstractAccessDriver $accessDriver)
    {
        parent::initMeta($ctx, $accessDriver);
        $store = PluginsService::getInstance($ctx)->getUniqueActivePluginForType("metastore");
        if ($store === false) {
            throw new PydioException("The 'meta.simple_lock' plugin requires at least one active 'metastore' plugin");
        }
        $this->metaStore = $store;
        $this->metaStore->initMeta($ctx, $accessDriver);
    }

    /**
     * Handle stat_hash action
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     */
    public function statAction(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface){

        $selection = UserSelection::fromContext($requestInterface->getAttribute("ctx"), $requestInterface->getParsedBody());

        clearstatcache();
        if ($selection->isUnique()) {
            $node = $selection->getUniqueNode();
            $stat = @stat($node->getUrl());
            if (!$stat || !is_readable($node->getUrl())) {
                $responseData = new \stdClass();
            } else {
                if(is_file($node->getUrl())) {
                    $serverParams = $requestInterface->getServerParams();
                    if(isSet($serverParams["HTTP_RANGE"])){
                        $fullSize = floatval($stat['size']);
                        $ranges = explode('=', $serverParams["HTTP_RANGE"]);
                        $offsets = explode('-', $ranges[1]);
                        $offset = floatval($offsets[0]);
                        $length = floatval($offsets[1]) - $offset;
                        if (!$length) $length = $fullSize - $offset;
                        if ($length + $offset > $fullSize || $length < 0) $length = $fullSize - $offset;
                        $hash = $this->getPartialHash($node, $offset, $length);
                    }else{
                        $selection->getUniqueNode()->loadNodeInfo(true);
                        $hash = $this->getFileHash($selection->getUniqueNode());
                    }
                }
                else $hash = 'directory';
                $stat[13] = $stat["hash"] = $hash;
                $responseData = $stat;
            }
        } else {
            $files = $selection->getFiles();
            $responseData = [];
            foreach ($files as $index => $path) {
                $node = new AJXP_Node($selection->currentBaseUrl().$path);
                $stat = @stat($selection->currentBaseUrl().$path);
                if(!$stat || !is_readable($node->getUrl())) {
                    $stat = new \stdClass();
                } else {
                    if(!is_dir($node->getUrl())) {
                        $node->loadNodeInfo(true);
                        $hash = $this->getFileHash($node);
                    } else {
                        $hash = 'directory';
                    }
                    $stat[13] = $stat["hash"] = $hash;
                }
                $responseData[$path] = $stat;
            }

        }

        $responseInterface = new JsonResponse($responseData);

    }

    /**
     * @param $actionName
     * @param $httpVars
     * @param $fileVars
     * @param ContextInterface $ctx
     * @throws \Exception
     */
    public function switchActions($actionName, $httpVars, $fileVars, ContextInterface $ctx)
    {
        $selection = UserSelection::fromContext($ctx, $httpVars);
        
        switch ($actionName) {

            case "filehasher_signature":
                $file = $selection->getUniqueNode();
                if(!file_exists($file->getUrl())) break;
                $cacheItem = LocalCache::getItem("signatures", $file->getUrl(), array($this, "generateSignature"));
                $data = $cacheItem->getData();
                header("Content-Type:application/octet-stream");
                header("Content-Length", strlen($data));
                echo($data);
                break;
            
            case "filehasher_delta":
            case "filehasher_patch":
                // HANDLE UPLOAD DATA
                $this->logDebug("Received signature file, should compute delta now");
                if (!isSet($fileVars) && !is_array($fileVars["userfile_0"])) {
                    throw new \Exception("These action should find uploaded data");
                }
                $signature_delta_file = $fileVars["userfile_0"]["tmp_name"];
                $fileUrl = $selection->getUniqueNode()->getUrl();
                $file = MetaStreamWrapper::getRealFSReference($fileUrl, true);
                if ($actionName == "filehasher_delta") {
                    $deltaFile = tempnam(ApplicationState::getTemporaryFolder(), $actionName."-delta");
                    $this->logDebug("Received signature file, should compute delta now");
                    \rsync_generate_delta($signature_delta_file, $file, $deltaFile);
                    $this->logDebug("Computed delta file, size is ".filesize($deltaFile));
                    header("Content-Type:application/octet-stream");
                    header("Content-Length:".filesize($deltaFile));
                    readfile($deltaFile);
                    unlink($deltaFile);
                } else {
                    $patched = $file.".rdiff_patched";
                    \rsync_patch_file($file, $signature_delta_file, $patched);
                    rename($patched, $file);
                    $node = $selection->getUniqueNode();
                    Controller::applyHook("node.change", array($node, $node, false));
                    header("Content-Type:text/plain");
                    echo md5_file($file);
                }
                break;

        }
    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     * @return String md5
     */
    public function getFileHash(AJXP_Node $node)
    {
        // Make sure that node is really there
        if ($node->isLeaf()) {
            $md5 = null;
            if ($this->metaStore != false) {

                $hashMeta = $this->metaStore->retrieveMetadata(
                    $node,
                    FileHasher::METADATA_HASH_NAMESPACE,
                    false,
                    AJXP_METADATA_SCOPE_GLOBAL);
                $mtime = filemtime($node->getUrl());
                if(is_array($hashMeta)
                    && array_key_exists("md5", $hashMeta)
                    && array_key_exists("md5_mtime", $hashMeta)
                    && $hashMeta["md5_mtime"] >= $mtime){
                    $md5 = $hashMeta["md5"];
                }
                if ($md5 == null) {
                    $md5 = md5_file($node->getUrl());
                    $hashMeta = array(
                        "md5" => $md5,
                        "md5_mtime" => $mtime
                    );
                    $this->metaStore->setMetadata($node, FileHasher::METADATA_HASH_NAMESPACE, $hashMeta, false, AJXP_METADATA_SCOPE_GLOBAL);
                }

            } else {

                $md5 = md5_file($node->getUrl());

            }
            $node->mergeMetadata(array("md5" => $md5));
            return $md5;
        }else{
            return 'directory';
        }
    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     * @param float $offset
     * @param float $length
     * @return String md5
     */
    public function getPartialHash($node, $offset, $length){

        $this->logDebug('Getting partial hash from ' . $offset . ' to ' . $length );
        $fp = fopen($node->getUrl(), "r");
        $ctx = hash_init('md5');
        if($offset > 0){
            fseek($fp, $offset);
        }
        hash_update_stream($ctx, $fp, $length);
        $hash = hash_final($ctx);
        $this->logDebug('Partial hash is ' . $hash );
        fclose($fp);
        return $hash;

    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $oldNode
     * @param \Pydio\Access\Core\Model\AJXP_Node $newNode
     * @param bool $copy
     */
    public function invalidateHash($oldNode = null, $newNode = null, $copy = false)
    {
        if($this->metaStore == false) return;
        if ($oldNode != null) {
            $this->metaStore->removeMetadata($oldNode, FileHasher::METADATA_HASH_NAMESPACE, false, AJXP_METADATA_SCOPE_GLOBAL);
        }
    }


    /**
     * @param $masterFile
     * @param $targetFile
     */
    public function generateSignature($masterFile, $targetFile)
    {
        \rsync_generate_signature($masterFile, $targetFile);
    }
}
