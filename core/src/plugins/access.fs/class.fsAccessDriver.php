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
 *
 */
namespace Pydio\Access\Driver\StreamProvider\FS;

use DOMNode;
use DOMXPath;
use Normalizer;
use PclZip;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\AJXP_MetaStreamWrapper;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\IAjxpWrapperProvider;
use Pydio\Access\Core\Model\NodesDiff;
use Pydio\Access\Core\Model\NodesList;
use Pydio\Access\Core\RecycleBinManager;
use Pydio\Access\Core\Model\Repository;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Auth\Core\AJXP_Safe;
use Pydio\Core\Http\Response\FileReaderResponse;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\Http\Message\UserMessage;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Utils\Utils;
use Pydio\Core\Controller\HTMLWriter;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\Utils\TextEncoder;
use Pydio\Tasks\Schedule;
use Pydio\Tasks\Task;
use Pydio\Tasks\TaskService;
use Zend\Diactoros\Response;

defined('AJXP_EXEC') or die( 'Access not allowed');


// This is used to catch exception while downloading
if (!function_exists('download_exception_handler')) {
    function download_exception_handler($exception){}
}
/**
 * Plugin to access a filesystem. Most "FS" like driver (even remote ones)
 * extend this one.
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class fsAccessDriver extends AbstractAccessDriver implements IAjxpWrapperProvider
{
    /**
    * @var Repository
    */
    public $repository;
    public $driverConf;
    protected $wrapperClassName;
    protected $urlBase;

    /**
     * @param ContextInterface $contextInterface
     * @throws PydioException
     * @throws \Exception
     */
    protected function initRepository(ContextInterface $contextInterface)
    {
        if (is_array($this->pluginConf)) {
            $this->driverConf = $this->pluginConf;
        } else {
            $this->driverConf = array();
        }
        if ( $this->getContextualOption($contextInterface, "PROBE_REAL_SIZE") == true ) {
            // PASS IT TO THE WRAPPER
            ConfService::setConf("PROBE_REAL_SIZE", true);
        }
        $create = $this->repository->getContextOption($contextInterface, "CREATE");
        $path = TextEncoder::toStorageEncoding($this->repository->getContextOption($contextInterface, "PATH"));
        $recycle = $this->repository->getContextOption($contextInterface, "RECYCLE_BIN");
        $chmod = $this->repository->getContextOption($contextInterface, "CHMOD_VALUE");
        $this->detectStreamWrapper(true);
        $this->urlBase = $contextInterface->getUrlBase();


        if ($create == true) {
            if(!is_dir($path)) @mkdir($path, 0755, true);
            if (!is_dir($path)) {
                throw new PydioException("Cannot create root path for repository (".$this->repository->getDisplay()."). Please check repository configuration or that your folder is writeable!");
            }
            if ($recycle!= "" && !is_dir($path."/".$recycle)) {
                @mkdir($path."/".$recycle);
                if (!is_dir($path."/".$recycle)) {
                    throw new PydioException("Cannot create recycle bin folder. Please check repository configuration or that your folder is writeable!");
                } else {
                    $this->setHiddenAttribute(new AJXP_Node($contextInterface->getUrlBase() ."/".$recycle));
                }
            }
            $dataTemplate = $this->repository->getContextOption($contextInterface, "DATA_TEMPLATE");
            if (!empty($dataTemplate) && is_dir($dataTemplate) && !is_file($path."/.ajxp_template")) {
                $errs = array();$succ = array();
                $repoData = array('base_url' => $contextInterface->getUrlBase(), 'chmod' => $chmod, 'recycle' => $recycle);
                $this->dircopy($dataTemplate, $path, $succ, $errs, false, false, $repoData, $repoData);
                touch($path."/.ajxp_template");
            }
        } else {
            if (!is_dir($path)) {
                throw new PydioException("Cannot find base path for your repository! Please check the configuration!");
            }
        }
        if ($recycle != "") {
            RecycleBinManager::init($contextInterface->getUrlBase(), "/".$recycle);
        }
    }

    public function getResourceUrl($path)
    {
        return $this->urlBase.$path;
    }

    /**
     * @param AJXP_Node $node
     * @return int
     */
    public function directoryUsage(AJXP_Node $node){

        //$dir = (isSet($repositoryResolvedOptions["PATH"]) ? $repositoryResolvedOptions["PATH"] : $this->repository->getOption("PATH")).$directoryPath;
        $dir = $node->getRealFile();
        $size = -1;
        if ( ( PHP_OS == "WIN32" || PHP_OS == "WINNT" || PHP_OS == "Windows") && class_exists("COM") ) {
            $obj = new COM ( 'scripting.filesystemobject' );
            if ( is_object ( $obj ) ) {
                $ref = $obj->getfolder ( $dir );
                $size = floatval($ref->size);
                $obj = null;
            }
        } else {
            if((PHP_OS == "Darwin") || (PHP_OS == "FreeBSD")) $option = "-sk";
            else $option = "-sb";
            $cmd = '/usr/bin/du '.$option.' ' . escapeshellarg($dir);
            $io = popen ( $cmd , 'r' );
            $size = fgets ( $io, 4096);
            $size = trim(str_replace($dir, "", $size));
            $size =  floatval($size);
            if((PHP_OS == "Darwin") || (PHP_OS == "FreeBSD")) $size = $size * 1024;
            pclose ( $io );
        }
        if($size != -1){
            return $size;
        }else{
            return $this->recursiveDirUsageByListing($directoryPath);
        }

    }

    protected function recursiveDirUsageByListing($path){
        $total_size = 0;
        $files = scandir($path);

        foreach ($files as $t) {
            if (is_dir(rtrim($path, '/') . '/' . $t)) {
                if ($t <> "." && $t <> "..") {
                    $size = $this->recursiveDirUsageByListing(rtrim($path, '/') . '/' . $t);
                    $total_size += $size;
                }
            } else {
                $size = sprintf("%u", filesize(rtrim($path, '/') . '/' . $t));
                $total_size += $size;
            }
        }
        return $total_size;
    }

    public function redirectActionsToMethod(&$contribNode, $arrayActions, $targetMethod)
    {
        $actionXpath=new DOMXPath($contribNode->ownerDocument);
        foreach ($arrayActions as $index => $value) {
            $arrayActions[$index] = 'action[@name="'.$value.'"]/processing/serverCallback';
        }
        $procList = $actionXpath->query(implode(" | ", $arrayActions), $contribNode);
        foreach ($procList as $node) {
            $node->setAttribute("methodName", $targetMethod);
        }
    }

    /**
     * @param ServerRequestInterface $request
     */
    protected function filterByApi(&$request){
        if($request->getAttribute("api") !== "v2") return;
        $params = $request->getParsedBody();
        $action = $request->getAttribute("action");
        switch($action){
            case "ls":
                $children = $params["children"] OR null;
                $meta     = $params["meta"] OR "standard";
                if(!empty($children)){
                    $options = $children;
                } else {
                    $options = "dzf";
                }
                if($meta !== "minimal") $options .= "l";
                $params["options"] = $options;
                $request = $request->withParsedBody($params);
                break;
            case "download":

                break;

            default:
                break;
        }
    }

    /**
     * @param DOMNode $contribNode
     */
    public function disableArchiveBrowsingContributions(&$contribNode)
    {
        // Cannot use zip features on FTP !
        // Remove "compress" action
        $actionXpath=new DOMXPath($contribNode->ownerDocument);
        $compressNodeList = $actionXpath->query('action[@name="compress"]|action[@name="compress_ui"]|action[@name="download_all"]', $contribNode);
        if(!$compressNodeList->length) return ;
        foreach($compressNodeList as $compressNodeAction){
            $contribNode->removeChild($compressNodeAction);
        }
        // Disable "download" if selection is multiple
        $nodeList = $actionXpath->query('action[@name="download"]/gui/selectionContext', $contribNode);
        $selectionNode = $nodeList->item(0);
        $values = array("dir" => "false", "unique" => "true");
        foreach ($selectionNode->attributes as $attribute) {
            if (isSet($values[$attribute->name])) {
                $attribute->value = $values[$attribute->name];
            }
        }
        $nodeList = $actionXpath->query('action[@name="download"]/processing/clientListener[@name="selectionChange"]', $contribNode);
        $listener = $nodeList->item(0);
        $listener->parentNode->removeChild($listener);
        // Disable "Explore" action on files
        $nodeList = $actionXpath->query('action[@name="ls"]/gui/selectionContext', $contribNode);
        $selectionNode = $nodeList->item(0);
        $values = array("file" => "false", "allowedMimes" => "");
        foreach ($selectionNode->attributes as $attribute) {
            if (isSet($values[$attribute->name])) {
                $attribute->value = $values[$attribute->name];
            }
        }
    }

    protected function getNodesDiffArray()
    {
        return array("REMOVE" => array(), "ADD" => array(), "UPDATE" => array());
    }

    public function addSlugToPath($selection)
    {
        if (is_array($selection))
            // As passed by Copy/Move
            $orig_files = $selection;
        elseif ((is_object($selection)) && (isset($selection->files)) && (is_array($selection->files)))
            // As passed by Download
            $orig_files = $selection->files;
        elseif (is_string($selection))
            // As passed by destination parameter
            return $this->repository->slug.$selection;
        else
            // Unrecognized
            return $selection;

        $files = array();
        foreach ($orig_files as $file)
            $files[] = $this->repository->slug.$file;
        return $files;
    }

    protected function writeUploadError(ServerRequestInterface &$request, $message, $code){
        $request = $request->withAttribute("upload_process_result", ["ERROR" => ["CODE" => $code, "MESSAGE" => $message]]);
    }

    protected function writeUploadSuccess(ServerRequestInterface &$request, $nodeData){
        $arr = array_merge(["SUCCESS" => true], $nodeData);
        $request = $request->withAttribute("upload_process_result", $arr);
    }

    /**
     * API V2, will get POST / PUT actions, will reroute to mkdir, mkfile, copy, move actions
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @throws \Exception
     */
    public function createResourceAction(ServerRequestInterface &$request, ResponseInterface &$response){

        $selection = new UserSelection($this->repository);
        $selection->initFromHttpVars($request->getParsedBody());
        if($selection->isEmpty()){
            throw new PydioException("Empty resource");
        }
        $path = $selection->getUniqueFile();
        $notDecodedPath = TextEncoder::toUTF8($path);
        $params = $request->getParsedBody();
        $newAction = null;
        if(isSet($params["copy_source"])){
            $newVars["dest"] = Utils::safeDirname($notDecodedPath);
            $newVars["targetBaseName"] = Utils::safeBasename($notDecodedPath);
            $newVars["file"] = "/".implode("/", array_slice(explode("/", trim($params["copy_source"], "/")), 1));
            if(isSet($params["delete_source"]) && $params["delete_source"] == "true"){
                $newAction = "move";
            }else{
                $newAction = "copy";
            }
        }else{
            if(substr_compare($path, "/", strlen($path)-1, 1) === 0){
                // Ends with slash => mkdir
                $newAction = "mkdir";
                $newVars["file"] = $notDecodedPath;
                if(!empty($params["override"])) {
                    $newVars["ignore_exists"] = $params["override"];
                }
            }else{
                $newAction = "mkfile";
                $newVars["node"] = $notDecodedPath;
                if(!empty($params["content"])) {
                    $newVars["content"] = $params["content"];
                }
                if(!empty($params["override"])) {
                    $newVars["force"] = $params["override"];
                }
            }
        }
        $request = $request->withParsedBody($newVars)->withAttribute("action", $newAction);
        $this->switchAction($request, $response);

    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @throws \Exception
     */
    public function uploadAction(ServerRequestInterface &$request, ResponseInterface &$response){

        $httpVars = $request->getParsedBody();
        $dir = Utils::sanitize($httpVars["dir"], AJXP_SANITIZE_DIRNAME) OR "";
        if (AJXP_MetaStreamWrapper::actualRepositoryWrapperClass($this->repository->getId()) == "fsAccessWrapper") {
            $dir = fsAccessWrapper::patchPathForBaseDir($dir);
        }
        $dir = Utils::securePath($dir);
        /** @var ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");
        $selection = UserSelection::fromContext($ctx, $httpVars);
        if (!$selection->isEmpty()) {
            $this->filterUserSelectionToHidden($selection->getContext(), $selection->getFiles());
            if(empty($dir) && $selection->isUnique()){
                $dir = Utils::safeDirname($selection->getUniqueFile());
            }
        }
        $mess = ConfService::getMessages();

        $repoData = array(
            'chmod'     => $ctx->getRepository()->getContextOption($ctx, 'CHMOD_VALUE'),
            'recycle'   => $ctx->getRepository()->getContextOption($ctx, 'RECYCLE_BIN')
        );
        $this->logDebug("Upload Files Data", $request->getUploadedFiles());

        $destNode = $selection->nodeForPath(Utils::decodeSecureMagic($dir));
        $destination = $destNode->getUrl();
        $this->logDebug("Upload inside", array("destination"=>$this->addSlugToPath($destNode->getUrl())));
        if (!$this->isWriteable($destNode)) {
            $errorCode = 412;
            $errorMessage = "$mess[38] ".TextEncoder::toUTF8($dir)." $mess[99].";
            $this->logDebug("Upload error 412", array("destination"=>$this->addSlugToPath($destination)));
            $this->writeUploadError($request, $errorMessage, $errorCode);
            return;
        }

        $partialUpload = false;
        $partialTargetSize = -1;
        $originalAppendTo = "";
        $createdNode = null;

        /** @var UploadedFileInterface[] $uploadedFiles */
        $uploadedFiles = $request->getUploadedFiles();
        foreach ($uploadedFiles as $parameterName => $uploadedFile){
            if(substr($parameterName, 0, 9) != "userfile_") continue;
            
            try{
                // CHECK PHP UPLOAD ERRORS
                Utils::parseFileDataErrors($uploadedFile, true);

                // FIND PROPER FILE NAME
                $userfile_name=Utils::sanitize(TextEncoder::fromPostedFileName($uploadedFile->getClientFileName()), AJXP_SANITIZE_FILENAME);
                if (isSet($httpVars["urlencoded_filename"])) {
                    $userfile_name = Utils::sanitize(TextEncoder::fromUTF8(urldecode($httpVars["urlencoded_filename"])), AJXP_SANITIZE_FILENAME);
                }
                $userfile_name = substr($userfile_name, 0, ConfService::getCoreConf("NODENAME_MAX_LENGTH"));
                if (isSet($httpVars["auto_rename"])) {
                    $userfile_name = self::autoRenameForDest($destination, $userfile_name);
                }
                $this->logDebug("User filename ".$userfile_name);
                if(class_exists("Normalizer")){
                    $userfile_name = Normalizer::normalize($userfile_name, Normalizer::FORM_C);
                }

                // CHECK IF THIS IS A FORBIDDEN FILENAME
                $this->filterUserSelectionToHidden($selection->getContext(), array($userfile_name));

                // APPLY PRE-UPLOAD HOOKS
                $already_existed = false;
                try {
                    $newFileSize = $uploadedFile->getSize();
                    $targetUrl = $destination."/".$userfile_name;
                    $targetNode = new AJXP_Node($targetUrl);
                    if (file_exists($targetUrl)) {
                        $already_existed = true;
                        Controller::applyHook("node.before_change", array($targetNode, $newFileSize));
                    } else {
                        Controller::applyHook("node.before_create", array($targetNode, $newFileSize));
                    }
                    Controller::applyHook("node.before_change", array(new AJXP_Node($destination)));
                } catch (\Exception $e) {
                    throw new \Exception($e->getMessage(), 507);
                }

                // PARTIAL UPLOAD CASE - PREPPEND .dlpart extension
                if(isSet($httpVars["partial_upload"]) && $httpVars["partial_upload"] == 'true' && isSet($httpVars["partial_target_bytesize"])){
                    $partialUpload = true;
                    $partialTargetSize = intval($httpVars["partial_target_bytesize"]);
                    if(!isSet($httpVars["appendto_urlencoded_part"])){
                        $userfile_name .= ".dlpart";
                    }
                }

                // NOW DO THE ACTUAL COPY
                $this->copyUploadedData($uploadedFile, $targetUrl, $mess);

                // PARTIAL UPLOAD - PART II: APPEND DATA TO EXISTING PART
                if (isSet($httpVars["appendto_urlencoded_part"])) {
                    $appendTo = Utils::sanitize(TextEncoder::fromUTF8(urldecode($httpVars["appendto_urlencoded_part"])), AJXP_SANITIZE_FILENAME);
                    if(isSet($httpVars["partial_upload"]) && $httpVars["partial_upload"] == 'true'){
                        $originalAppendTo = $appendTo;
                        $appendTo .= ".dlpart";
                    }
                    $this->logDebug("AppendTo FILE".$appendTo);
                    $already_existed = $this->appendUploadedData($destination, $userfile_name, $appendTo);
                    $userfile_name = $appendTo;
                    if($partialUpload && $partialTargetSize == filesize($destination."/".$userfile_name)){
                        // This was the last part. We can now rename to the original name.
                        if(is_file($destination."/".$originalAppendTo)){
                            unlink($destination."/".$originalAppendTo);
                        }
                        $result = @rename($destination."/".$userfile_name, $destination."/".$originalAppendTo);
                        if($result === false){
                            throw new \Exception("Error renaming ".$destination."/".$userfile_name." to ".$destination."/".$originalAppendTo);
                        }
                        $userfile_name = $originalAppendTo;
                        $partialUpload = false;
                        // Send a create event!
                        $already_existed = false;
                    }
                }

                // NOW PREPARE POST-UPLOAD EVENTS
                $this->changeMode($destination."/".$userfile_name,$repoData);
                $createdNode = new AJXP_Node($destination."/".$userfile_name);
                clearstatcache(true, $createdNode->getUrl());
                $createdNode->loadNodeInfo(true);
                $logFile = $this->addSlugToPath(TextEncoder::fromUTF8($dir))."/".$userfile_name;
                $this->logInfo("Upload File", array("file"=>$logFile, "files"=> $logFile ) );

                if($partialUpload){
                    $this->logDebug("Return Partial Upload: SUCESS but no event yet");
                    if(isSet($already_existed) && $already_existed === true){
                        $this->writeUploadSuccess($request, ["PARTIAL_NODE" => $createdNode]);
                        return;
                    }
                } else {
                    $this->logDebug("Return success");
                    if(isSet($already_existed) && $already_existed === true){
                        $this->writeUploadSuccess($request, ["UPDATED_NODE" => $createdNode]);
                        return;
                    }else{
                        $this->writeUploadSuccess($request, ["CREATED_NODE" => $createdNode]);
                        return;
                    }
                }

            }catch(\Exception $e){
                $errorCode = $e->getCode();
                if(empty($errorCode)) $errorCode = 411;
                $this->writeUploadError($request, $e->getMessage(), $errorCode);
                return;
            }

        }
        $this->writeUploadError($request, "Could not find any uploaded file", 411);

    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @throws PydioException
     * @throws \Exception
     */
    public function downloadAction(ServerRequestInterface &$request, ResponseInterface &$response){

        /** @var ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");
        $httpVars = $request->getParsedBody();
        $selection = UserSelection::fromContext($ctx, $httpVars);
        if (!$selection->isEmpty()) {
            $this->filterUserSelectionToHidden($ctx, $selection->getFiles());
        }

        $action = $request->getAttribute("action");

        switch ($action){
            case "download":

                $this->logInfo("Download", array("files"=>$this->addSlugToPath($selection)));
                @set_error_handler(array("Pydio\\Core\\Controller\\HTMLWriter", "javascriptErrorHandler"), E_ALL & ~ E_NOTICE);
                @register_shutdown_function("restore_error_handler");
                $zip = false;
                $dir = "";
                if ($selection->isUnique()) {
                    if (is_dir($selection->getUniqueNode()->getUrl())) {
                        $zip = true;
                        $base = basename($selection->getUniqueFile());
                        $uniqDir = dirname($selection->getUniqueFile());
                        if(!empty($uniqDir) && $uniqDir != "/"){
                            $dir = dirname($selection->getUniqueFile());
                        }
                    } else {
                        if (!file_exists($selection->getUniqueNode()->getUrl())) {
                            throw new \Exception("Cannot find file!");
                        }
                    }
                    $node = $selection->getUniqueNode();
                } else {
                    if(isset($httpVars["dir"])){
                        $dir = Utils::decodeSecureMagic($httpVars["dir"], AJXP_SANITIZE_DIRNAME);
                    }
                    $zip = true;
                }
                if ($zip) {
                    // Make a temp zip and send it as download
                    $loggedUser = $ctx->getUser();
                    $file = Utils::getAjxpTmpDir()."/".($loggedUser?$loggedUser->getId():"shared")."_".time()."tmpDownload.zip";
                    $zipFile = $this->makeZip($selection, $file, empty($dir)?"/":$dir);
                    if(!$zipFile) throw new PydioException("Error while compressing");
                    if(!ConfService::getCoreConf("USE_XSENDFILE")  && !ConfService::getCoreConf("USE_XACCELREDIRECT")){
                        register_shutdown_function("unlink", $file);
                    }
                    $localName = (empty($base)?"Files":$base).".zip";
                    if(isSet($httpVars["archive_name"])){
                        $localName = Utils::decodeSecureMagic($httpVars["archive_name"]);
                    }
                    $fileReader = new FileReaderResponse($file);
                    $fileReader->setLocalName($localName);
                    $response = $response->withBody($fileReader);
                } else {
                    $localName = "";
                    Controller::applyHook("dl.localname", array($selection->getUniqueNode()->getUrl(), &$localName));
                    $fileReader = new FileReaderResponse($selection->getUniqueNode());
                    $fileReader->setLocalName($localName);
                    $response = $response->withBody($fileReader);
                }
                if (isSet($node)) {
                    Controller::applyHook("node.read", array(&$node));
                }

                break;

            case "get_content":

                $node = $selection->getUniqueNode();
                $dlFile = $node->getUrl();
                if(!is_readable($dlFile)){
                    throw new \Exception("Cannot access file!");
                }
                $this->logInfo("Get_content", array("files"=>$this->addSlugToPath($selection)));

                if (Utils::getStreamingMimeType(basename($dlFile))!==false) {
                    $readMode  = "stream_content";
                } else {
                    $readMode  = "plain";
                }
                $fileReader = new FileReaderResponse($node);
                $fileReader->setHeaderType($readMode);
                $response = $response->withBody($fileReader);
                Controller::applyHook("node.read", array(&$node));

                break;

            case "prepare_chunk_dl" :

                $chunkCount = intval($httpVars["chunk_count"]);
                $node = $selection->getUniqueNode();

                $fileId = $node->getUrl();
                $sessionKey = "chunk_file_".md5($fileId.time());
                $totalSize = filesize($fileId);
                $chunkSize = intval ( $totalSize / $chunkCount );
                $realFile  = AJXP_MetaStreamWrapper::getRealFSReference($fileId, true);
                $chunkData = array(
                    "localname"	  => basename($fileId),
                    "chunk_count" => $chunkCount,
                    "chunk_size"  => $chunkSize,
                    "total_size"  => $totalSize,
                    "file_id"	  => $sessionKey
                );

                $_SESSION[$sessionKey] = array_merge($chunkData, array("file"=>$realFile));
                $response = $response->withHeader("Content-type", "application/json; charset=UTF-8");
                $response->getBody()->write(json_encode($chunkData));

                Controller::applyHook("node.read", array(&$node));

                break;

            case "download_chunk" :

                $chunkIndex = intval($httpVars["chunk_index"]);
                $chunkKey = $httpVars["file_id"];
                $sessData = $_SESSION[$chunkKey];
                $realFile = $sessData["file"];
                $chunkSize = $sessData["chunk_size"];
                $offset = $chunkSize * $chunkIndex;
                if ($chunkIndex == $sessData["chunk_count"]-1) {
                    // Compute the last chunk real length
                    $chunkSize = $sessData["total_size"] - ($chunkSize * ($sessData["chunk_count"]-1));
                    if ($selection->nodeForPath("/")->wrapperIsRemote()) {
                        register_shutdown_function("unlink", $realFile);
                    }
                }
                $fileReader = new FileReaderResponse($realFile);
                $fileReader->setLocalName($sessData["localname"].".".sprintf("%03d", $chunkIndex+1));
                $fileReader->setPartial($offset, $chunkSize);
                $response = $response->withBody($fileReader);

                break;

            default:
                break;
        }

    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @throws PydioException
     * @throws \Exception
     */
    public function switchAction(ServerRequestInterface &$request, ResponseInterface &$response)
    {
        parent::accessPreprocess($request);
        $this->filterByApi($request);

        /** @var ContextInterface $ctx */
        $ctx = $request->getAttribute("ctx");
        $action = $request->getAttribute("action");
        $httpVars = $request->getParsedBody();

        $selection = UserSelection::fromContext($ctx, $httpVars);
        if (!$selection->isEmpty()) {
            $this->filterUserSelectionToHidden($ctx, $selection->getFiles());
            RecycleBinManager::filterActions($action, $selection, $httpVars);
        }
        $mess = ConfService::getMessages();
        $nodesDiffs = new NodesDiff();

        switch ($action) {

            case "compress" :

                $taskId = $request->getAttribute("pydio-task-id");
                if($request->getAttribute("pydio-task-id") === null){
                    $task = TaskService::actionAsTask($ctx, $action, $httpVars);
                    $task->setFlags(Task::FLAG_STOPPABLE);
                    $response = TaskService::getInstance()->enqueueTask($task, $request, $response);
                    break;
                }

                if($taskId !== null){
                    TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_RUNNING, "Starting compression in background");
                }
                $dir = Utils::decodeSecureMagic($httpVars["dir"], AJXP_SANITIZE_DIRNAME);
                $currentDirNode = $selection->nodeForPath($dir);
                // Make a temp zip
                $loggedUser = $ctx->getUser();
                if (isSet($httpVars["archive_name"])) {
                    $localName = Utils::decodeSecureMagic($httpVars["archive_name"]);
                    $this->filterUserSelectionToHidden($ctx, array($localName));
                } else {
                    $localName = (basename($dir)==""?"Files":basename($dir)).".zip";
                }
                $file = Utils::getAjxpTmpDir()."/".($loggedUser?$loggedUser->getId():"shared")."_".time()."tmpCompression.zip";
                if(isSet($httpVars["compress_flat"])) $baseDir = "__AJXP_ZIP_FLAT__/";
                else $baseDir = $dir;
                $zipFile = $this->makeZip($selection, $file, $baseDir, $taskId);
                if(!$zipFile) throw new PydioException("Error while compressing file $localName");
                register_shutdown_function("unlink", $file);
                $urlBase = $selection->currentBaseUrl();
                $tmpFNAME = $urlBase.$dir."/".str_replace(".zip", ".tmp", $localName);
                copy($file, $tmpFNAME);
                try {
                    Controller::applyHook("node.before_create", array(new AJXP_Node($tmpFNAME), filesize($tmpFNAME)));
                } catch (\Exception $e) {
                    @unlink($tmpFNAME);
                    throw $e;
                }
                @rename($tmpFNAME, $urlBase.$dir."/".$localName);
                $newArchiveNode = $currentDirNode->getParent()->createChildNode($localName);
                Controller::applyHook("node.change", array(null, $newArchiveNode, false), true);
                if($taskId !== null){
                    TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_COMPLETE, "Finished compression in background");
                }

            break;

            case "stat" :

                clearstatcache();
                $jsonData = new \stdClass;
                if($selection->isUnique()){
                    $stat = @stat($selection->getUniqueNode()->getUrl());
                    if ($stat !== false && is_readable($selection->getUniqueNode()->getUrl())) {
                        $jsonData = $stat;
                    }
                }else{
                    $nodes = $selection->buildNodes();
                    foreach($nodes as $node){
                        $stat = @stat($node->getUrl());
                        if(!$stat || !is_readable($node->getUrl())) {
                            $stat = new \stdClass();
                        }
                        $path = $node->getPath();
                        $jsonData->$path = $stat;
                    }
                }
                $response = new Response\JsonResponse($jsonData);

            break;


            //------------------------------------
            //	ONLINE EDIT
            //------------------------------------
            case "put_content":

                if(!isset($httpVars["content"])) break;
                // Load "code" variable directly from POST array, do not "securePath" or "sanitize"...
                $code = $httpVars["content"];
                $currentNode = $selection->getUniqueNode();
                $fileName = $currentNode->getUrl();
                $this->logInfo("Online Edition", array("files"=> $this->addSlugToPath($fileName)));
                if (isSet($httpVars["encode"]) && $httpVars["encode"] == "base64") {
                    $code = base64_decode($code);
                } else {
                    $code=str_replace("&lt;","<",TextEncoder::magicDequote($code));
                }
                $response = $response->withHeader("Content-Type", "text/plain");
                try {
                    Controller::applyHook("node.before_change", array(&$currentNode, strlen($code)));
                } catch (\Exception $e) {
                    $response->getBody()->write($e->getMessage());
                    break;
                }
                if (!is_file($fileName) || !$this->isWriteable($currentNode)) {
                    $response->getBody()->write((!$this->isWriteable($currentNode)?"1001":"1002"));
                    break;
                }
                $fp=fopen($fileName,"w");
                fputs ($fp,$code);
                fclose($fp);
                clearstatcache(true, $fileName);
                Controller::applyHook("node.change", array($currentNode, $currentNode, false));
                $response->getBody()->write($mess[115]);

            break;

            //------------------------------------
            //	DELETE
            //  Warning, must be kept BEFORE copy/move
            //  as recyclebin filtering can transform
            //  it move action.
            //------------------------------------
            case "delete":

                if ($selection->isEmpty()) {
                    throw new PydioException("", 113);
                }
                $size = 0;
                $nodes = $selection->buildNodes();
                $bgSizeThreshold = 1*1024*1024;
                $bgWorkerThreshold = 80*1024*1024;
                foreach($nodes as $node){
                    $size += $node->getSizeRecursive();
                }
                $taskId = $request->getAttribute("pydio-task-id");
                if($taskId === null && ($size > $bgSizeThreshold)){
                    $task = TaskService::actionAsTask($ctx, $action, $httpVars);
                    if($size > $bgWorkerThreshold){
                        $task->setSchedule(new Schedule(Schedule::TYPE_ONCE_DEFER));
                    }
                    $response = TaskService::getInstance()->enqueueTask($task, $request, $response);
                    break;
                }

                $logMessages = array();
                $errorMessage = $this->delete($selection, $logMessages, $taskId);
                if (count($logMessages)) {
                    $logMessage = new UserMessage(join("\n", $logMessages));
                }
                if($errorMessage) {
                    throw new PydioException(TextEncoder::toUTF8($errorMessage));
                }
                $this->logInfo("Delete", array("files"=>$this->addSlugToPath($selection)));
                $nodesDiffs->remove($selection->getFiles());

            break;

            //------------------------------------
            //	COPY / MOVE
            //------------------------------------
            case "copy":
            case "move":

                if ($selection->isEmpty()) {
                    throw new PydioException("", 113);
                }
                $taskId = $request->getAttribute("pydio-task-id");
                // Compute copy size
                $size = 0;
                $nodes = $selection->buildNodes();
                $bgSizeThreshold = 10*1024*1024;
                $bgWorkerThreshold = 80*1024*1024;
                foreach($nodes as $node){
                    $size += $node->getSizeRecursive();
                }
                if($taskId === null && ($size > $bgSizeThreshold)){
                    $task = TaskService::actionAsTask($ctx, $action, $httpVars);
                    $task->setFlags(Task::FLAG_STOPPABLE);
                    if($size > $bgWorkerThreshold){
                        $task->setSchedule(new Schedule(Schedule::TYPE_ONCE_DEFER));
                    }
                    $response = TaskService::getInstance()->enqueueTask($task, $request, $response);
                    break;
                }
                if(!empty($taskId)){
                    TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_RUNNING, "Starting operation in background");
                }
                $loggedUser = $ctx->getUser();
                if($loggedUser != null && !$loggedUser->canWrite($ctx->getRepositoryId())){
                    throw new PydioException("You are not allowed to write", 207);
                }
                $success = $error = array();
                $destPath = Utils::decodeSecureMagic($httpVars["dest"]);
                $targetBaseName = null;
                if($selection->isUnique() && isSet($httpVars["targetBaseName"])){
                    $targetBaseName = $httpVars["targetBaseName"];
                }
                $this->filterUserSelectionToHidden($ctx, array($httpVars["dest"]));
                if ($selection->inZip()) {
                    // Set action to copy anycase (cannot move from the zip).
                    $action = "copy";
                    $this->extractArchive($destPath, $selection, $error, $success, $taskId);
                } else {
                    $move = ($action == "move" ? true : false);
                    if ($move && isSet($httpVars["force_copy_delete"])) {
                        $move = false;
                    }
                    $this->copyOrMove($destPath, $selection, $error, $success, $move, $targetBaseName, $taskId);

                }

                if (count($error)) {
                    if(!empty($taskId)) TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_FAILED, "Error while copy/move: ".TextEncoder::toUTF8(join("\n", $error)));
                    throw new PydioException(TextEncoder::toUTF8(join("\n", $error)));
                } else {
                    if (isSet($httpVars["force_copy_delete"])) {
                        $errorMessage = $this->delete($selection, $logMessages, $taskId);
                        if($errorMessage) {
                            if(!empty($taskId)) {
                                TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_FAILED, "Error while deleting data: ".TextEncoder::toUTF8($errorMessage));
                            }
                            throw new PydioException(TextEncoder::toUTF8($errorMessage));
                        }
                        $this->logInfo("Copy/Delete", array("files"=>$this->addSlugToPath($selection), "destination" => $this->addSlugToPath($destPath)));
                    } else {
                        $this->logInfo(($action=="move"?"Move":"Copy"), array("files"=>$this->addSlugToPath($selection), "destination"=>$this->addSlugToPath($destPath)));
                    }
                    $logMessage = new UserMessage(join("\n", $success));
                }
                // Assume new nodes are correctly created
                $destNode = $selection->nodeForPath($destPath);
                foreach ($nodes as $selectedNode) {
                    $newNode = $destNode->createChildNode((isSet($targetBaseName)?$targetBaseName : $selectedNode->getLabel()));
                    if($action == "move"){
                        $nodesDiffs->update($newNode, $selectedNode->getPath());
                    }else{
                        $nodesDiffs->add($newNode);
                    }
                }

                if(!empty($taskId)) {
                    TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_COMPLETE, "");
                    Controller::applyHook("msg.instant", array($ctx, $nodesDiffs->toXML()));
                }

                break;


            case "purge" :


                $hardPurgeTime = intval($ctx->getRepository()->getContextOption($ctx, "PURGE_AFTER"))*3600*24;
                $softPurgeTime = intval($ctx->getRepository()->getContextOption($ctx, "PURGE_AFTER_SOFT"))*3600*24;
                $shareCenter = PluginsService::findPluginById('action.share');
                if( !($shareCenter && $shareCenter->isEnabled()) ) {
                    //action.share is disabled, don't look at the softPurgeTime
                    $softPurgeTime = 0;
                }
                if ($hardPurgeTime > 0 || $softPurgeTime > 0) {
                    $this->recursivePurge($selection->currentBaseUrl(), $hardPurgeTime, $softPurgeTime);
                }

            break;

            //------------------------------------
            //	RENAME
            //------------------------------------
            case "rename":

                $originalNode = $selection->getUniqueNode();
                $destNode = null;
                $filename_new = "";
                if (isSet($httpVars["dest"])) {
                    $dest = Utils::decodeSecureMagic($httpVars["dest"]);
                    $destNode = $selection->nodeForPath($dest);
                    $this->filterUserSelectionToHidden($ctx, array($destNode->getLabel()));
                }else if(isSet($httpVars["filename_new"])){
                    $filename_new = Utils::decodeSecureMagic($httpVars["filename_new"]);
                    $this->filterUserSelectionToHidden($ctx, array($filename_new));
                }
                $renamedNode = $this->rename($originalNode, $destNode, $filename_new);

                $logMessage = new UserMessage(TextEncoder::toUTF8($originalNode->getLabel())." $mess[41] ".TextEncoder::toUTF8($renamedNode->getLabel()));
                $nodesDiffs->update($renamedNode, $originalNode->getPath());
                $this->logInfo("Rename", [
                    "files"     => $this->addSlugToPath($originalNode->getUrl()),
                    "original"  => $this->addSlugToPath($originalNode->getUrl()),
                    "new"       => $this->addSlugToPath($renamedNode->getUrl())
                ]);

            break;

            //------------------------------------
            //	CREER UN REPERTOIRE / CREATE DIR
            //------------------------------------
            case "mkdir":

                $messtmp="";
                $files = $selection->getFiles();
                if(isSet($httpVars["dir"]) && isSet($httpVars["dirname"])){
                    $files[] =
                        rtrim(Utils::decodeSecureMagic($httpVars["dir"], AJXP_SANITIZE_DIRNAME), "/")
                        ."/".
                        Utils::decodeSecureMagic($httpVars["dirname"], AJXP_SANITIZE_FILENAME);
                }
                $messages = array();
                $errors = array();
                $max_length = ConfService::getCoreConf("NODENAME_MAX_LENGTH");
                foreach($files as $newDirPath){
                    $parentDir = Utils::safeDirname($newDirPath);
                    $basename = Utils::safeBasename($newDirPath);
                    $basename = substr($basename, 0, $max_length);
                    $this->filterUserSelectionToHidden($ctx, array($basename));
                    $parentNode = $selection->nodeForPath($parentDir);
                    try{
                        Controller::applyHook("node.before_create", array(new AJXP_Node($parentDir."/".$basename), -2));
                    }catch (PydioException $e){
                        $errors[] = $e->getMessage();
                        continue;
                    }
                    try{
                        $newNode = $this->mkDir($parentNode, $basename, isSet($httpVars["ignore_exists"])?true:false);
                    }catch(PydioException $ex){
                        $errors[] = $ex->getMessage();
                        continue;
                    }
                    if(empty($newNode)){
                        continue;
                    }
                    $messtmp.="$mess[38] ".TextEncoder::toUTF8($basename)." $mess[39] ";
                    if ($parentDir=="") {$messtmp.="/";} else {$messtmp.= TextEncoder::toUTF8($parentDir);}
                    $messages[] = $messtmp;
                    $nodesDiffs->add($newNode);
                    $this->logInfo("Create Dir", array("dir"=>$this->addSlugToPath($parentDir)."/".$basename, "files"=>$this->addSlugToPath($parentDir)."/".$basename));
                }
                if(count($errors)){
                    if(!count($messages)){
                        throw new PydioException(implode('', $errors));
                    }else{
                        $messages = array_merge($messages, $errors);
                    }
                }
                $logMessage = new UserMessage(implode("<br>", $messages));


            break;

            //------------------------------------
            //	CREER UN FICHIER / CREATE FILE
            //------------------------------------
            case "mkfile":

                if(empty($httpVars["filename"]) && isSet($httpVars["node"])){
                    $filename= Utils::decodeSecureMagic($httpVars["node"]);
                }else{
                    $parent = rtrim(Utils::decodeSecureMagic($httpVars["dir"], AJXP_SANITIZE_DIRNAME), "/");
                    $filename = $parent ."/" . Utils::decodeSecureMagic($httpVars["filename"], AJXP_SANITIZE_FILENAME);
                }
                $filename = substr($filename, 0, ConfService::getCoreConf("NODENAME_MAX_LENGTH"));
                $this->filterUserSelectionToHidden($ctx, array($filename));
                $node = $selection->nodeForPath($filename);
                $content = "";
                if (isSet($httpVars["content"])) {
                    $content = $httpVars["content"];
                }
                $forceCreation = false;
                if (isSet($httpVars["force"]) && $httpVars["force"] == "true"){
                    $forceCreation = true;
                }
                $this->createEmptyFile($node, $content, $forceCreation);
                $logMessage = new UserMessage($mess[34]." ".$node->getLabel()." ".$mess[39]." ". $node->getParent()->getPath());
                $this->logInfo("Create File", array("files"=>$this->addSlugToPath($node->getPath())));
                $node->loadNodeInfo();
                $nodesDiffs->add($node);

            break;

            //------------------------------------
            //	CHANGE FILE PERMISSION
            //------------------------------------
            case "chmod":

                $nodes = $selection->buildNodes();
                $changedFiles = array();
                $chmod_value = $httpVars["chmod_value"];
                $recursive = $httpVars["recursive"];
                $recur_apply_to = $httpVars["recur_apply_to"];
                foreach ($nodes as $node) {
                    $this->chmod($node, $chmod_value, ($recursive=="on"), ($recursive=="on"?$recur_apply_to:"both"), $changedFiles);
                }
                $logMessage= new UserMessage("Successfully changed permission to ".$chmod_value." for ".count($changedFiles)." files or folders");
                $this->logInfo("Chmod", [
                    "files"         => array_map(array($this, "addSlugToPath"), $selection->getFiles()),
                    "filesCount"    =>count($changedFiles)
                ]);
                $nodesDiffs->update($nodes);

            break;

            case "lsync" :

                if (!ConfService::currentContextIsCommandLine()) {
                    //die("This command must be accessed via CLI only.");
                }
                $fromNode = null;
                $toNode = null;
                $copyOrMove = false;
                if (isSet($httpVars["from"])) {
                    $fromNode = $selection->nodeForPath(Utils::decodeSecureMagic($httpVars["from"]));
                }
                if (isSet($httpVars["to"])) {
                    $toNode = $selection->nodeForPath(Utils::decodeSecureMagic($httpVars["to"]));
                }
                if (isSet($httpVars["copy"]) && $httpVars["copy"] == "true") {
                    $copyOrMove = true;
                }
                Controller::applyHook("node.change", array($fromNode, $toNode, $copyOrMove));

            break;

            //------------------------------------
            //	XML LISTING
            //------------------------------------
            case "ls":

                $nodesList = new NodesList();

                if($selection->isUnique() && $request->getAttribute("api") == "v2" && !empty($httpVars["children"])){
                    $dir = $selection->getUniqueFile();
                    $selection->setFiles([]);
                }else{
                    $dir = Utils::sanitize($httpVars["dir"], AJXP_SANITIZE_DIRNAME) OR "";
                }
                if (AJXP_MetaStreamWrapper::actualRepositoryWrapperClass($this->repository->getId()) == "fsAccessWrapper") {
                    $dir = fsAccessWrapper::patchPathForBaseDir($dir);
                }
                $dir = Utils::securePath($dir);

                // FILTER DIR PAGINATION ANCHOR
                $page = null;
                if (isSet($dir) && strstr($dir, "%23")!==false) {
                    $parts = explode("%23", $dir);
                    $dir = $parts[0];
                    $page = $parts[1];
                }

                if(!isSet($dir) || $dir == "/") $dir = "";
                $lsOptions = $this->parseLsOptions((isSet($httpVars["options"])?$httpVars["options"]:"a"));

                $startTime = microtime();
                $path = $selection->nodeForPath(($dir!= ""?($dir[0]=="/"?"":"/").$dir:""))->getUrl();
                $nonPatchedPath = $path;
                if (AJXP_MetaStreamWrapper::actualRepositoryWrapperClass($this->repository->getId()) == "fsAccessWrapper") {
                    $nonPatchedPath = fsAccessWrapper::unPatchPathForBaseDir($path);
                }
                $testPath = @stat($path);
                if($testPath === null || $testPath === false){
                    throw new \Exception("There was a problem trying to open folder ". $path. ", please check your Administrator");
                }
                if(!is_readable($path) && !is_writeable($path)){
                    throw new \Exception("You are not allowed to access folder " . $path);
                }
                // Backward compat
                if($selection->isUnique() && strpos($selection->getUniqueFile(), "/") !== 0){
                    $selection->setFiles(array($dir . "/" . $selection->getUniqueFile()));
                }
                
                $orderField = $orderDirection = null;
                $threshold          = 500;
                $limitPerPage       = 200;
                $defaultOrder       = $ctx->getRepository()->getContextOption($ctx, "REMOTE_SORTING_DEFAULT_COLUMN");
                $defaultDirection   = $ctx->getRepository()->getContextOption($ctx, "REMOTE_SORTING_DEFAULT_DIRECTION");
                if ($ctx->getRepository()->getContextOption($ctx, "REMOTE_SORTING")) {
                    $orderDirection = isSet($httpVars["order_direction"])?strtolower($httpVars["order_direction"]):$defaultDirection;
                    $orderField = isSet($httpVars["order_column"])?$httpVars["order_column"]:$defaultOrder;
                    if ($orderField != null && !in_array($orderField, array("ajxp_label", "filesize", "ajxp_modiftime", "mimestring"))) {
                        $orderField = $defaultOrder;
                    }
                }
                if(!isSet($httpVars["recursive"]) || $httpVars["recursive"] != "true"){
                    $threshold = $ctx->getRepository()->getContextOption($ctx, "PAGINATION_THRESHOLD");
                    if(!isSet($threshold) || intval($threshold) == 0) $threshold = 500;
                    $limitPerPage = $ctx->getRepository()->getContextOption($ctx, "PAGINATION_NUMBER");
                    if(!isset($limitPerPage) || intval($limitPerPage) == 0) $limitPerPage = 200;
                }

                if(!$selection->isEmpty()){
                    $uniqueNodes = $selection->buildNodes();
                    $parentAjxpNode = $selection->nodeForPath("/");
                    Controller::applyHook("node.read", array(&$parentAjxpNode));
                    $nodesList->setParentNode($parentAjxpNode);
                    foreach($uniqueNodes as $node){
                        if(!file_exists($node->getUrl()) || (!is_readable($node->getUrl()) && !is_writable($node->getUrl()))) continue;
                        $nodeName = $node->getLabel();
                        if (!$this->filterNodeName($ctx, $node->getPath(), $nodeName, $isLeaf, $lsOptions)) {
                            continue;
                        }
                        if (RecycleBinManager::recycleEnabled() && $node->getPath() == RecycleBinManager::getRecyclePath()) {
                            continue;
                        }
                        $node->loadNodeInfo(false, false, ($lsOptions["l"]?"all":"minimal"));
                        if (!empty($node->metaData["nodeName"]) && $node->metaData["nodeName"] != $nodeName) {
                            $node->setUrl(dirname($node->getUrl())."/".$node->metaData["nodeName"]);
                        }
                        if (!empty($node->metaData["hidden"]) && $node->metaData["hidden"] === true) {
                            continue;
                        }
                        if (!empty($node->metaData["mimestring_id"]) && array_key_exists($node->metaData["mimestring_id"], $mess)) {
                            $node->mergeMetadata(array("mimestring" =>  $mess[$node->metaData["mimestring_id"]]));
                        }
                        if(isSet($httpVars["page_position"]) && $httpVars["page_position"] == "true"){
                            // Detect page position: we have to loading "siblings"
                            $parentPath = Utils::safeDirname($node->getPath());
                            $siblings = scandir($selection->currentBaseUrl().$parentPath);
                            foreach($siblings as $i => $s){
                                if($this->filterFile($ctx, $s, true)) unset($siblings[$i]);
                                if($this->filterFolder($ctx, $s)) unset($siblings[$i]);
                            }
                            if(count($siblings) > $threshold){
                                //usort($siblings, "strcasecmp");
                                $siblings = $this->orderNodes($siblings, $selection->currentBaseUrl().$parentPath, $orderField, $orderDirection);
                                $index = array_search($node->getLabel(), $siblings);
                                $node->mergeMetadata(array("page_position" => floor($index / $limitPerPage) +1));
                            }
                        }
                        $nodesList->addBranch($node);
                    }
                    break;
                }

                $metaData = array();
                if (RecycleBinManager::recycleEnabled() && $dir == "") {
                    $metaData["repo_has_recycle"] = "true";
                }
                $parentAjxpNode = new AJXP_Node($nonPatchedPath, $metaData);
                $parentAjxpNode->loadNodeInfo(false, true, ($lsOptions["l"]?"all":"minimal"));
                Controller::applyHook("node.read", array(&$parentAjxpNode));

                $streamIsSeekable = AJXP_MetaStreamWrapper::wrapperIsSeekable($path);

                $sharedHandle = null; $handle = null;
                if($streamIsSeekable){
                    $handle = opendir($path);
                    $sharedHandle = $handle;
                }
                $countFiles = $this->countChildren($parentAjxpNode, !$lsOptions["f"], false, $sharedHandle);
                if(isSet($sharedHandle)){
                    rewind($handle);
                }
                $totalPages = $crtPage = 1;
                if (isSet($threshold) && isSet($limitPerPage) && $countFiles > $threshold) {
                    $offset = 0;
                    $crtPage = 1;
                    if (isSet($page)) {
                        $offset = (intval($page)-1)*$limitPerPage;
                        $crtPage = $page;
                    }
                    $totalPages = floor($countFiles / $limitPerPage) + 1;
                } else {
                    $offset = $limitPerPage = 0;
                }

                $nodesList->setParentNode($parentAjxpNode);
                if (isSet($totalPages) && isSet($crtPage) && ($totalPages > 1 || ! Utils::userAgentIsNativePydioApp())) {
                    $remoteOptions = null;
                    if ($this->getContextualOption($ctx, "REMOTE_SORTING")) {
                        $remoteOptions = array(
                            "remote_order" => "true",
                            "currentOrderCol" => isSet($orderField)?$orderField:$defaultOrder,
                            "currentOrderDir"=> isSet($orderDirection)?$orderDirection:$defaultDirection
                        );
                    }
                    $foldersCounts = $this->countChildren($parentAjxpNode, TRUE, false, $sharedHandle);
                    if(isSet($sharedHandle)) {
                        rewind($sharedHandle);
                    }
                    $nodesList->setPaginationData($countFiles, $crtPage, $totalPages, $foldersCounts, $remoteOptions);
                    if ($totalPages > 1 && !$lsOptions["f"]) {
                        if(isSet($sharedHandle)) {
                            closedir($sharedHandle);
                        }
                        break;
                    }
                }

                $cursor = 0;
                if(isSet($sharedHandle)){
                    $handle = $sharedHandle;
                }else{
                    $handle = opendir($path);
                }
                if (!$handle) {
                    throw new PydioException("Cannot open dir ".$nonPatchedPath);
                }
                $nodes = array();
                while(false !== ($file = readdir($handle))){
                    $nodes[] = $file;
                }
                closedir($handle);
                $fullList = array("d" => array(), "z" => array(), "f" => array());

                //$nodes = scandir($path);
                $nodes = $this->orderNodes($nodes, $nonPatchedPath, $orderField, $orderDirection);

                foreach ($nodes as $nodeName) {
                    if($nodeName == "." || $nodeName == "..") {
                        continue;
                    }
                    $isLeaf = "";
                    if (!$this->filterNodeName($ctx, $path, $nodeName, $isLeaf, $lsOptions)) {
                        continue;
                    }
                    if (RecycleBinManager::recycleEnabled() && $dir == "" && "/".$nodeName == RecycleBinManager::getRecyclePath()) {
                        continue;
                    }
                    if ($offset > 0 && $cursor < $offset) {
                        $cursor ++;
                        continue;
                    }

                    if ($limitPerPage > 0 && ($cursor - $offset) >= $limitPerPage) {
                        break;
                    }

                    $currentFile = $nonPatchedPath."/".$nodeName;
                    $meta = array();
                    if($isLeaf != "") $meta = array("is_file" => ($isLeaf?"1":"0"));
                    $node = new AJXP_Node($currentFile, $meta);
                    $node->setLabel($nodeName);
                    $node->loadNodeInfo(false, false, ($lsOptions["l"]?"all":"minimal"));
                    if (!empty($node->metaData["nodeName"]) && $node->metaData["nodeName"] != $nodeName) {
                        $node->setUrl($nonPatchedPath."/".$node->metaData["nodeName"]);
                    }
                    if (!empty($node->metaData["hidden"]) && $node->metaData["hidden"] === true) {
                           continue;
                       }
                    if (!empty($node->metaData["mimestring_id"]) && array_key_exists($node->metaData["mimestring_id"], $mess)) {
                        $node->mergeMetadata(array("mimestring" =>  $mess[$node->metaData["mimestring_id"]]));
                    }
                    if (isSet($originalLimitPerPage) && $cursor > $originalLimitPerPage) {
                        $node->mergeMetadata(array("page_position" => floor($cursor / $originalLimitPerPage) +1));
                    }

                    $nodeType = "d";
                    if ($node->isLeaf()) {
                        if (Utils::isBrowsableArchive($nodeName)) {
                            if ($lsOptions["f"] && $lsOptions["z"]) {
                                $nodeType = "f";
                            } else {
                                $nodeType = "z";
                            }
                        } else $nodeType = "f";
                    }
                    // There is a special sorting, cancel the reordering of files & folders.
                    if(isSet($orderField) && $orderField != "ajxp_label" && !(isSet($httpVars["recursive"]) && $httpVars["recursive"] == "true" )) {
                        $nodeType = "f";
                    }
                    $fullList[$nodeType][$nodeName] = $node;
                    $cursor ++;
                }
                if (isSet($httpVars["recursive"]) && $httpVars["recursive"] == "true") {

                    $max_depth = (isSet($httpVars["max_depth"])?intval($httpVars["max_depth"]):0);
                    $max_nodes = (isSet($httpVars["max_nodes"])?intval($httpVars["max_nodes"]):0);
                    $crt_depth = (isSet($httpVars["crt_depth"])?intval($httpVars["crt_depth"])+1:1);
                    $crt_nodes = (isSet($httpVars["crt_nodes"])?intval($httpVars["crt_nodes"]):0);
                    $crt_nodes += $countFiles;

                    $breakNow = false;
                    if(isSet($max_depth) && $max_depth > 0 && $crt_depth >= $max_depth) $breakNow = true;
                    if(isSet($max_nodes) && $max_nodes > 0 && $crt_nodes >= $max_nodes) $breakNow = true;
                    /**
                     * @var $nodeDir AJXP_Node
                     */
                    foreach ($fullList["d"] as &$nodeDir) {
                        if($breakNow){
                            $nodeDir->mergeMetadata(array("ajxp_has_children" => $this->countChildren($nodeDir, false, true)?"true":"false"));
                            $nodesList->addBranch($nodeDir);
                            continue;
                        }
                        $newBody = array(
                            "dir" => TextEncoder::toUTF8($nodeDir->getPath()),
                            "options"=> $httpVars["options"],
                            "recursive" => "true",
                            "max_depth"=> $max_depth,
                            "max_nodes"=> $max_nodes,
                            "crt_depth"=> $crt_depth,
                            "crt_nodes"=> $crt_nodes,
                        );
                        $fakeRequest = Controller::executableRequest($request->getAttribute("ctx"), "ls", $newBody);
                        $fakeRequest = $fakeRequest->withAttribute("parent_node_list", $nodesList);
                        $this->switchAction($fakeRequest, new Response());
                    }

                } else {

                    array_map(array($nodesList, "addBranch"), $fullList["d"]);

                }
                array_map(array($nodesList, "addBranch"), $fullList["z"]);
                array_map(array($nodesList, "addBranch"), $fullList["f"]);

                // ADD RECYCLE BIN TO THE LIST
                if ($dir == ""  && $lsOptions["d"] && RecycleBinManager::recycleEnabled() && $this->getContextualOption($ctx, "HIDE_RECYCLE") !== true) {
                    $recycleBinOption = RecycleBinManager::getRelativeRecycle();
                    $recycleNode = $selection->nodeForPath("/".$recycleBinOption);
                    if (file_exists($recycleNode->getUrl())) {
                        $recycleNode->loadNodeInfo();
                        $nodesList->addBranch($recycleNode);
                    }
                }

                $this->logDebug("LS Time : ".intval((microtime()-$startTime)*1000)."ms");

                $parentList = $request->getAttribute("parent_node_list", null);
                if($parentList !== null){
                    $parentList->addBranch($nodesList);
                }

            break;
        }


        if(isSet($logMessage) || !$nodesDiffs->isEmpty() || isSet($nodesList)){
            $body = new SerializableResponseStream();
            if(isSet($logMessage)) {
                $body->addChunk($logMessage);
            }
            if(!$nodesDiffs->isEmpty()) {
                $body->addChunk($nodesDiffs);
            }
            if(isSet($nodesList)) {
                $body->addChunk($nodesList);
            }
            $response = $response->withBody($body);
        }

    }

    protected function orderNodes($nodes, $path, $orderField, $orderDirection){

        usort($nodes, "strcasecmp");
        if (!empty($orderField) && !empty($orderDirection) && $orderField == "ajxp_label" && $orderDirection == "desc") {
            $nodes = array_reverse($nodes);
        }
        if (!empty($this->driverConf["SCANDIR_RESULT_SORTFONC"])) {
            usort($nodes, $this->driverConf["SCANDIR_RESULT_SORTFONC"]);
        }
        if (!empty($orderField) && !empty($orderDirection) && $orderField != "ajxp_label") {
            $toSort = array();
            foreach ($nodes as $node) {
                if($orderField == "filesize") $toSort[$node] = is_file($path."/".$node) ? filesize($path."/".$node) : 0;
                else if($orderField == "ajxp_modiftime") $toSort[$node] = filemtime($path."/".$node);
                else if($orderField == "mimestring") $toSort[$node] = pathinfo($node, PATHINFO_EXTENSION);
            }
            if($orderDirection == "asc") asort($toSort);
            else arsort($toSort);
            $nodes = array_keys($toSort);
        }
        return $nodes;

    }

    public function parseLsOptions($optionString)
    {
        // LS OPTIONS : dz , a, d, z, all of these with or without l
        // d : directories
        // z : archives
        // f : files
        // => a : all, alias to dzf
        // l : list metadata
        $allowed = array("a", "d", "z", "f", "l");
        $lsOptions = array();
        foreach ($allowed as $key) {
            if (strchr($optionString, $key)!==false) {
                $lsOptions[$key] = true;
            } else {
                $lsOptions[$key] = false;
            }
        }
        if ($lsOptions["a"]) {
            $lsOptions["d"] = $lsOptions["z"] = $lsOptions["f"] = true;
        }
        return $lsOptions;
    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $ajxpNode
     * @param bool $parentNode
     * @param bool $details
     * @return void
     */
    public function loadNodeInfo(&$ajxpNode, $parentNode = false, $details = false)
    {
        $mess = ConfService::getMessages();

        $nodeName = basename($ajxpNode->getPath());
        $metaData = $ajxpNode->metadata;
        if (!isSet($metaData["is_file"])) {
            $isLeaf = is_file($ajxpNode->getUrl()) || Utils::isBrowsableArchive($nodeName);
            $metaData["is_file"] = ($isLeaf?"1":"0");
        } else {
            $isLeaf = $metaData["is_file"] == "1" ? true : false;
        }
        $metaData["filename"] = $ajxpNode->getPath();

        if (RecycleBinManager::recycleEnabled() && $ajxpNode->getPath() == RecycleBinManager::getRelativeRecycle()) {
            $recycleIcon = ($this->countChildren($ajxpNode, false, true)>0?"trashcan_full.png":"trashcan.png");
            $metaData["icon"] = $recycleIcon;
            $metaData["mimestring"] = $mess[122];
            $ajxpNode->setLabel($mess[122]);
            $metaData["ajxp_mime"] = "ajxp_recycle";
        } else {
            $mimeData = Utils::mimeData($ajxpNode->getUrl(), !$isLeaf);
            $metaData["mimestring_id"] = $mimeData[0]; //AJXP_Utils::mimetype($ajxpNode->getUrl(), "type", !$isLeaf);
            $metaData["icon"] = $mimeData[1]; //AJXP_Utils::mimetype($nodeName, "image", !$isLeaf);
            if ($metaData["icon"] == "folder.png") {
                $metaData["openicon"] = "folder_open.png";
            }
            if (!$isLeaf) {
                $metaData["ajxp_mime"] = "ajxp_folder";
            }
        }
        //if ($lsOptions["l"]) {

        $metaData["file_group"] = @filegroup($ajxpNode->getUrl()) || "unknown";
        $metaData["file_owner"] = @fileowner($ajxpNode->getUrl()) || "unknown";
        $metaData["ajxp_readonly"] = "false";
        if (!@$this->isWriteable($ajxpNode)) {
           $metaData["ajxp_readonly"] = "true";
        }
        $fPerms = @fileperms($ajxpNode->getUrl());
        if ($fPerms !== false) {
            $fPerms = substr(decoct( $fPerms ), ($isLeaf?2:1));
        } else {
            $fPerms = '0000';
        }
        $metaData["file_perms"] = $fPerms;
        $datemodif = $this->date_modif($ajxpNode->getUrl());
        $metaData["ajxp_modiftime"] = ($datemodif ? $datemodif : "0");
        $metaData["ajxp_description"] =$metaData["ajxp_relativetime"] = $mess[4]." ".Utils::relativeDate($datemodif, $mess);
        $metaData["bytesize"] = 0;
        if ($isLeaf) {
            $metaData["bytesize"] = filesize($ajxpNode->getUrl());
        }
        $metaData["filesize"] = Utils::roundSize($metaData["bytesize"]);
        if (Utils::isBrowsableArchive($nodeName)) {
            $metaData["ajxp_mime"] = "ajxp_browsable_archive";
        }

        if ($details == "minimal") {
            $miniMeta = array(
                "is_file" => $metaData["is_file"],
                "filename" => $metaData["filename"],
                "bytesize" => $metaData["bytesize"],
                "ajxp_modiftime" => $metaData["ajxp_modiftime"],
            );
            $ajxpNode->mergeMetadata($miniMeta);
        } else {
            $ajxpNode->mergeMetadata($metaData);
        }

    }

    /**
     * @param array|UploadedFileInterface $uploadData Php-upload array
     * @param String $destination Full path to destination file, including stream data
     * @param array $messages Application messages table
     * @return bool
     * @throws \Exception
     */
    protected function copyUploadedData($uploadData, $destination, $messages){
        if(is_array($uploadData)){
            $isInputStream = isSet($uploadData["input_upload"]);
            $newFileSize = $uploadData["size"];
        }else{
            $isInputStream = $uploadData->getStream() !== null;
            $newFileSize = $uploadData->getSize();
        }

        if ($isInputStream) {
            try {
                $this->logDebug("Begining reading INPUT stream");
                if(is_array($uploadData)){
                    $input = fopen("php://input", "r");
                }else{
                    $input = $uploadData->getStream()->detach();
                }
                $output = fopen($destination, "w");
                $sizeRead = 0;
                while ($sizeRead < intval($newFileSize)) {
                    $chunk = fread($input, 4096);
                    $sizeRead += strlen($chunk);
                    fwrite($output, $chunk, strlen($chunk));
                }
                fclose($input);
                fclose($output);
                $this->logDebug("End reading INPUT stream");
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage(), 411);
            }
        } else {
            if(is_array($uploadData)){
                $result = @move_uploaded_file($uploadData["tmp_name"], $destination);
                if (!$result) {
                    $realPath = AJXP_MetaStreamWrapper::getRealFSReference($destination);
                    $result = move_uploaded_file($uploadData["tmp_name"], $realPath);
                }
            }else{
                $clone = clone $uploadData;
                try{
                    $uploadData->moveTo($destination);
                    $result = true;
                }catch(\Exception $e){
                    // Can be blocked by open_basedir, try to perform the move again, with the
                    // real FS reference.
                    $realPath = AJXP_MetaStreamWrapper::getRealFSReference($destination);
                    try{
                        $clone->moveTo($realPath);
                        $result = true;
                    }catch(\Exception $e){
                        $result = false;
                    }
                }
            }
            if (!$result) {
                $errorMessage="$messages[33] ".Utils::safeBasename($destination);
                throw new \Exception($errorMessage, 411);
            }
        }
        return true;
    }

    /**
     * @param String $folder Folder destination
     * @param String $source Maybe updated by the function
     * @param String $target Existing part to append data
     * @return bool If the target file already existed or not.
     * @throws \Exception
     */
    protected function appendUploadedData($folder, $source, $target){

        $already_existed = false;
        if($source == $target){
            throw new \Exception("Something nasty happened: trying to copy $source into itself, it will create a loop!");
        }
        if (file_exists($folder ."/" . $target)) {
            $already_existed = true;
            $this->logDebug("Should copy stream from $source to $target");
            $partO = fopen($folder."/".$source, "r");
            $appendF = fopen($folder ."/". $target, "a+");
            while (!feof($partO)) {
                $buf = fread($partO, 1024);
                fwrite($appendF, $buf, strlen($buf));
            }
            fclose($partO);
            fclose($appendF);
            $this->logDebug("Done, closing streams!");
        }
        @unlink($folder."/".$source);
        return $already_existed;

    }
    
    public function countChildren(AJXP_Node $dirNode, $foldersOnly = false, $nonEmptyCheckOnly = false, $dirHANDLE = null)
    {
        $dirName = $dirNode->getUrl();
        if(is_resource($dirHANDLE)){
            $handle = $dirHANDLE;
        }else{
            $handle=@opendir($dirName);
        }
        if ($handle === false) {
            throw new \Exception("Error while trying to open directory ".$dirName);
        }
        if ($foldersOnly && !$dirNode->wrapperIsRemote()) {
            if($dirHANDLE == null || !is_resource($dirHANDLE)){
                closedir($handle);
            }
            $path = $dirNode->getRealFile();
            $dirs = glob($path."/*", GLOB_ONLYDIR|GLOB_NOSORT);
            if($dirs === false) return 0;
            return count($dirs);
        }
        $count = 0;
        $showHiddenFiles = $this->getContextualOption($dirNode->getContext(), "SHOW_HIDDEN_FILES");
        while (false !== ($file = readdir($handle))) {
            if($file != "." && $file !=".."
                && !(Utils::isHidden($file) && !$showHiddenFiles)){
                if($foldersOnly && is_file($dirName."/".$file)) continue;
                $count++;
                if($nonEmptyCheckOnly) break;
            }
        }
        if($dirHANDLE == null || !is_resource($dirHANDLE)){
            closedir($handle);
        }
        return $count;
    }

    public function date_modif($file)
    {
        $tmp = @filemtime($file) or 0;
        return $tmp;// date("d,m L Y H:i:s",$tmp);
    }

    public function extractArchiveItemPreCallback($crtUrlBase, $status, $data, $taskId = null){
        $fullname = $data['filename'];
        $size = $data['size'];
        $realBase = AJXP_MetaStreamWrapper::getRealFSReference($crtUrlBase);
        $realBase = str_replace("\\", "/", $realBase);
        $repoName = $crtUrlBase.str_replace($realBase, "", $fullname);

        $toNode = new AJXP_Node($repoName);
        $toNode->setLeaf($data['folder'] ? false:true);
        if(file_exists($toNode->getUrl())){
            Controller::applyHook("node.before_change", array($toNode, $size));
        }else{
            Controller::applyHook("node.before_create", array($toNode, $size));
        }
        return 1;
    }

    public function extractArchiveItemPostCallback($crtUrlBase, $status, $data, $taskId = null){
        $fullname = $data['filename'];
        $realBase = AJXP_MetaStreamWrapper::getRealFSReference($crtUrlBase);
        $repoName = str_replace($realBase, "", $fullname);
        if($taskId !== null){
            TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_RUNNING, "Extracted file ".$repoName);
        }
        $toNode = new AJXP_Node($crtUrlBase.$repoName);
        $toNode->setLeaf($data['folder'] ? false:true);
        Controller::applyHook("node.change", array(null, $toNode, false));
        return 1;
    }

    /**
     * Extract an archive directly inside the dest directory.
     *
     * @param string $destDir
     * @param UserSelection $selection
     * @param array $error
     * @param array $success
     * @param string $taskId
     */
    public function extractArchive($destDir, $selection, &$error, &$success, $taskId = null)
    {
        require_once(AJXP_BIN_FOLDER."/lib/pclzip.lib.php");
        $zipPath = $selection->getZipPath(true);
        $zipLocalPath = $selection->getZipLocalPath(true);
        if(strlen($zipLocalPath)>1 && $zipLocalPath[0] == "/") $zipLocalPath = substr($zipLocalPath, 1)."/";
        $files = $selection->getFiles();
        $currentUrlBase = $selection->currentBaseUrl();

        $realZipFile = AJXP_MetaStreamWrapper::getRealFSReference($currentUrlBase.$zipPath);
        $archive = new PclZip($realZipFile);
        $content = $archive->listContent();
        foreach ($files as $key => $item) {// Remove path
            $item = substr($item, strlen($zipPath));
            if($item[0] == "/") $item = substr($item, 1);
            foreach ($content as $zipItem) {
                if ($zipItem["stored_filename"] == $item || $zipItem["stored_filename"] == $item."/") {
                    $files[$key] = $zipItem["stored_filename"];
                    break;
                } else {
                    unset($files[$key]);
                }
            }
        }
        $this->logDebug("Archive", $this->addSlugToPath($files));
        $realDestination = AJXP_MetaStreamWrapper::getRealFSReference($currentUrlBase.$destDir);
        $this->logDebug("Extract", array($realDestination, $realZipFile, $this->addSlugToPath($files), $zipLocalPath));

        $result = $archive->extract(PCLZIP_OPT_BY_NAME,     $files,
                                    PCLZIP_OPT_PATH,        $realDestination,
                                    PCLZIP_OPT_REMOVE_PATH, $zipLocalPath,
                                    PCLZIP_CB_PRE_EXTRACT,  function($status, $data) use ($currentUrlBase, $taskId) { return $this->extractArchiveItemPreCallback($currentUrlBase, $status, $data, $taskId); },
                                    PCLZIP_CB_POST_EXTRACT, function($status, $data) use ($currentUrlBase, $taskId) { return $this->extractArchiveItemPostCallback($currentUrlBase, $status, $data, $taskId); },
                                    PCLZIP_OPT_STOP_ON_ERROR
        );

        if ($result <= 0) {
            $error[] = $archive->errorInfo(true);
        } else {
            $mess = ConfService::getMessages();
            $success[] = sprintf($mess[368], basename($zipPath), $destDir);
        }
    }

    /**
     * @param string $destDir
     * @param UserSelection $selection
     * @param array $error
     * @param array $success
     * @param bool $move
     * @param string|null $targetBaseName
     * @param string|null $taskId
     * @throws \Exception
     */
    public function copyOrMove($destDir, $selection, &$error, &$success, $move = false, $targetBaseName = null, $taskId = null)
    {
        $selectedFiles = $selection->getFiles();
        $this->logDebug("CopyMove", array("dest"=>$this->addSlugToPath($destDir), "selection" => $this->addSlugToPath($selectedFiles)));
        $mess = ConfService::getMessages();
        if (!$this->isWriteable($selection->nodeForPath($destDir))) {
            $error[] = $mess[38]." ".$destDir." ".$mess[99];
            return ;
        }
        $repoData = array(
            'base_url'      => $selection->currentBaseUrl(),
            'chmod'         => $selection->getContext()->getRepository()->getContextOption($selection->getContext(), 'CHMOD_VALUE'),
            'recycle'       => $selection->getContext()->getRepository()->getContextOption($selection->getContext(), 'RECYCLE_BIN')
        );
        foreach ($selectedFiles as $selectedFile) {
            if ($move && !$this->isWriteable($selection->nodeForPath(dirname($selectedFile)))) {
                $error[] = "\n".$mess[38]." ".dirname($selectedFile)." ".$mess[99];
                continue;
            }
            if( !empty ($targetBaseName)){
                $destFile = $destDir ."/" . $targetBaseName;
            }else{
                $bName = basename($selectedFile);
                $localName = '';
                Controller::applyHook("dl.localname", array($selectedFile, &$localName));
                if(!empty($localName)) $bName = $localName;
                $destFile = $destDir ."/". $bName;
            }
            $this->copyOrMoveFile($destFile, $selectedFile, $error, $success, $move, $repoData, $repoData, $taskId);
        }
    }

    /**
     * @param AJXP_Node $originalNode
     * @param AJXP_Node $dest
     * @param string $filename_new
     * @return AJXP_Node
     * @throws PydioException
     * @throws \Exception
     */
    public function rename($originalNode, $dest = null, $filename_new = null)
    {
        $mess = ConfService::getMessages();

        if(!empty($filename_new)){
            $filename_new=  Utils::sanitize(TextEncoder::magicDequote($filename_new), AJXP_SANITIZE_FILENAME);
            $filename_new = substr($filename_new, 0, ConfService::getCoreConf("NODENAME_MAX_LENGTH"));
        }

        if (empty($filename_new) && empty($dest)) {
            throw new PydioException("$mess[37]");
        }

        if (!$this->isWriteable($originalNode)) {
            throw new PydioException($mess[34]." ".$originalNode->getLabel()." ".$mess[99]);
        }

        if($dest == null) {
            $newNode = $originalNode->getParent()->createChildNode($filename_new);
        } else {
            $newNode = $dest;
        }
        if (file_exists($newNode->getUrl())) {
            throw new PydioException($newNode->getPath()." $mess[43]");
        }
        if (!file_exists($originalNode->getUrl())) {
            throw new PydioException($mess[100]." ".$originalNode->getPath());
        }
        Controller::applyHook("node.before_path_change", array(&$originalNode));
        $test = @rename($originalNode->getUrl(),$newNode->getUrl());
        if($test === false){
            throw new \Exception("Error while renaming ".$originalNode->getPath()." to ".$newNode->getPath());
        }
        Controller::applyHook("node.change", array($originalNode, $newNode, false));
        return $newNode;

    }

    public static function autoRenameForDest($destination, $fileName)
    {
        if(!is_file($destination."/".$fileName)) return $fileName;
        $i = 1;
        $ext = "";
        $split = explode(".", $fileName);
        if (count($split) > 1) {
            $ext = ".".$split[count($split)-1];
            array_pop($split);
            $name = join(".", $split);
        } else {
            $name = $fileName;
        }
        while (is_file($destination."/".$name."-$i".$ext)) {
            $i++; // increment i until finding a non existing file.
        }
        return $name."-$i".$ext;
    }

    /**
     * @param AJXP_Node $parentNode
     * @param String $newDirName
     * @param bool $ignoreExists
     * @return AJXP_Node
     * @throws \Exception
     */
    public function mkDir($parentNode, $newDirName, $ignoreExists = false)
    {
        Controller::applyHook("node.before_change", array(&$parentNode));

        $mess = ConfService::getMessages();
        if ($newDirName=="") {
            throw new PydioException($mess[37]);
        }
        if (file_exists($parentNode->getUrl()."/".$newDirName)) {
            if($ignoreExists) {
                return $parentNode->createChildNode($newDirName);
            }
            throw new PydioException($mess[40]);
        }
        if (!$this->isWriteable($parentNode)) {
            throw new PydioException($mess[38]." $parentNode->getPath() ".$mess[99]);
        }

        $dirMode = 0775;
        $ctx = $parentNode->getContext();
        $chmodValue = $ctx->getRepository()->getContextOption($ctx, "CHMOD_VALUE");
        if (isSet($chmodValue) && $chmodValue != "") {
            $dirMode = octdec(ltrim($chmodValue, "0"));
            if ($dirMode & 0400) $dirMode |= 0100; // User is allowed to read, allow to list the directory
            if ($dirMode & 0040) $dirMode |= 0010; // Group is allowed to read, allow to list the directory
            if ($dirMode & 0004) $dirMode |= 0001; // Other are allowed to read, allow to list the directory
        }
        $old = umask(0);
        mkdir($parentNode->getUrl()."/".$newDirName, $dirMode);
        umask($old);
        $newNode =  $parentNode->createChildNode($newDirName);
        $newNode->setLeaf(false);
        Controller::applyHook("node.change", array(null, $newNode, false));
        return $newNode;

    }

    /**
     * @param AJXP_Node $node
     * @param string $content
     * @param bool $force
     * @throws \Exception
     */
    public function createEmptyFile(AJXP_Node $node, $content = "", $force = false)
    {
        Controller::applyHook("node.before_change", array($node->getParent()));
        $mess = ConfService::getMessages();

        if (!$force && file_exists($node->getUrl())) {
            throw new PydioException($mess[71], 71);
        }
        if (!$this->isWriteable($node->getParent())) {
            throw new PydioException("$mess[38] ".$node->getParent()->getPath()." $mess[99]", 71);
        }
        $ctx = $node->getContext();
        $repoData = array(
            'chmod'     => $ctx->getRepository()->getContextOption($ctx, 'CHMOD_VALUE'),
            'recycle'   => $ctx->getRepository()->getContextOption($ctx, 'RECYCLE_BIN')
        );
        $fp=fopen($node->getUrl(),"w");
        if ($fp) {
            if ($content != "") {
                fputs($fp, $content);
            }
            $this->changeMode($node->getUrl(), $repoData);
            fclose($fp);
            $node->loadNodeInfo();
            Controller::applyHook("node.change", array(null, $node, false));
        } else {
            throw new PydioException("$mess[102] ".$node->getPath()." (".$fp.")");
        }
    }


    /**
     * @param UserSelection $selection
     * @param $logMessages
     * @param null $taskId
     * @return null
     * @throws PydioException
     * @throws \Exception
     */
    public function delete(UserSelection $selection, &$logMessages, $taskId = null)
    {
        $ctx = $selection->getContext();
        $repoData = array(
            'chmod'         => $ctx->getRepository()->getContextOption($ctx, 'CHMOD_VALUE'),
            'recycle'       => $ctx->getRepository()->getContextOption($ctx, 'RECYCLE_BIN')
        );
        $mess = ConfService::getMessages();
        $selectedNodes = $selection->buildNodes();
        foreach ($selectedNodes as $selectedNode) {

            $fileUrl = $selectedNode->getUrl();
            $filePath = $selectedNode->getPath();

            if (!file_exists($fileUrl)) {
                $logMessages[]=$mess[100]." ".TextEncoder::toUTF8($filePath);
                continue;
            }
            $this->deldir($fileUrl, $repoData, $taskId);
            if (is_dir($fileUrl)) {
                $logMessages[]="$mess[38] ".TextEncoder::toUTF8($filePath)." $mess[44].";
            } else {
                $logMessages[]="$mess[34] ".TextEncoder::toUTF8($filePath)." $mess[44].";
            }
            Controller::applyHook("node.change", array($selectedNode));
        }
        if($taskId != null){
            TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_COMPLETE, "Done");
            $nodesDiff = new NodesDiff();
            $nodesDiff->remove($selection->getFiles());
            $t = TaskService::getInstance()->getTaskById($taskId);
            Controller::applyHook("msg.instant", array($t->getContext(), $nodesDiff->toXML()));
        }
        return null;
    }

    public function simpleCopy($origFile, $destFile)
    {
        return copy($origFile, $destFile);
    }

    /**
     * @param AJXP_Node $node
     * @return bool
     */
    public function isWriteable(AJXP_Node $node)
    {
        if ( $this->getContextualOption($node->getContext(), "USE_POSIX") == true && extension_loaded('posix')) {
            $real = $node->getRealFile();
            return posix_access($real, POSIX_W_OK);
        }
        //clearstatcache();
        return is_writable($node->getUrl());
    }

    /**
     * Change file permissions
     *
     * @param AJXP_Node $node
     * @param String $chmodValue
     * @param Boolean $recursive
     * @param String $nodeType "both", "file", "dir"
     * @param $changedFiles
     * @return void
     */
    public function chmod(AJXP_Node $node, $chmodValue, $recursive, $nodeType, &$changedFiles)
    {
        $realValue = octdec(ltrim($chmodValue, "0"));
        $nodeUrl = $node->getUrl();
        if (is_file($nodeUrl)) {
            if ($nodeType=="both" || $nodeType=="file") {
                AJXP_MetaStreamWrapper::changeMode($nodeUrl, $realValue);
                $changedFiles[] = $node->getPath();
            }
        } else {
            if ($nodeType=="both" || $nodeType=="dir") {
                AJXP_MetaStreamWrapper::changeMode($nodeUrl, $realValue);
                $changedFiles[] = $node->getPath();
            }
            if ($recursive) {
                $handler = opendir($nodeUrl);
                while ($child=readdir($handler)) {
                    if($child == "." || $child == "..") continue;
                    // do not pass realValue or it will be re-decoded.
                    $this->chmod($node->createChildNode($child), $chmodValue, $recursive, $nodeType, $changedFiles);
                }
                closedir($handler);
            }
        }
    }

    /**
     * @param AJXP_Node $fromNode
     * @param AJXP_Node $toNode
     * @param Boolean $copy
     */
    public function nodeChanged(&$fromNode = null, &$toNode = null, $copy = false)
    {
        Controller::applyHook("node.change", array($fromNode, $toNode, $copy));
    }

    /**
     * @param AJXP_Node $node
     * @param null $newSize
     */
    public function nodeWillChange($node, $newSize = null)
    {
        if ($newSize != null) {
            Controller::applyHook("node.before_change", array($node, $newSize));
        } else {
            Controller::applyHook("node.before_path_change", array($node));
        }
    }


    /**
     * @param UserSelection $selection
     * @param string $dest
     * @param string $basedir
     * @param string $taskId
     * @throws \Exception
     * @return PclZip
     */
    public function makeZip (UserSelection $selection, $dest, $basedir, $taskId = null)
    {
        $zipEncoding = ConfService::getCoreConf("ZIP_ENCODING");

        @set_time_limit(0);
        require_once(AJXP_BIN_FOLDER."/lib/pclzip.lib.php");
        $filePaths = array();
        $selectedNodes = $selection->buildNodes();
        foreach ($selectedNodes as $node) {
            $realFile = $node->getRealFile();
            if (basename($node->getPath()) == "") {
                $filePaths[] = array(PCLZIP_ATT_FILE_NAME => $realFile);
            } else {
                $shortName = $node->getLabel();
                if(!empty($zipEncoding)){
                    $test = iconv(TextEncoder::getEncoding(), $zipEncoding, $shortName);
                    if($test !== false) $shortName = $test;
                }
                $filePaths[] = array(PCLZIP_ATT_FILE_NAME => $realFile,
                                    PCLZIP_ATT_FILE_NEW_SHORT_NAME => $shortName);
            }
        }
        $this->logDebug("Pathes", $filePaths);
        $archive = new PclZip($dest);
        $zipEncoding = ConfService::getCoreConf("ZIP_ENCODING");
        $fsEncoding = TextEncoder::getEncoding();
        $ctx = $selection->getContext();

        $preAddCallback = function($value, &$header) use ($ctx, $taskId, $zipEncoding, $fsEncoding){
            if($taskId !== null){
                TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_RUNNING, "Adding ".$header["stored_filename"]." to archive");
            }
            $search = $header["filename"];
            if(!empty($zipEncoding)){
                $test = iconv($fsEncoding, $zipEncoding, $header["stored_filename"]);
                if($test !== false){
                    $header["stored_filename"] = $test;
                }
            }
            return !($this->filterFile($ctx, $search, true) || $this->filterFolder($ctx, $search, "contains"));
        };

        if($basedir == "__AJXP_ZIP_FLAT__/"){
            $vList = $archive->create($filePaths, PCLZIP_OPT_REMOVE_ALL_PATH, PCLZIP_OPT_NO_COMPRESSION, PCLZIP_OPT_ADD_TEMP_FILE_ON, PCLZIP_CB_PRE_ADD, $preAddCallback);
        }else{
            $basedir = AJXP_MetaStreamWrapper::getRealFSReference($selection->currentBaseUrl()).trim($basedir);
            $this->logDebug("Basedir", array($basedir));
            $vList = $archive->create($filePaths, PCLZIP_OPT_REMOVE_PATH, $basedir, PCLZIP_OPT_NO_COMPRESSION, PCLZIP_OPT_ADD_TEMP_FILE_ON, PCLZIP_CB_PRE_ADD, $preAddCallback);
        }

        if (!$vList) {
            if($taskId !== null){
                TaskService::getInstance()->updateTaskStatus($taskId, Task::STATUS_FAILED, "Zip creation error : ($dest) ".$archive->errorInfo(true));
            }
            throw new \Exception("Zip creation error : ($dest) ".$archive->errorInfo(true));
        }
        return $vList;
    }


    public function recursivePurge($dirName, $hardPurgeTime, $softPurgeTime = 0)
    {
        $handle=opendir($dirName);
        $shareCenter = false;
        if(class_exists("\\Pydio\\Share\\ShareCenter")){
            $shareCenter = \Pydio\Share\ShareCenter::getShareCenter();
        }
        if($handle === false){
            $this->logError(__FUNCTION__, "Cannot open folder ".$dirName);
            return;
        }
        while (false !== ($entry = readdir($handle))) {
            if ($entry == "" || $entry == ".."  || Utils::isHidden($entry) ) {
                continue;
            }
            $fileName = $dirName."/".$entry;
            if (is_file($fileName)) {
                $docAge = time() - filemtime($fileName);
                if ($hardPurgeTime > 0 && $docAge > $hardPurgeTime) {
                    $this->purge($fileName);
                } elseif ($softPurgeTime > 0 && $docAge > $softPurgeTime) {
                    if($shareCenter !== false && $shareCenter->isShared(new AJXP_Node($fileName))) {
                        $this->purge($fileName);
                    }
                }
            } else {
                $this->recursivePurge($fileName, $hardPurgeTime, $softPurgeTime);
            }
        }
        closedir($handle);
    }

    /**
     * Apply specific operation to set a node as hidden.
     * Can be overwritten, or will probably do nothing.
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     */
    public function setHiddenAttribute($node){
        if(AJXP_MetaStreamWrapper::actualRepositoryWrapperClass($node->getRepositoryId()) == "fsAccessWrapper" && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'){
            $realPath =  AJXP_MetaStreamWrapper::getRealFSReference($node->getUrl());
            @shell_exec("attrib +H " . escapeshellarg($realPath));
        }
    }

    private function purge($fileName)
    {
        $node = new AJXP_Node($fileName);
        Controller::applyHook("node.before_path_change", array($node));
        unlink($fileName);
        Controller::applyHook("node.change", array($node));
        $this->logInfo("Purge", array("file" => $fileName, "files" => $fileName));
        print(" - Purging document : ".$fileName."\n");
    }

    /** The publiclet URL making */
    public function makePublicletOptions($filePath, $password, $expire, $downloadlimit, $repository)
    {
        $data = array(
            "DRIVER"=>$repository->getAccessType(),
            "OPTIONS"=>NULL,
            "FILE_PATH"=>$filePath,
            "ACTION"=>"download",
            "EXPIRE_TIME"=>$expire ? (time() + $expire * 86400) : 0,
            "DOWNLOAD_LIMIT"=>$downloadlimit ? $downloadlimit : 0,
            "PASSWORD"=>$password
        );
        return $data;
    }

    public function makeSharedRepositoryOptions(ContextInterface $ctx, $httpVars)
    {
        $repository = $ctx->getRepository();
        $newOptions = array(
            "PATH"           => TextEncoder::toStorageEncoding($repository->getContextOption($ctx, "PATH")).Utils::decodeSecureMagic($httpVars["file"]),
            "CREATE"         => $repository->getContextOption($ctx, "CREATE"),
            "RECYCLE_BIN"    => isSet($httpVars["inherit_recycle"])? $repository->getContextOption($ctx, "RECYCLE_BIN") : "",
            "DEFAULT_RIGHTS" => "",
            "DATA_TEMPLATE"  => ""
        );
        if ($repository->getContextOption($ctx, "USE_SESSION_CREDENTIALS") === true) {
            $newOptions["ENCODED_CREDENTIALS"] = AJXP_Safe::getEncodedCredentialString();
        }
        $customData = array();
        foreach ($httpVars as $key => $value) {
            if (substr($key, 0, strlen("PLUGINS_DATA_")) == "PLUGINS_DATA_") {
                $customData[substr($key, strlen("PLUGINS_DATA_"))] = $value;
            }
        }
        if (count($customData)) {
            $newOptions["PLUGINS_DATA"] = $customData;
        }
        if ($repository->getContextOption($ctx, "META_SOURCES")) {
            $newOptions["META_SOURCES"] = $repository->getContextOption($ctx, "META_SOURCES");
            foreach ($newOptions["META_SOURCES"] as $index => &$data) {
                if (isSet($data["USE_SESSION_CREDENTIALS"]) && $data["USE_SESSION_CREDENTIALS"] === true) {
                    $newOptions["META_SOURCES"][$index]["ENCODED_CREDENTIALS"] = AJXP_Safe::getEncodedCredentialString();
                }
            }
            Controller::applyHook("workspace.share_metasources", array(&$newOptions["META_SOURCES"]));
        }
        return $newOptions;
    }


}