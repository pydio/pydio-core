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
namespace Pydio\Access\Meta\Version;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\MetaStreamWrapper;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\RecycleBinManager;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Http\Message\ReloadMessage;
use Pydio\Core\Http\Message\UserMessage;
use Pydio\Core\Http\Message\XMLMessage;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\AuthService;

use Pydio\Core\Services\LocaleService;
use Pydio\Core\Utils\Vars\InputFilter;

use Pydio\Core\Utils\Vars\XMLFilter;
use Pydio\Core\Utils\TextEncoder;
use Pydio\Access\Meta\Core\AbstractMetaSource;

defined('AJXP_EXEC') or die( 'Access not allowed');
define('SVNLIB_PATH', '');
if (SVNLIB_PATH != "") {
    putenv("LD_LIBRARY_PATH=".SVNLIB_PATH);
}
/**
 * Uses svn command lines to extract version infos. Autocommit on change.
 * @package AjaXplorer_Plugins
 * @subpackage Meta
 */
class SvnManager extends AbstractMetaSource
{
    private static $svnListDir;
    private static $svnListCache;
    private $commitMessageParams;

    /**
     * @param \Pydio\Core\Model\ContextInterface $ctx
     * @param array $options
     */
    public function init(\Pydio\Core\Model\ContextInterface $ctx, $options = [])
    {
        $this->options = $options;
        // Do nothing
    }

    /**
     * @param ContextInterface $ctx
     * @param AbstractAccessDriver $accessDriver
     */
    public function initMeta(ContextInterface $ctx, AbstractAccessDriver $accessDriver)
    {
        require_once("svn_lib.inc.php");
        parent::initMeta($ctx, $accessDriver);
        parent::init($ctx, $this->options);
    }

    /**
     * @param ContextInterface $ctx
     * @param $httpVars
     * @param array $additionnalPathes
     * @param bool $testRecycle
     * @return array
     * @throws \Exception
     */
    protected function initDirAndSelection(ContextInterface $ctx, $httpVars, $additionnalPathes = array(), $testRecycle = false)
    {
        $userSelection = UserSelection::fromContext($ctx, $httpVars);
        $repo = $userSelection->getContext()->getRepository();
        $urlBase = $userSelection->currentBaseUrl();
        $result = array();

        if ($testRecycle) {
            $recycle = $repo->getContextOption($ctx, "RECYCLE_BIN");
            if ($recycle != "") {
                RecycleBinManager::init($urlBase, "/".$recycle);
                RecycleBinManager::filterActions($httpVars["get_action"], $userSelection, $httpVars);
                $result["RECYCLE"] = $recycle;
                    // if necessary, check recycle was checked.
                // We could use a hook instead here? Maybe the full recycle system
                // could be turned into a plugin
                $sessionKey = "AJXP_SVN_".$repo->getId()."_RECYCLE_CHECKED";
                if (isSet($_SESSION[$sessionKey])) {
                    $file = RecycleBinManager::getRelativeRecycle()."/".RecycleBinManager::getCacheFileName();
                    $realFile = MetaStreamWrapper::getRealFSReference($urlBase.$file);
                    $this->addIfNotVersionned($ctx, $file, $realFile);
                    $_SESSION[$sessionKey] = true;
                }
            }
        }

        $result["DIR"] = MetaStreamWrapper::getRealFSReference($urlBase. InputFilter::decodeSecureMagic($httpVars["dir"]));
        $result["ORIGINAL_SELECTION"] = $userSelection;
        $result["SELECTION"] = array();
        if (!$userSelection->isEmpty()) {
            $files = $userSelection->getFiles();
            foreach ($files as $selected) {
                $result["SELECTION"][] = MetaStreamWrapper::getRealFSReference($urlBase.$selected);
            }
        }
        foreach ($additionnalPathes as $parameter => $path) {
            $result[$parameter] = MetaStreamWrapper::getRealFSReference($urlBase.$path);
        }
        return $result;
    }

