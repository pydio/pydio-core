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
namespace Pydio\Access\Meta\Sync;

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Filter\AJXP_Permission;
use Pydio\Core\Controller\CliRunner;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\RepositoryService;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\DBHelper;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\OptionsHelper;
use Pydio\Core\Controller\HTMLWriter;
use Pydio\Core\PluginFramework\SqlTableProvider;
use Pydio\Log\Core\Logger;
use Pydio\Access\Meta\Core\AbstractMetaSource;
use Pydio\Tasks\Schedule;
use Pydio\Tasks\TaskService;
use \dibi as dibi;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Generates and caches and md5 hash of each file
 * @package AjaXplorer_Plugins
 * @subpackage Meta
 */
class ChangesTracker extends AbstractMetaSource implements SqlTableProvider
{
    private $sqlDriver;

    /**
     * @param ContextInterface $ctx
     * @param array $options
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        $this->sqlDriver = OptionsHelper::cleanDibiDriverParameters(["group_switch_value" => "core"]);
        parent::init($ctx, $options);
    }

    /**
     * @param ContextInterface $ctx
     * @param string $path
     * @return bool
     */
    protected function excludeFromSync($ctx, $path){
        $excludedExtensions = ["dlpart"];
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if(!empty($ext) && in_array($ext, $excludedExtensions)){
            return true;
        }
        try{
            $ctx->getRepository()->getDriverInstance($ctx)->filterUserSelectionToHidden($ctx, [$path]);
        }catch(\Exception $e){
            return true;
        }
        return false;
    }

