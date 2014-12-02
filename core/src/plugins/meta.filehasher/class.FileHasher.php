<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <http://pyd.io/>.
 */

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Generates and caches and md5 hash of each file
 * @package AjaXplorer_Plugins
 * @subpackage Meta
 */
class FileHasher extends AJXP_AbstractMetaSource
{
    const METADATA_HASH_NAMESPACE = "file_hahser";
    /**
    * @var MetaStoreProvider
    */
    protected $metaStore;

    public static function rsyncEnabled()
    {
        return function_exists("rsync_generate_signature");
    }

    public function getConfigs()
    {
        $data = parent::getConfigs();
        $this->filterData($data);
        return $data;
    }
    public function loadConfigs($data)
    {
        $this->filterData($data);
        parent::loadConfigs($data);

    }

    private function filterData(&$data)
    {
        $data["RSYNC_SUPPORTED"] = self::rsyncEnabled();
    }


    public function parseSpecificContributions(&$contribNode)
    {
        parent::parseSpecificContributions($contribNode);
        if (!self::rsyncEnabled() && $contribNode->nodeName == "actions") {
            // REMOVE rsync actions, this will advertise the fact that
            // rsync is not enabled.
            $xp = new DOMXPath($contribNode->ownerDocument);
            $children = $xp->query("action[contains(@name, 'filehasher')]", $contribNode);
            foreach ($children as $child) {
                $contribNode->removeChild($child);
            }
        }
        if ($this->getFilteredOption("CACHE_XML_TREE") !== true && $contribNode->nodeName == "actions") {
            // REMOVE pre and post process on LS action
            $xp = new DOMXPath($contribNode->ownerDocument);
            $children = $xp->query("action[@name='ls']", $contribNode);
            foreach ($children as $child) {
                $contribNode->removeChild($child);
            }
        }
    }

    public function initMeta($accessDriver)
    {
        parent::initMeta($accessDriver);
        $store = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("metastore");
        if ($store === false) {
            throw new Exception("The 'meta.simple_lock' plugin requires at least one active 'metastore' plugin");
        }
        $this->metaStore = $store;
        $this->metaStore->initMeta($accessDriver);
    }

    private function getTreeName()
    {
        $repo = $this->accessDriver->repository;
        $base = AJXP_SHARED_CACHE_DIR."/trees/tree-".$repo->getId();
        $secuScope = $repo->securityScope();
        if ($secuScope == "USER") {
            $base .= "-".AuthService::getLoggedUser()->getId();
        } else if ($secuScope == "GROUP") {
            $base .= "-".str_replace("/", "_", AuthService::getLoggedUser()->getGroupPath());
        }
        return $base . "-full.xml";
    }

    public function checkFullTreeCache($actionName, &$httpVars, &$fileVars)
    {
        $cName = $this->getTreeName();
        if (is_file($cName)) {
            header('Content-Type: text/xml; charset=UTF-8');
            header('Cache-Control: no-cache');
            if ( strstr($_SERVER['HTTP_ACCEPT_ENCODING'], 'deflate') ) {
                header('Content-Encoding:deflate');
                readfile($cName.".gz");
            } else {
                readfile($cName);
            }
            exit();
        }
    }

    public function cacheFullTree($actionName, $httpVars, $postProcessData)
    {
        $cName = $this->getTreeName();
        if(!is_dir(dirname($cName))) mkdir(dirname($cName));
        $xmlString = $postProcessData["ob_output"];
        file_put_contents($cName, $xmlString);
        file_put_contents($cName.".gz", gzdeflate($xmlString, 9));
        print($xmlString);
    }