    /**
     * @param $ctx ContextInterface
     * @param $repoFile
     * @param $realFile
     * @throws \Exception
     */
    protected function addIfNotVersionned(ContextInterface $ctx, $repoFile, $realFile)
    {
        $error = false;
        $res = [];
        try {
            $res = ExecSvnCmd("svn status ", $realFile);
        } catch (\Exception $e) {
            $error = true;
        }
        if ($error || (count($res[IDX_STDOUT]) && substr($res[IDX_STDOUT][0],0,1) == "?")) {
            ExecSvnCmd("svn add", "$realFile");
            $this->commitMessageParams = "Recycle cache file";
            $this->commitChanges("ADD", array("dir" => dirname($repoFile)), array(), $ctx);
        }
    }

    /**
     * @param String $file URL of the file to commit (probably a metadata)
     * @param \Pydio\Access\Core\Model\AJXP_Node $ajxpNode Optionnal node to commit along.
     */
    public function commitFile($file, $ajxpNode)
    {
        $realFile = MetaStreamWrapper::getRealFSReference($file);

        $res = ExecSvnCmd("svn status ", $realFile);
        if (count($res[IDX_STDOUT]) && substr($res[IDX_STDOUT][0],0,1) == "?") {
            $res2 = ExecSvnCmd("svn add", "$realFile");
        }
        $ctx = $ajxpNode->getContext();
        if ($ajxpNode != null) {
            $nodeRealFile = MetaStreamWrapper::getRealFSReference($ajxpNode->getUrl());
            try {
                ExecSvnCmd("svn propset metachange ".time(), $nodeRealFile);
            } catch (\Exception $e) {
                $this->commitChanges("COMMIT_META", $realFile, array(), $ctx);
                return;
            }
            // WILL COMMIT BOTH AT ONCE
            $command = "svn commit";
            $user = $ctx->getUser()->getId();
            $switches = "-m \"Pydio||$user||COMMIT_META||file:".escapeshellarg($file)."\"";
            ExecSvnCmd($command, array($realFile, $nodeRealFile), $switches);
            ExecSvnCmd('svn update', dirname($nodeRealFile), '');
        } else {
            $this->commitChanges("COMMIT_META", $realFile, array(), $ctx);
        }
    }

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @throws \Exception
     */
    public function switchAction(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface)
    {
        $actionName     = $requestInterface->getAttribute("action");
        $httpVars       = $requestInterface->getParsedBody();
        $ctx            = $requestInterface->getAttribute("ctx");

        $init = $this->initDirAndSelection($ctx, $httpVars);
        if ($actionName == "svnlog") {
            $res1 = ExecSvnCmd("svnversion", $init["DIR"]);
            $test = trim(implode("", $res1[IDX_STDOUT]));
            if (is_numeric($test)) {
                $currentRev = $test;
            } else if (strstr($test, ":")!==false && count(explode(":", $test))) {
                $revRange = explode(":", $test);
            }
            $command = 'svn log';
            $switches = '--xml -rHEAD:0';
            $arg = $init["SELECTION"][0];
            $res = ExecSvnCmd($command, $arg, $switches);
            $serialStream = new SerializableResponseStream();
            $lines = explode(PHP_EOL, $res[IDX_STDOUT]);
            array_shift($lines);
            if (isSet($currentRev)) {
                $serialStream->addChunk(new XMLMessage("<current_revision>$currentRev</current_revision>"));
            } else if (isSet($revRange)) {
                $serialStream->addChunk(new XMLMessage("<revision_range start='$revRange[0]' end='$revRange[1]'/>"));
            }
            $serialStream->addChunk(new XMLMessage(implode("", $lines)));
            $responseInterface = $responseInterface->withBody($serialStream);

        } else if ($actionName == "svndownload") {
            $revision = $httpVars["revision"];
            $realFile = $init["SELECTION"][0];
            $entries = $this->svnListNode($realFile, $revision);
            $keys = array_keys($entries);
            $localName = $keys[0];
            $contentSize = 0;
            if (isSet($entries[$localName]["last_revision_size"])) {
                $contentSize = intval($entries[$localName]["last_revision_size"]);
            }
            // output directly the file!
            header("Content-Type: application/force-download; name=\"".$localName."\"");
            header("Content-Transfer-Encoding: binary");
            if($contentSize > 0) header("Content-Length: ".$contentSize);
            header("Content-Disposition: attachment; filename=\"".$localName."\"");
            header("Expires: 0");
            header("Cache-Control: no-cache, must-revalidate");
            header("Pragma: no-cache");
            if (preg_match('/ MSIE /',$_SERVER['HTTP_USER_AGENT'])) {
                header("Pragma: public");
                header("Expires: 0");
                header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
                header("Cache-Control: private",false);
            }
            $realFile = escapeshellarg($realFile);
            $revision = escapeshellarg($revision);
            system( (SVNLIB_PATH!=""?SVNLIB_PATH."/":"") ."svn cat -r$revision $realFile");
            return;
        } else if ($actionName == "revert_file") {

            $revision = escapeshellarg($httpVars["revision"]);
            $realFile = $init["SELECTION"][0];
            $compare = (isSet($httpVars["compare"]) && $httpVars["compare"] == "true");
            $escapedFile = escapeshellarg($realFile);
            if ($compare) {
                $ext = pathinfo($realFile, PATHINFO_EXTENSION);
                $targetFile = preg_replace("/\.$ext$/", "-r$revision.$ext", $realFile);
                system( (SVNLIB_PATH!=""?SVNLIB_PATH."/":"") ."svn cat -r$revision $escapedFile > ".escapeshellarg($targetFile));
            } else {
                system( (SVNLIB_PATH!=""?SVNLIB_PATH."/":"") ."svn cat -r$revision $escapedFile > $escapedFile");
                $this->commitChanges($actionName, $realFile, array(), $ctx);
            }

        } else if ($actionName == "svnswitch") {
            $revision = escapeshellarg($httpVars["revision"]);
            ExecSvnCmd("svn update -r$revision ".$init["DIR"]);
        }
    }