    /**
     * @param ContextInterface $ctx
     */
    protected function indexIsSync(ContextInterface $ctx){
        // Grab all folders mtime and compare them
        $repoIdentifier = $this->computeIdentifier($ctx);
        $res = dibi::query("SELECT [node_path],[mtime] FROM [ajxp_index] WHERE [md5] = %s AND [repository_identifier] = %s", 'directory', $repoIdentifier);
        $modified = [];

        // REGISTER ROOT ANYWAY: WE PROBABLY CAN'T GET A "FILEMTIME" ON IT.
        $mod = [
            "url"   => $ctx->getUrlBase(),
            "path"  => "/",
            "children" => []
        ];
        $children = dibi::query("SELECT [node_path],[mtime] FROM [ajxp_index] WHERE [repository_identifier] = %s AND [node_path] LIKE %s AND [node_path] NOT LIKE %s",
             $repoIdentifier, "/%", "/%/%");
        foreach($children as $cRow){
            $cp = substr($cRow->node_path, 1);
            if(empty($cp)) continue;
            if(PHP_OS == 'Darwin'){
                $cp = \Normalizer::normalize($cp, \Normalizer::FORM_D);
            }
            $mod["children"][$cp] = $cRow->mtime;
        }
        $modified[] = $mod;

        clearstatcache();
        // CHECK ALL FOLDERS
        foreach($res as $row){
            $path = $row->node_path;
            $mtime = intval($row->mtime);
            $url = $ctx->getUrlBase().$path;
            $currentTime = @filemtime($url);
            if($currentTime === false && !file_exists($url)) {
                // Deleted folder!
                $this->logDebug(__FUNCTION__, "Folder deleted directly on storage: ".$url);
                $node = new AJXP_Node($url);
                Controller::applyHook("node.change", [&$node, null, false], true);
                continue;
            }
            if($currentTime > $mtime){
                $mod = [
                    "url" => $url,
                    "path" => $path,
                    "children" => [],
                    "current_time" => $currentTime
                ];
                $children = dibi::query("SELECT [node_path],[mtime],[md5] FROM [ajxp_index] WHERE [repository_identifier] = %s AND [node_path] LIKE %s AND [node_path] NOT LIKE %s",
                    $repoIdentifier, "$path/%", "$path/%/%");
                foreach($children as $cRow){
                    $cp = substr($cRow->node_path, strlen($path)+1);
                    if(empty($cp)) continue;
                    if(PHP_OS == 'Darwin'){
                        $cp = \Normalizer::normalize($cp, \Normalizer::FORM_D);
                    }
                    $mod["children"][$cp] = $cRow->mtime;
                }
                $modified[] = $mod;
            }
        }

        // NOW COMPUTE DIFFS
        foreach($modified as $mod_data){
            $url = $mod_data["url"];
            $this->logDebug("Current folder is ".$url);
            $current_time = $mod_data["current_time"];
            $currentChildren = $mod_data["children"];
            $files = scandir($url);
            foreach($files as $f){
                if($f[0] == ".") continue;
                $nodeUrl = $url."/".$f;
                $this->logDebug(__FUNCTION__, "Scanning ".$nodeUrl);
                $node = new AJXP_Node($nodeUrl);
                // Ignore dirs modified time
                // if(is_dir($nodeUrl) && $mod_data["path"] != "/") continue;
                if(!isSet($currentChildren[$f])){
                    if($this->excludeFromSync($ctx, $nodeUrl)){
                        $this->logDebug(__FUNCTION__, "Excluding item detected on storage: ".$nodeUrl);
                        continue;
                    }
                    // New items detected
                    $this->logDebug(__FUNCTION__, "New item detected on storage: ".$nodeUrl);
                    Controller::applyHook("node.change", [null, &$node, false, true], true);
                    continue;
                }else {
                    if(is_dir($nodeUrl)) continue; // Make sure to not trigger a recursive indexation here.
                    if(filemtime($nodeUrl) > $currentChildren[$f]){
                        if($this->excludeFromSync($ctx, $nodeUrl)){
                            $this->logDebug(__FUNCTION__, "Excluding item changed on storage: ".$nodeUrl);
                            continue;
                        }
                        // Changed!
                        $this->logDebug(__FUNCTION__, "Item modified directly on storage: ".$nodeUrl);
                        Controller::applyHook("node.change", [&$node, &$node, false], true);
                    }
                }
            }
            foreach($currentChildren as $cPath => $mtime){
                $this->logDebug(__FUNCTION__, "Existing children ".$cPath);
                if(!in_array($cPath, $files)){
                    if($this->excludeFromSync($ctx, $url."/".$cPath)){
                        $this->logDebug(__FUNCTION__, "Excluding item deleted on storage: ".$url."/".$cPath);
                        continue;
                    }
                    // Deleted
                    $this->logDebug(__FUNCTION__, "File deleted directly on storage: ".$url."/".$cPath);
                    $node = new AJXP_Node($url."/".$cPath);
                    Controller::applyHook("node.change", [&$node, null, false], true);
                }
            }
            // Now "touch" parent directory
            if(isSet($current_time)){
                dibi::query("UPDATE [ajxp_index] SET ", ["mtime" => $current_time], " WHERE [repository_identifier] = %s AND [node_path] = %s", $repoIdentifier, $mod_data["path"]);
            }
        }
    }

    /**
     * @param ContextInterface $ctx
     * @param bool $check
     * @return string
     * @throws \Exception
     */
    protected function getResyncTimestampFile(\Pydio\Core\Model\ContextInterface $ctx, $check = false){
        $repo = $ctx->getRepository();
        $sScope = $repo->securityScope();
        $suffix = "-".$repo->getId();
        if(!empty($sScope)) $suffix = "-".$ctx->getUser()->getId();
        $file = $this->getPluginCacheDir(true, $check)."/storage_changes_time".$suffix;
        return $file;
    }

    /**
     * @param $actionName
     * @param $httpVars
     * @param $fileVars
     * @param ContextInterface $contextInterface
     */
    public function resyncAction($actionName, $httpVars, $fileVars, \Pydio\Core\Model\ContextInterface $contextInterface)
    {
        if (ConfService::backgroundActionsSupported() && !ApplicationState::sapiIsCli()) {
            CliRunner::applyActionInBackground($contextInterface, "resync_storage", $httpVars);
        }else{
            $file = $this->getResyncTimestampFile($contextInterface, true);
            file_put_contents($file, time());
            $this->indexIsSync($contextInterface);
        }
    }

