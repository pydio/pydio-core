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
class ChangesTracker extends AJXP_AbstractMetaSource
{
    private $sqlDriver;

    public function init($options)
    {
        $this->sqlDriver = AJXP_Utils::cleanDibiDriverParameters(array("group_switch_value" => "core"));
        parent::init($options);
    }

    protected function excludeFromSync($path){
        $excludedExtensions = array("dlpart");
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if(!empty($ext) && in_array($ext, $excludedExtensions)){
            return true;
        }
        try{
            $this->accessDriver->filterUserSelectionToHidden(array($path));
        }catch(Exception $e){
            return true;
        }
        return false;
    }

    protected function indexIsSync(){
        // Grab all folders mtime and compare them
        $repoIdentifier = $this->computeIdentifier($this->accessDriver->repository);
        $res = dibi::query("SELECT [node_path],[mtime] FROM [ajxp_index] WHERE [md5] = %s AND [repository_identifier] = %s", 'directory', $repoIdentifier);
        $modified = array();

        // REGISTER ROOT ANYWAY: WE PROBABLY CAN'T GET A "FILEMTIME" ON IT.
        $mod = array(
            "url"   => $this->accessDriver->getResourceUrl(""),
            "path"  => "/",
            "children" => array()
        );
        $children = dibi::query("SELECT [node_path],[mtime] FROM [ajxp_index] WHERE [repository_identifier] = %s AND [node_path] LIKE %s AND [node_path] NOT LIKE %s",
             $repoIdentifier, "/%", "/%/%");
        foreach($children as $cRow){
            $cp = substr($cRow->node_path, 1);
            if(empty($cp)) continue;
            if(PHP_OS == 'Darwin'){
                $cp = Normalizer::normalize($cp, Normalizer::FORM_D);
            }
            $mod["children"][$cp] = $cRow->mtime;
        }
        $modified[] = $mod;

        clearstatcache();
        // CHECK ALL FOLDERS
        foreach($res as $row){
            $path = $row->node_path;
            $mtime = intval($row->mtime);
            $url = $this->accessDriver->getResourceUrl($path);
            $currentTime = @filemtime($url);
            if($currentTime === false && !file_exists($url)) {
                // Deleted folder!
                $this->logDebug(__FUNCTION__, "Folder deleted directly on storage: ".$url);
                $node = new AJXP_Node($url);
                AJXP_Controller::applyHook("node.change", array(&$node, null, false), true);
                continue;
            }
            if($currentTime > $mtime){
                $mod = array(
                    "url" => $url,
                    "path" => $path,
                    "children" => array(),
                    "current_time" => $currentTime
                );
                $children = dibi::query("SELECT [node_path],[mtime],[md5] FROM [ajxp_index] WHERE [repository_identifier] = %s AND [node_path] LIKE %s AND [node_path] NOT LIKE %s",
                    $repoIdentifier, "$path/%", "$path/%/%");
                foreach($children as $cRow){
                    $cp = substr($cRow->node_path, strlen($path)+1);
                    if(empty($cp)) continue;
                    if(PHP_OS == 'Darwin'){
                        $cp = Normalizer::normalize($cp, Normalizer::FORM_D);
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
                    if($this->excludeFromSync($nodeUrl)){
                        $this->logDebug(__FUNCTION__, "Excluding item detected on storage: ".$nodeUrl);
                        continue;
                    }
                    // New items detected
                    $this->logDebug(__FUNCTION__, "New item detected on storage: ".$nodeUrl);
                    AJXP_Controller::applyHook("node.change", array(null, &$node, false, true), true);
                    continue;
                }else {
                    if(is_dir($nodeUrl)) continue; // Make sure to not trigger a recursive indexation here.
                    if(filemtime($nodeUrl) > $currentChildren[$f]){
                        if($this->excludeFromSync($nodeUrl)){
                            $this->logDebug(__FUNCTION__, "Excluding item changed on storage: ".$nodeUrl);
                            continue;
                        }
                        // Changed!
                        $this->logDebug(__FUNCTION__, "Item modified directly on storage: ".$nodeUrl);
                        AJXP_Controller::applyHook("node.change", array(&$node, &$node, false), true);
                    }
                }
            }
            foreach($currentChildren as $cPath => $mtime){
                $this->logDebug(__FUNCTION__, "Existing children ".$cPath);
                if(!in_array($cPath, $files)){
                    if($this->excludeFromSync($url."/".$cPath)){
                        $this->logDebug(__FUNCTION__, "Excluding item deleted on storage: ".$url."/".$cPath);
                        continue;
                    }
                    // Deleted
                    $this->logDebug(__FUNCTION__, "File deleted directly on storage: ".$url."/".$cPath);
                    $node = new AJXP_Node($url."/".$cPath);
                    AJXP_Controller::applyHook("node.change", array(&$node, null, false), true);
                }
            }
            // Now "touch" parent directory
            if(isSet($current_time)){
                dibi::query("UPDATE [ajxp_index] SET ", array("mtime" => $current_time), " WHERE [repository_identifier] = %s AND [node_path] = %s", $repoIdentifier, $mod_data["path"]);
            }
        }
    }

    protected function getResyncTimestampFile($check = false){
        $repo = ConfService::getRepository();
        $sScope = $repo->securityScope();
        $suffix = "-".$repo->getId();
        if(!empty($sScope)) $suffix = "-".AuthService::getLoggedUser()->getId();
        $file = $this->getPluginCacheDir(true, $check)."/storage_changes_time".$suffix;
        return $file;
    }

    public function resyncAction($actionName, $httpVars, $fileVars)
    {
        if (ConfService::backgroundActionsSupported() && !ConfService::currentContextIsCommandLine()) {
            AJXP_Controller::applyActionInBackground(ConfService::getRepository()->getId(), "resync_storage", $httpVars);
        }else{
            $file = $this->getResyncTimestampFile(true);
            file_put_contents($file, time());
            $this->indexIsSync();
        }
    }

    public function switchActions($actionName, $httpVars, $fileVars)
    {
        if($actionName != "changes" || !isSet($httpVars["seq_id"])) return false;
        if(!dibi::isConnected()) {
            dibi::connect($this->sqlDriver);
        }
        $filter = null;
        $currentRepo = $this->accessDriver->repository;
        $recycle = $currentRepo->getOption("RECYCLE_BIN");
        $recycle = (!empty($recycle)?$recycle:false);

        if($this->options["OBSERVE_STORAGE_CHANGES"] === true){
            // Do it every XX minutes
            $minutes = 5;
            if(isSet($this->options["OBSERVE_STORAGE_EVERY"])){
                $minutes = intval($this->options["OBSERVE_STORAGE_EVERY"]);
            }
            $file = $this->getResyncTimestampFile();
            $last = 0;
            if(is_file($file)) $last = intval(file_get_contents($file));
            if(time() - $last >  $minutes * 60){
                $this->resyncAction("resync_storage", array(), array());
            }
        }
        if($this->options["REQUIRES_INDEXATION"]){
            if (ConfService::backgroundActionsSupported()){
                AJXP_Controller::applyActionInBackground(ConfService::getRepository()->getId(), "index", array());
            }else{
                AJXP_Controller::findActionAndApply("index", array(), array());
            }
            // Unset the REQUIRES_INDEXATION FLAG
            $meta =  $currentRepo->getOption("META_SOURCES");
            unset($meta["meta.syncable"]["REQUIRES_INDEXATION"]);
            $currentRepo->addOption("META_SOURCES", $meta);
            ConfService::replaceRepository($currentRepo->getId(), $currentRepo);
        }

        HTMLWriter::charsetHeader('application/json', 'UTF-8');
        $stream = isSet($httpVars["stream"]);
        $separator = $stream ? "\n" : ",";


        $veryLastSeq = intval(dibi::query("SELECT MAX([seq]) FROM [ajxp_changes]")->fetchSingle());
        $seqId = intval(AJXP_Utils::sanitize($httpVars["seq_id"], AJXP_SANITIZE_ALPHANUM));
        if($veryLastSeq > 0 && $seqId > $veryLastSeq){
            // This is not normal! Send a signal reload all changes from start.
            if(!$stream) echo json_encode(array('changes'=>array(), 'last_seq'=>1));
            else echo 'LAST_SEQ:1';
            return null;
        }


        if(isSet($httpVars["filter"])){
            $filter = AJXP_Utils::decodeSecureMagic($httpVars["filter"]);
            $res = dibi::query("SELECT
                [seq] , [ajxp_changes].[repository_identifier] , [ajxp_changes].[node_id] , [type] , [source] ,  [target] , [ajxp_index].[bytesize], [ajxp_index].[md5], [ajxp_index].[mtime], [ajxp_index].[node_path]
                FROM [ajxp_changes]
                LEFT JOIN [ajxp_index]
                    ON [ajxp_changes].[node_id] = [ajxp_index].[node_id]
                WHERE [ajxp_changes].[repository_identifier] = %s AND ([source] LIKE %like~ OR [target] LIKE %like~ ) AND [seq] > %i
                ORDER BY [ajxp_changes].[node_id], [seq] ASC",
                $this->computeIdentifier($currentRepo), rtrim($filter, "/")."/", rtrim($filter, "/")."/", $seqId);
        }else{
            $res = dibi::query("SELECT
                [seq] , [ajxp_changes].[repository_identifier] , [ajxp_changes].[node_id] , [type] , [source] ,  [target] , [ajxp_index].[bytesize], [ajxp_index].[md5], [ajxp_index].[mtime], [ajxp_index].[node_path]
                FROM [ajxp_changes]
                LEFT JOIN [ajxp_index]
                    ON [ajxp_changes].[node_id] = [ajxp_index].[node_id]
                WHERE [ajxp_changes].[repository_identifier] = %s AND [seq] > %i
                ORDER BY [ajxp_changes].[node_id], [seq] ASC",
                $this->computeIdentifier($currentRepo), $seqId);
        }

        if(!$stream) echo '{"changes":[';
        $previousNodeId = -1;
        $previousRow = null;
        $order = array("path"=>0, "content"=>1, "create"=>2, "delete"=>3);
        $relocateAttrs = array("bytesize", "md5", "mtime", "node_path", "repository_identifier");
        $valuesSent = false;
        foreach ($res as $row) {
            $row->node = array();
            foreach ($relocateAttrs as $att) {
                $row->node[$att] = $row->$att;
                unset($row->$att);
            }
            if(!empty($recycle)) $this->cancelRecycleNodes($row, $recycle);
            if(!isSet($httpVars["flatten"]) || $httpVars["flatten"] == "false"){

                if(!$this->filterRow($row, $filter)){
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
                    if ($order[$row->type] > $order[$previousRow->type]) {
                        $previousRow->type = $row->type;
                    }
                } else {
                    if (isSet($previousRow) && ($previousRow->source != $previousRow->target || $previousRow->type == "content")) {
                        if($this->filterRow($previousRow, $filter)){
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
                $lastSeq = $row->seq;
                flush();
            }
	    //CODES HERE HAVE BEEN MOVE OUT OF THE LOOP
        }

        /**********RETURN TO SENDER************/
        // is 'not NULL' included in isSet()?
        if ($previousRow && isSet($previousRow) && ($previousRow->source != $previousRow->target || $previousRow->type == "content") && !$this->filterRow($previousRow, $filter)) {
            if($valuesSent) echo $separator;
            echo json_encode($previousRow);
            if ($previousRow->seq > $lastSeq){
                $lastSeq = $previousRow->seq;
            }
            $valuesSent = true;
        }
        /*************************************/

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

    protected function filterRow(&$previousRow, $filter = null){
        if($filter == null) return false;
        $srcInFilter = strpos($previousRow->source, $filter) === 0;
        $targetInFilter = strpos($previousRow->target, $filter) === 0;
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
     * @param Repository $repository
     * @param null $resolveUserId
     * @return String
     */
    protected function computeIdentifier($repository, $resolveUserId = null)
    {
        $parts = array($repository->getId());
        if ($repository->securityScope() == 'USER') {
            if($resolveUserId != null) {
                $parts[] = $resolveUserId;
            } else {
                $parts[] = AuthService::getLoggedUser()->getId();
            }
        } else if ($repository->securityScope() == 'GROUP') {
            if($resolveUserId != null) {
                $userObject = ConfService::getConfStorageImpl()->createUserObject($resolveUserId);
                if($userObject != null) $parts[] = $userObject->getGroupPath();
            }else{
                $parts[] = AuthService::getLoggedUser()->getGroupPath();
            }
        }
        return implode("-", $parts);
    }

    /**
     * @param Repository $repository
     * @return float
     */
    public function getRepositorySpaceUsage($repository){
        $id = $this->computeIdentifier($repository);
        $res = dibi::query("SELECT SUM([bytesize]) FROM [ajxp_index] WHERE [repository_identifier] = %s", $id);
        return floatval($res->fetchSingle());
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
     * @param AJXP_Node $oldNode
     * @param AJXP_Node $newNode
     * @param bool $copy
     */
    public function updateNodesIndex($oldNode = null, $newNode = null, $copy = false)
    {
        if(!dibi::isConnected()) {
            dibi::connect($this->sqlDriver);
        }
        //$this->logInfo("Syncable index", array($oldNode == null?'null':$oldNode->getUrl(), $newNode == null?'null':$newNode->getUrl()));
        try {
            if ($newNode != null && $this->excludeNode($newNode)) {
                // CREATE
                if($oldNode == null) {
                    AJXP_Logger::debug("Ignoring ".$newNode->getUrl()." for indexation");
                    return;
                }else{
                    AJXP_Logger::debug("Target node is excluded, see it as a deletion: ".$newNode->getUrl());
                    $newNode = null;
                }
            }
            if ($newNode == null) {
                $repoId = $this->computeIdentifier($oldNode->getRepository(), $oldNode->getUser());
                // DELETE
                $this->logDebug('DELETE', $oldNode->getUrl());
                dibi::query("DELETE FROM [ajxp_index] WHERE [node_path] LIKE %like~ AND [repository_identifier] = %s", $oldNode->getPath(), $repoId);
            } else if ($oldNode == null || $copy) {
                // CREATE
                $stat = stat($newNode->getUrl());
                $newNode->setLeaf(!($stat['mode'] & 040000));
                $this->logDebug('INSERT', $newNode->getUrl());
                dibi::query("INSERT INTO [ajxp_index]", array(
                    "node_path" => $newNode->getPath(),
                    "bytesize"  => $stat["size"],
                    "mtime"     => $stat["mtime"],
                    "md5"       => $newNode->isLeaf()? md5_file($newNode->getUrl()):"directory",
                    "repository_identifier" => $repoId = $this->computeIdentifier($newNode->getRepository(), $newNode->getUser())
                ));
            } else {
                $repoId = $this->computeIdentifier($oldNode->getRepository(), $oldNode->getUser());
                if ($oldNode->getPath() == $newNode->getPath()) {
                    // CONTENT CHANGE
                    clearstatcache();
                    $stat = stat($newNode->getUrl());
                    $this->logDebug("Content changed", "current stat size is : " . $stat["size"]);
                    $this->logDebug('UPDATE CONTENT', $newNode->getUrl());
                    dibi::query("UPDATE [ajxp_index] SET ", array(
                        "bytesize"  => $stat["size"],
                        "mtime"     => $stat["mtime"],
                        "md5"       => md5_file($newNode->getUrl())
                    ), "WHERE [node_path] = %s AND [repository_identifier] = %s", $oldNode->getPath(), $repoId);
                    try{
                        $rowCount = dibi::getAffectedRows();
                        if($rowCount === 0){
                            $this->logError(__FUNCTION__, "There was an update event on a non-indexed node (".$newNode->getPath()."), creating index entry!");
                            $this->updateNodesIndex(null, $newNode, false);
                        }
                    }catch (Exception $e){}

                } else {
                    // PATH CHANGE ONLY
                    $newNode->loadNodeInfo();
                    if ($newNode->isLeaf()) {
                        $this->logDebug('UPDATE LEAF PATH', $newNode->getUrl());
                        dibi::query("UPDATE [ajxp_index] SET ", array(
                            "node_path"  => $newNode->getPath(),
                        ), "WHERE [node_path] = %s AND [repository_identifier] = %s", $oldNode->getPath(), $repoId);
                        try{
                            $rowCount = dibi::getAffectedRows();
                            if($rowCount === 0){
                                $this->logError(__FUNCTION__, "There was an update event on a non-indexed node (".$newNode->getPath()."), creating index entry!");
                                $this->updateNodesIndex(null, $newNode, false);
                            }
                        }catch (Exception $e){}
                    } else {
                        $this->logDebug('UPDATE FOLDER PATH', $newNode->getUrl());
                        dibi::query("UPDATE [ajxp_index] SET [node_path]=REPLACE( REPLACE(CONCAT('$$$',[node_path]), CONCAT('$$$', %s), CONCAT('$$$', %s)) , '$$$', '') ",
                            $oldNode->getPath(),
                            $newNode->getPath()
                            , "WHERE [node_path] LIKE %like~ AND [repository_identifier] = %s", $oldNode->getPath(), $repoId);
                        try{
                            $rowCount = dibi::getAffectedRows();
                            if($rowCount === 0){
                                $this->logError(__FUNCTION__, "There was an update event on a non-indexed folder (".$newNode->getPath()."), relaunching a recursive indexation!");
                                AJXP_Controller::findActionAndApply("index", array("file" => $newNode->getPath()), array());
                            }
                        }catch (Exception $e){}
                    }

                }
            }
        } catch (Exception $e) {
            AJXP_Logger::error("[meta.syncable]", "Exception", $e->getTraceAsString());
            AJXP_Logger::error("[meta.syncable]", "Indexation", $e->getMessage());
        }

    }

    /**
     * @param AJXP_Node $node
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
     * @param AJXP_Node $node
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

    public function installSQLTables($param)
    {
        $p = AJXP_Utils::cleanDibiDriverParameters(isSet($param) && isSet($param["SQL_DRIVER"])?$param["SQL_DRIVER"]:$this->sqlDriver);
        return AJXP_Utils::runCreateTablesQuery($p, $this->getBaseDir()."/create.sql");
    }

}