    /**
     * @param $actionName
     * @param $httpVars
     * @param $filesVars
     * @param ContextInterface $ctx
     * @throws \Exception
     */
    public function addSelection($actionName, $httpVars, $filesVars, ContextInterface $ctx)
    {
        switch ($actionName) {
            case "mkdir":
                $init = $this->initDirAndSelection($ctx, $httpVars, array("NEW_DIR" => InputFilter::decodeSecureMagic($httpVars["dir"] . "/" . $httpVars["dirname"])));
                $res = ExecSvnCmd("svn add", $init["NEW_DIR"]);
                $this->commitMessageParams = $httpVars["dirname"];
            break;
            case "mkfile":
                $init = $this->initDirAndSelection($ctx, $httpVars, array("NEW_FILE" => InputFilter::decodeSecureMagic($httpVars["dir"] . "/" . $httpVars["filename"])));
                $res = ExecSvnCmd("svn add", $init["NEW_FILE"]);
                $this->commitMessageParams = $httpVars["filename"];
            break;
            case "upload":
                if (isSet($filesVars) && isSet($filesVars["userfile_0"]) && isSet($filesVars["userfile_0"]["name"])) {
                    $init = $this->initDirAndSelection($ctx, $httpVars, array("NEW_FILE" => TextEncoder::fromUTF8($httpVars["dir"])."/".$filesVars["userfile_0"]["name"]));
                    $res = ExecSvnCmd("svn status ", $init["NEW_FILE"]);
                    if (count($res[IDX_STDOUT]) && substr($res[IDX_STDOUT][0],0,1) == "?") {
                        $res = ExecSvnCmd("svn add", $init["NEW_FILE"]);
                    } else {
                        $res = true;
                    }
                    //$res = ExecSvnCmd("svn add", $init["NEW_FILE"]);
                    $this->commitMessageParams = $filesVars["userfile_0"]["name"];
                }
            break;
        }
        if (isSet($res)) {
            $this->commitChanges($actionName, $httpVars, $filesVars, $ctx);
        }
    }

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @throws \Exception
     */
    public function copyOrMoveSelection(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface)
    {
        $actionName     = $requestInterface->getAttribute("action");
        $httpVars       = $requestInterface->getParsedBody();
        $ctx            = $requestInterface->getAttribute("ctx");

        if ($actionName != "rename") {
            $init = $this->initDirAndSelection($ctx, $httpVars, array("DEST_DIR" => InputFilter::decodeSecureMagic($httpVars["dest"])));
            $this->commitMessageParams = "To:".$httpVars["dest"].";items:";
        } else {
            $init = $this->initDirAndSelection($ctx, $httpVars, array(), true);
        }
        $this->logDebug("Entering SVN MAnager for action $actionName", $init);
        $action = 'copy';
        if ($actionName == "move" || $actionName == "rename") {
            $action = 'move';
        }
        foreach ($init["SELECTION"] as $selectedFile) {
            if ($actionName == "rename") {
                $destFile = dirname($selectedFile)."/". InputFilter::decodeSecureMagic($httpVars["filename_new"]);
                $this->commitMessageParams = "To:".$httpVars["filename_new"].";item:".$httpVars["file"];
            } else {
                $destFile = $init["DEST_DIR"]."/".basename($selectedFile);
            }
            $this->addIfNotVersionned($ctx, str_replace($init["DIR"], "", $selectedFile), $selectedFile);
            ExecSvnCmd("svn $action", array($selectedFile,$destFile), '');
        }
        if ($actionName != "rename") {
            $this->commitMessageParams .= "[".implode(",",$init["SELECTION"])."]";
        }
        $this->commitChanges($actionName, $httpVars, [], $ctx);
        if ($actionName != "rename") {
            $this->commitChanges($actionName, array("dir" => $httpVars["dest"]), [], $ctx);
        }
        $this->logInfo("CopyMove/Rename (svn delegate)", array("files"=>$init["SELECTION"]));

        $mess = LocaleService::getMessages();

        $serialStream = new SerializableResponseStream([new UserMessage($mess["meta.svn.5"]), new ReloadMessage()]);
        $responseInterface = $responseInterface->withBody($serialStream);
    }