    /**
     * @param $actionName
     * @param $httpVars
     * @param $fileVars
     * @param ContextInterface $contextInterface
     * @return null
     * @throws \Exception
     * @throws \Pydio\Core\Exception\PydioException
     */
    public function switchActions($actionName, $httpVars, $fileVars, \Pydio\Core\Model\ContextInterface $contextInterface)
    {
        if($actionName != "changes" || !isSet($httpVars["seq_id"])) return false;
        if(!dibi::isConnected()) {
            dibi::connect($this->sqlDriver);
        }
        $filter = null;
        $masks = [];
        $currentRepo = $contextInterface->getRepository();
        Controller::applyHook("role.masks", [$contextInterface, &$masks, AJXP_Permission::READ]);
        if(count($masks) == 1 && $masks[0] == "/"){
            $masks = [];
        }
        $recycle = $currentRepo->getContextOption($contextInterface, "RECYCLE_BIN");
        $recycle = (!empty($recycle)?$recycle:false);

        if($this->options["OBSERVE_STORAGE_CHANGES"] === true){
            // Do it every XX minutes
            $minutes = 5;
            if(isSet($this->options["OBSERVE_STORAGE_EVERY"])){
                $minutes = intval($this->options["OBSERVE_STORAGE_EVERY"]);
            }
            $file = $this->getResyncTimestampFile($contextInterface);
            $last = 0;
            if(is_file($file)) $last = intval(file_get_contents($file));
            if(time() - $last >  $minutes * 60){
                $this->resyncAction("resync_storage", [], [], $contextInterface);
            }
        }
        if($this->options["REQUIRES_INDEXATION"]){
            $task = TaskService::actionAsTask($contextInterface, "index", []);
            $task->setActionLabel(LocaleService::getMessages(), 'core.index.3');
            TaskService::getInstance()->enqueueTask($task);
            // Unset the REQUIRES_INDEXATION FLAG
            $meta =  $currentRepo->getContextOption($contextInterface, "META_SOURCES");
            unset($meta["meta.syncable"]["REQUIRES_INDEXATION"]);
            $currentRepo->addOption("META_SOURCES", $meta);
            RepositoryService::replaceRepository($currentRepo->getId(), $currentRepo);
        }

        HTMLWriter::charsetHeader('application/json', 'UTF-8');
        $stream = isSet($httpVars["stream"]);
        $separator = $stream ? "\n" : ",";


        $veryLastSeq = intval(dibi::query("SELECT MAX([seq]) FROM [ajxp_changes]")->fetchSingle());
        $seqId = intval(InputFilter::sanitize($httpVars["seq_id"], InputFilter::SANITIZE_ALPHANUM));
        if($veryLastSeq > 0 && $seqId > $veryLastSeq){
            // This is not normal! Send a signal reload all changes from start.
            if(!$stream) echo json_encode(['changes'=> [], 'last_seq'=>1]);
            else echo 'LAST_SEQ:1';
            return null;
        }


        $ands = [];
        $ands[] = ["[ajxp_changes].[repository_identifier] = %s", $this->computeIdentifier($contextInterface)];
        $ands[]= ["[seq] > %i", $seqId];
        if(isSet($httpVars["filter"])) {
            $filter = InputFilter::decodeSecureMagic($httpVars["filter"]);
            $filterLike = rtrim($filter, "/") . "/";
            $ands[] = ["[source] LIKE %like~ OR [target] LIKE %like~", $filterLike, $filterLike];
        }
        if(count($masks)){
            $ors = [];
            foreach($masks as $mask){
                $trimmedMask = rtrim($mask, "/") ;
                $filterLike = $trimmedMask . "/";
                $ors[] = ["[source] LIKE %like~ OR [target] LIKE %like~", $filterLike, $filterLike];
                $ors[] = ["[source] = %s OR [target] = %s", $trimmedMask, $trimmedMask];
            }
            if(count($ors)){
                $ands[] = ["%or", $ors];
            }
        }
        $res = dibi::query("SELECT
            [seq] , [ajxp_changes].[repository_identifier] , [ajxp_changes].[node_id] , [type] , [source] ,  [target] , [ajxp_index].[bytesize], [ajxp_index].[md5], [ajxp_index].[mtime], [ajxp_index].[node_path]
            FROM [ajxp_changes]
            LEFT JOIN [ajxp_index]
                ON [ajxp_changes].[node_id] = [ajxp_index].[node_id]
            WHERE %and
            ORDER BY [ajxp_changes].[node_id], [seq] ASC",
            $ands);

        if(!$stream) echo '{"changes":[';
        $previousNodeId = -1;
        $previousRow = null;
        $order = ["path"=>0, "content"=>1, "create"=>2, "delete"=>3];
        $relocateAttrs = ["bytesize", "md5", "mtime", "node_path", "repository_identifier"];
        $valuesSent = false;
        foreach ($res as $row) {
            $row->node = [];
            foreach ($relocateAttrs as $att) {
                $row->node[$att] = $row->$att;
                unset($row->$att);
            }
            if(!empty($recycle)) $this->cancelRecycleNodes($row, $recycle);
            if($this->pathOutOfMask($row->node["node_path"], $masks)){
                $row->node["node_path"] = false;
            }
            if(!isSet($httpVars["flatten"]) || $httpVars["flatten"] == "false"){

                if(!$this->filterMasks($row, $masks) && !$this->filterRow($row, $filter)){
                    if ($valuesSent) {
                        echo $separator;
                    }
                    echo json_encode($row);
                    $valuesSent = true;
                }

            }else{

                if ($row->node_id == $previousNodeId) {
                    $previousRow->target = $row->target;
                    $previousRow->seq = $row->seq;
                    // Specific case, maybe linked to recycle bin management
                    // A create should make a new node ID.
                    if ($row->type === "create" && $previousRow->type === "delete"){
                        $previousRow->type = "create";
                    }else if ($order[$row->type] > $order[$previousRow->type]) {
                        $previousRow->type = $row->type;
                    }
                } else {
                    if (isSet($previousRow) && ($previousRow->source != $previousRow->target || $previousRow->type == "content")) {
                        if($this->filterMasks($previousRow, $masks) || $this->filterRow($previousRow, $filter)){
                            $previousRow = $row;
                            $previousNodeId = $row->node_id;
                            $lastSeq = $row->seq;
                            continue;
                        }
                        if($valuesSent) echo $separator;
                        echo json_encode($previousRow);
                        $valuesSent = true;
                    }
                    $previousRow = $row;
                    $previousNodeId = $row->node_id;
                }
                if(!isSet($lastSeq) || $row->seq > $lastSeq){
                    $lastSeq = $row->seq;
                }
                flush();
            }
        }

        // SEND LAST ROW IF THERE IS ONE
        if (isSet($previousRow) && ($previousRow->source != $previousRow->target || $previousRow->type == "content")
            && !$this->pathOutOfMask($previousRow->target, $masks) && !$this->filterMasks($previousRow, $masks) && !$this->filterRow($previousRow, $filter)) {

            if($valuesSent) echo $separator;
            echo json_encode($previousRow);
            if (!isSet($lastSeq) || $previousRow->seq > $lastSeq){
                $lastSeq = $previousRow->seq;
            }
            $valuesSent = true;
        }

        if (isSet($lastSeq)) {
            if($stream){
                echo("\nLAST_SEQ:".$lastSeq);
            }else{
                echo '], "last_seq":'.$lastSeq.'}';
            }
        } else {
            $lastSeq = dibi::query("SELECT MAX([seq]) FROM [ajxp_changes]")->fetchSingle();
            if(empty($lastSeq)) $lastSeq = 1;
            if($stream){
                echo("\nLAST_SEQ:".$lastSeq);
            }else{
                echo '], "last_seq":'.$lastSeq.'}';
            }
        }
        return null;
    }