    public function switchActions($actionName, $httpVars, $fileVars)
    {
        //$urlBase = $this->accessDriver
        $repository = $this->accessDriver->repository;
        if (!$repository->detectStreamWrapper(true)) {
            return false;
        }
        $streamData = $repository->streamData;
        $this->streamData = $streamData;
        $destStreamURL = $streamData["protocol"]."://".$repository->getId();
        $selection = new UserSelection($repository, $httpVars);
        switch ($actionName) {
            case "filehasher_signature":
                $file = $selection->getUniqueFile();
                if(!file_exists($destStreamURL.$file)) break;
                $cacheItem = AJXP_Cache::getItem("signatures", $destStreamURL.$file, array($this, "generateSignature"));
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
                    throw new Exception("These action should find uploaded data");
                }
                $uploadedData = tempnam(AJXP_Utils::getAjxpTmpDir(), $actionName."-sig");
                move_uploaded_file($fileVars["userfile_0"]["tmp_name"], $uploadedData);

                $fileUrl = $destStreamURL.$selection->getUniqueFile();
                $file = call_user_func(array($this->streamData["classname"], "getRealFSReference"), $fileUrl, true);
                if ($actionName == "filehasher_delta") {
                    $signatureFile = $uploadedData;
                    $deltaFile = tempnam(AJXP_Utils::getAjxpTmpDir(), $actionName."-delta");
                    $this->logDebug("Received signature file, should compute delta now");
                    rsync_generate_delta($signatureFile, $file, $deltaFile);
                    $this->logDebug("Computed delta file, size is ".filesize($deltaFile));
                    header("Content-Type:application/octet-stream");
                    header("Content-Length:".filesize($deltaFile));
                    readfile($deltaFile);
                    unlink($signatureFile);
                    unlink($deltaFile);
                } else {
                    $patched = $file.".rdiff_patched";
                    $deltaFile = $uploadedData;
                    rsync_patch_file($file, $deltaFile, $patched);
                    rename($patched, $file);
                    unlink($deltaFile);
                    $node = $selection->getUniqueNode($this->accessDriver);
                    AJXP_Controller::applyHook("node.change", array($node, $node, false));
                    header("Content-Type:text/plain");
                    echo md5_file($file);
                }
            break;
            case "stat_hash" :
                $selection = new UserSelection();
                $selection->initFromArray($httpVars);
                clearstatcache();
                header("Content-type:application/json");
                if ($selection->isUnique()) {
                    $node = $selection->getUniqueNode($this->accessDriver);
                    $stat = @stat($node->getUrl());
                    if (!$stat) {
                        print '{}';
                    } else {
                        if($node->isLeaf()) {
                            if(isSet($_SERVER["HTTP_RANGE"])){
                                $fullSize = floatval($stat['size']);
                                $ranges = explode('=', $_SERVER["HTTP_RANGE"]);
                                $offsets = explode('-', $ranges[1]);
                                $offset = floatval($offsets[0]);
                                $length = floatval($offsets[1]) - $offset;
                                if (!$length) $length = $fullSize - $offset;
                                if ($length + $offset > $fullSize || $length < 0) $length = $fullSize - $offset;
                                $hash = $this->getPartialHash($node, $offset, $length);
                            }else{
                                $hash = $this->getFileHash($selection->getUniqueNode($this->accessDriver));
                            }
                        }
                        else $hash = 'directory';
                        $stat[13] = $stat["hash"] = $hash;
                        print json_encode($stat);
                    }
                } else {
                    $files = $selection->getFiles();
                    print '{';
                    foreach ($files as $index => $path) {
                        $node = new AJXP_Node($destStreamURL.$path);
                        $stat = @stat($destStreamURL.$path);
                        if(!$stat) $stat = '{}';
                        else {
                            if(!is_dir($node->getUrl())) $hash = $this->getFileHash($node);
                            else $hash = 'directory';
                            $stat[13] = $stat["hash"] = $hash;
                            $stat = json_encode($stat);
                        }
                        print json_encode($path).':'.$stat . (($index < count($files) -1) ? "," : "");
                    }
                    print '}';
                }

            break;


            break;
        }
    }

    /**
     * @param AJXP_Node $node
     * @return String md5
     */
    public function getFileHash($node)
    {
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
     * @param AJXP_Node $node
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
     * @param AJXP_Node $oldNode
     * @param AJXP_Node $newNode
     * @param bool $copy
     */
    public function invalidateHash($oldNode = null, $newNode = null, $copy = false)
    {
        if($this->metaStore == false) return;
        if ($oldNode != null) {
            $this->metaStore->removeMetadata($oldNode, FileHasher::METADATA_HASH_NAMESPACE, false, AJXP_METADATA_SCOPE_GLOBAL);
        }
        if ($this->getFilteredOption("CACHE_XML_TREE") === true && is_file($this->getTreeName())) {
            @unlink($this->getTreeName());
        }
    }


    public function generateSignature($masterFile, $targetFile)
    {
        rsync_generate_signature($masterFile, $targetFile);
    }
}