    /**
     * @param $actionName
     * @param $httpVars
     * @param $filesVars
     * @param ContextInterface $ctx
     * @throws \Exception
     */
    public function deleteSelection(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface)
    {
        $actionName     = $requestInterface->getAttribute("action");
        $httpVars       = $requestInterface->getParsedBody();
        $ctx            = $requestInterface->getAttribute("ctx");

        $init = $this->initDirAndSelection($ctx, $httpVars, array(), true);
        if (isSet($init["RECYCLE"]) && isSet($init["RECYCLE"]["action"]) && $init["RECYCLE"]["action"] != "delete") {
            $httpVars["dest"] = TextEncoder::fromUTF8($init["RECYCLE"]["dest"]);
            $updatedRequest = $requestInterface->withAttribute("action", "move")->withParsedBody($httpVars);
            $this->copyOrMoveSelection($updatedRequest, $responseInterface);

            $userSelection = $init["ORIGINAL_SELECTION"];
            $files = $userSelection->getFiles();
            if ($actionName == "delete") {
                foreach ($files as $file) {
                    RecycleBinManager::fileToRecycle($file);
                }
            } else if ($actionName == "restore") {
                foreach ($files as $file) {
                    RecycleBinManager::deleteFromRecycle($file);
                }
            }
            $this->commitChanges($actionName, array("dir" => RecycleBinManager::getRelativeRecycle()), [], $ctx);
            return ;
        }
        foreach ($init["SELECTION"] as $selectedFile) {
            $res = ExecSvnCmd('svn delete', $selectedFile, '--force');
        }
        $this->commitMessageParams = "[".implode(",",$init["SELECTION"])."]";
        $this->commitChanges($actionName, $httpVars, [], $ctx);
        $this->logInfo("Delete (svn delegate)", array("files"=>$init["SELECTION"]));

        $mess = LocaleService::getMessages();
        $serialStream = new SerializableResponseStream([new UserMessage($mess["meta.svn.51"]), new ReloadMessage()]);
        $responseInterface = $responseInterface->withBody($serialStream);
    }