    /**
     * @param $row
     * @param $recycle
     */
    protected function cancelRecycleNodes(&$row, $recycle){
        if($row->type != 'path') return;
        if(strpos($row->source, '/'.$recycle) === 0){
            $row->source = 'NULL';
            $row->type  = 'create';
        }else if(strpos($row->target, '/'.$recycle) === 0){
            $row->target = 'NULL';
            $row->type   = 'delete';
        }
    }

    /**
     * @param $previousRow
     * @param null $filter
     * @return bool
     */
    protected function filterRow(&$previousRow, $filter = null){
        if($filter == null) return false;
        $srcInFilter = strpos($previousRow->source, $filter."/") === 0;
        $targetInFilter = strpos($previousRow->target, $filter."/") === 0;
        if(!$srcInFilter && !$targetInFilter){
            return true;
        }
        if($previousRow->type == 'path'){
            if(!$srcInFilter){
                $previousRow->type = 'create';
                $previousRow->source = 'NULL';
            }else if(!$targetInFilter){
                $previousRow->type = 'delete';
                $previousRow->target = 'NULL';
            }
        }
        if($srcInFilter){
            $previousRow->source = substr($previousRow->source, strlen($filter));
        }
        if($targetInFilter){
            $previousRow->target = substr($previousRow->target, strlen($filter));
        }
        if($previousRow->type != 'delete'){
            $previousRow->node['node_path'] = substr($previousRow->node['node_path'], strlen($filter));
        }else if(strpos($previousRow->node['node_path'], $filter) !== 0){
            $previousRow->node['node_path'] = false;
        }
        return false;
    }

    /**
     * @param $testPath
     * @param array $masks
     * @return bool
     */
    protected function pathOutOfMask($testPath, $masks = []){
        if(!count($masks)) return false;
        $regexps = [];
        foreach($masks as $path){
            $regexps[] = '^'.preg_quote($path.'/', '/');
        }
        $regexp = '/'.implode("|", $regexps).'/';
        $inMask = ($testPath == 'NULL') || $testPath === false || in_array($testPath, $masks) || preg_match($regexp, $testPath);
        return !$inMask;
    }

    /**
     * @param $previousRow
     * @param array $masks
     * @return bool
     */
    protected function filterMasks(&$previousRow, $masks = []){
        if(!count($masks)) return false;

        $srcInFilter = !$this->pathOutOfMask($previousRow->source, $masks);
        $targetInFilter = !$this->pathOutOfMask($previousRow->target, $masks);

        if(!$srcInFilter && !$targetInFilter){
            return true;
        }
        if($previousRow->type == 'path'){
            if(!$srcInFilter){
                $previousRow->type = 'create';
                $previousRow->source = 'NULL';
            }else if(!$targetInFilter){
                $previousRow->type = 'delete';
                $previousRow->target = 'NULL';
            }
        }
        return false;
    }

    /**
     * @param ContextInterface $ctx
     * @return String
     */
    protected function computeIdentifier(ContextInterface $ctx)
    {
        $parts = [$ctx->getRepositoryId()];
        $repository = $ctx->getRepository();
        if ($repository->securityScope() == 'USER') {
            $parts[] = $ctx->getUser()->getId();
        } else if ($repository->securityScope() == 'GROUP') {
            $parts[] = $ctx->getUser()->getGroupPath();
        }
        return implode("-", $parts);
    }