    /**
     * @param $actionName
     * @param $httpVars
     * @param $filesVars
     * @param ContextInterface $ctx
     * @throws \Exception
     */
    public function commitChanges($actionName, $httpVars, $filesVars, ContextInterface $ctx)
    {
        if (is_array($httpVars)) {
            $init = $this->initDirAndSelection($ctx, $httpVars);
            $args = $init["DIR"];
        } else {
            $args = $httpVars;
        }
        $status = ExecSvnCmd('svn status', $args);
        if (trim(implode("", $status[IDX_STDOUT])) == "") {
            return;
        }
        $command = "svn commit";
        $user = $ctx->getUser()->getId();
        $switches = "-m \"Pydio||$user||$actionName".(isSet($this->commitMessageParams)?"||".$this->commitMessageParams:"")."\"";
        $res = ExecSvnCmd($command, $args, $switches);
        if (is_file($args)) {
            $res2 = ExecSvnCmd('svn update', dirname($args), '');
        } else if (is_dir($args)) {
            $res2 = ExecSvnCmd('svn update', $args, '');
        }
    }
    /**
     *
     * @param AJXP_Node $ajxpNode
     */
    public function extractMeta(&$ajxpNode)
    {
        //if(isSet($_SESSION["SVN_COMMAND_RUNNING"]) && $_SESSION["SVN_COMMAND_RUNNING"] === true) return ;
        $realDir = dirname($ajxpNode->getRealFile());
        if (SvnManager::$svnListDir == $realDir) {
            $entries = SvnManager::$svnListCache;
        } else {
            try {
                SvnManager::$svnListDir = $realDir;
                $entries = $this->svnListNode($realDir);
                SvnManager::$svnListCache = $entries;
            } catch (\Exception $e) {
                $this->logError("ExtractMeta", $e->getMessage());
            }
        }
        $fileId = TextEncoder::toUTF8(basename($ajxpNode->getUrl()));
        if (isSet($entries[$fileId])) {
            $ajxpNode->mergeMetadata($entries[$fileId]);
        }
    }

    /**
     * @param $realPath
     * @param null $revision
     * @return array
     * @throws \Exception
     */
    protected function svnListNode($realPath, $revision = null)
    {
        $command = 'svn list';
        $switches = '--xml';
        if ($revision != null) {
            $switches = '--xml -r'.$revision;
        }
        $_SESSION["SVN_COMMAND_RUNNING"] = true;
        //if(substr(strtolower(PHP_OS), 0, 3) == "win") session_write_close();
        try {
            $res = ExecSvnCmd($command, $realPath, $switches);
        } catch (\Exception $e) {
            return array();
        }
        //if(substr(strtolower(PHP_OS), 0, 3) == "win") session_start();
        unset($_SESSION["SVN_COMMAND_RUNNING"]);
        $domDoc = new \DOMDocument();
        $domDoc->loadXML($res[IDX_STDOUT]);
        $xPath = new \DOMXPath($domDoc);
        $entriesList = $xPath->query("list/entry");
        $entries = array();
        foreach ($entriesList as $entry) {
            $logEntry = array();
            $name = $xPath->query("name", $entry)->item(0)->nodeValue;
            $logEntry["last_revision"] = $xPath->query("commit/@revision", $entry)->item(0)->value;
            $logEntry["last_revision_author"] = $xPath->query("commit/author", $entry)->item(0)->nodeValue;
            $logEntry["last_revision_date"] = $xPath->query("commit/date", $entry)->item(0)->nodeValue;
            $logEntry["last_revision_size"] = $xPath->query("size", $entry)->item(0)->nodeValue;
            $entries[$name] = $logEntry;
        }
        return $entries;
    }



}