    /**
     * Called on workspace.after_delete event. Remove all references to this WS in the DB.
     * Find all repo identifier exactly equal to $repoId , or like $repoId-%
     * @param $repoId
     */
    public function clearIndexForWorkspaceId($repoId){
        if(!dibi::isConnected()) {
            dibi::connect($this->sqlDriver);
        }
        dibi::query("DELETE FROM [ajxp_index] WHERE [repository_identifier] = %s OR [repository_identifier] LIKE %like~", $repoId, $repoId."-");
    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $oldNode
     * @param AJXP_Node $newNode
     * @param bool $copy
     */
    public function updateNodesIndex($oldNode = null, $newNode = null, $copy = false)
    {
        if(!dibi::isConnected()) {
            dibi::connect($this->sqlDriver);
        }
        $refNode = ($oldNode !== null ? $oldNode : $newNode);
        try {
            if ($newNode != null && $this->excludeNode($newNode)) {
                // CREATE
                if($oldNode == null) {
                    Logger::debug("Ignoring ".$newNode->getUrl()." for indexation");
                    return;
                }else{
                    Logger::debug("Target node is excluded, see it as a deletion: ".$newNode->getUrl());
                    $newNode = null;
                }
            }
            if ($newNode == null) {
                $repoId = $this->computeIdentifier($refNode->getContext());
                // DELETE
                $this->logDebug('DELETE', $oldNode->getUrl());
                dibi::query("DELETE FROM [ajxp_index] WHERE [node_path] LIKE %like~ AND [repository_identifier] = %s", $oldNode->getPath(), $repoId);
            } else if ($oldNode == null || $copy) {
                // CREATE
                $stat = stat($newNode->getUrl());
                $newNode->setLeaf(!($stat['mode'] & 040000));
                $this->logDebug('INSERT', $newNode->getUrl());
                dibi::query("INSERT INTO [ajxp_index]", [
                    "node_path" => $newNode->getPath(),
                    "bytesize"  => $stat["size"],
                    "mtime"     => $stat["mtime"],
                    "md5"       => $newNode->isLeaf()? md5_file($newNode->getUrl()):"directory",
                    "repository_identifier" => $repoId = $this->computeIdentifier($refNode->getContext())
                ]);
                if($copy && !$newNode->isLeaf()){
                    // Make sure to index the content of this folder
                    $this->logInfo("Core.index", "Should reindex folder ".$newNode->getPath());
                    $task = TaskService::actionAsTask($newNode->getContext(), "index", ["file" => $newNode->getPath()]);
                    $task->setActionLabel(LocaleService::getMessages(), 'core.index.3');
                    $task->setSchedule(new Schedule(Schedule::TYPE_ONCE_DEFER));
                    TaskService::getInstance()->enqueueTask($task);
                }
            } else {
                $repoId = $this->computeIdentifier($refNode->getContext());
                if ($oldNode->getPath() == $newNode->getPath()) {
                    // CONTENT CHANGE
                    clearstatcache();
                    $stat = stat($newNode->getUrl());
                    $this->logDebug("Content changed", "current stat size is : " . $stat["size"]);
                    $this->logDebug('UPDATE CONTENT', $newNode->getUrl());
                    dibi::query("UPDATE [ajxp_index] SET ", [
                        "bytesize"  => $stat["size"],
                        "mtime"     => $stat["mtime"],
                        "md5"       => md5_file($newNode->getUrl())
                    ], "WHERE [node_path] = %s AND [repository_identifier] = %s", $oldNode->getPath(), $repoId);
                    try{
                        $rowCount = dibi::getAffectedRows();
                        if($rowCount === 0){
                            $this->logError(__FUNCTION__, "There was an update event on a non-indexed node (".$newNode->getPath()."), creating index entry!");
                            $this->updateNodesIndex(null, $newNode, false);
                        }
                    }catch (\Exception $e){}

                } else {
                    // PATH CHANGE ONLY
                    $newNode->loadNodeInfo();
                    if ($newNode->isLeaf()) {
                        $this->logDebug('UPDATE LEAF PATH', $newNode->getUrl());
                        dibi::query("UPDATE [ajxp_index] SET ", [
                            "node_path"  => $newNode->getPath(),
                        ], "WHERE [node_path] = %s AND [repository_identifier] = %s", $oldNode->getPath(), $repoId);
                        try{
                            $rowCount = dibi::getAffectedRows();
                            if($rowCount === 0){
                                $this->logError(__FUNCTION__, "There was an update event on a non-indexed node (".$newNode->getPath()."), creating index entry!");
                                $this->updateNodesIndex(null, $newNode, false);
                            }
                        }catch (\Exception $e){}
                    } else {
                        $this->logDebug('UPDATE FOLDER PATH', $newNode->getUrl());
                        dibi::query("UPDATE [ajxp_index] SET [node_path]=REPLACE( REPLACE(CONCAT('$$$',[node_path]), CONCAT('$$$', %s), CONCAT('$$$', %s)) , '$$$', '') ",
                            $oldNode->getPath(),
                            $newNode->getPath(),
                            "WHERE ([node_path] = %s OR [node_path] LIKE %like~) AND [repository_identifier] = %s",
                            $oldNode->getPath(),
                            rtrim($oldNode->getPath(), '/') . '/',
                            $repoId);
                        try{
                            $rowCount = dibi::getAffectedRows();
                            if($rowCount === 0){
                                $this->logError(__FUNCTION__, "There was an update event on a non-indexed folder (".$newNode->getPath()."), relaunching a recursive indexation!");
                                $task = TaskService::actionAsTask($newNode->getContext(), "index", ["file" => $newNode->getPath()]);
                                $task->setActionLabel(LocaleService::getMessages(), 'core.index.3');
                                $task->setSchedule(new Schedule(Schedule::TYPE_ONCE_DEFER));
                                TaskService::getInstance()->enqueueTask($task);

                            }
                        }catch (\Exception $e){}
                    }

                }
            }
        } catch (\Exception $e) {
            Logger::error("[meta.syncable]", "Exception", $e->getTraceAsString());
            Logger::error("[meta.syncable]", "Indexation", $e->getMessage());
        }

    }

    /**
     * @param AJXP_Node $node
     * @param integer $result
     */
    public function computeSizeRecursive(&$node, &$result){
        
        $id = $this->computeIdentifier($node->getContext());
        $res = dibi::query("SELECT SUM([bytesize]) FROM [ajxp_index] WHERE [repository_identifier] = %s AND ([node_path] = %s OR [node_path] LIKE %s)", $id, $node->getPath(), rtrim($node->getPath(), "/")."/%");
        $result = floatval($res->fetchSingle());

    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     * @return bool
     */
    protected function excludeNode($node){
        // DO NOT EXCLUDE RECYCLE INDEXATION, OTHERWISE RESTORED DATA IS NOT DETECTED!
        //$repo = $node->getRepository();
        //$recycle = $repo->getOption("RECYCLE_BIN");
        //if(!empty($recycle) && strpos($node->getPath(), "/".trim($recycle, "/")) === 0) return true;

        // Other exclusions conditions here?
        return false;
    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     */
    public function indexNode($node){
        // Create
        $this->updateNodesIndex(null, $node, false);
    }

    /**
     * @param AJXP_Node $node
     */
    public function clearIndexForNode($node){
        // Delete
        $this->updateNodesIndex($node, null, false);
    }

    /**
     * @param ContextInterface $context
     * @param array $metaSourcesOptions
     */
    public function setIndexationRequiredFlag($context, &$metaSourcesOptions){
        if(isSet($metaSourcesOptions["meta.syncable"]) && (!isSet($metaSourcesOptions["meta.syncable"]["REPO_SYNCABLE"]) || $metaSourcesOptions["meta.syncable"]["REPO_SYNCABLE"] === true )){
            $metaSourcesOptions["meta.syncable"]["REQUIRES_INDEXATION"] = true;
        }
    }

    /**
     * @param array $param
     * @return string
     * @throws \Exception
     */
    public function installSQLTables($param)
    {
        $p = OptionsHelper::cleanDibiDriverParameters(isSet($param) && isSet($param["SQL_DRIVER"]) ? $param["SQL_DRIVER"] : $this->sqlDriver);
        return DBHelper::runCreateTablesQuery($p, $this->getBaseDir() . "/create.sql");
    }

}
