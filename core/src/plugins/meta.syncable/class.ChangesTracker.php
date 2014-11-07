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

    public function switchActions($actionName, $httpVars, $fileVars)
    {
        if($actionName != "changes" || !isSet($httpVars["seq_id"])) return false;

        require_once(AJXP_BIN_FOLDER."/dibi.compact.php");
        dibi::connect($this->sqlDriver);
        $filter = null;
        $currentRepo = $this->accessDriver->repository;
        $recycle = $currentRepo->getOption("RECYCLE_BIN");
        $recycle = (!empty($recycle)?$recycle:false);

        HTMLWriter::charsetHeader('application/json', 'UTF-8');
        if(isSet($httpVars["filter"])){
            $filter = AJXP_Utils::decodeSecureMagic($httpVars["filter"]);
            $res = dibi::query("SELECT
                [seq] , [ajxp_changes].[repository_identifier] , [ajxp_changes].[node_id] , [type] , [source] ,  [target] , [ajxp_index].[bytesize], [ajxp_index].[md5], [ajxp_index].[mtime], [ajxp_index].[node_path]
                FROM [ajxp_changes]
                LEFT JOIN [ajxp_index]
                    ON [ajxp_changes].[node_id] = [ajxp_index].[node_id]
                WHERE [ajxp_changes].[repository_identifier] = %s AND ([source] LIKE %like~ OR [target] LIKE %like~ ) AND [seq] > %i
                ORDER BY [ajxp_changes].[node_id], [seq] ASC",
                $this->computeIdentifier($currentRepo), rtrim($filter, "/")."/", rtrim($filter, "/")."/", AJXP_Utils::sanitize($httpVars["seq_id"], AJXP_SANITIZE_ALPHANUM));
        }else{
            $res = dibi::query("SELECT
                [seq] , [ajxp_changes].[repository_identifier] , [ajxp_changes].[node_id] , [type] , [source] ,  [target] , [ajxp_index].[bytesize], [ajxp_index].[md5], [ajxp_index].[mtime], [ajxp_index].[node_path]
                FROM [ajxp_changes]
                LEFT JOIN [ajxp_index]
                    ON [ajxp_changes].[node_id] = [ajxp_index].[node_id]
                WHERE [ajxp_changes].[repository_identifier] = %s AND [seq] > %i
                ORDER BY [ajxp_changes].[node_id], [seq] ASC",
                $this->computeIdentifier($currentRepo), AJXP_Utils::sanitize($httpVars["seq_id"], AJXP_SANITIZE_ALPHANUM));
        }

        $stream = isSet($httpVars["stream"]);
        $separator = $stream ? "\n" : ",";
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
     * @return String
     */
    protected function computeIdentifier($repository)
    {
        $parts = array($repository->getId());
        if ($repository->securityScope() == 'USER') {
            $parts[] = AuthService::getLoggedUser()->getId();
        } else if ($repository->securityScope() == 'GROUP') {
            $parts[] = AuthService::getLoggedUser()->getGroupPath();
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
     * @param AJXP_Node $oldNode
     * @param AJXP_Node $newNode
     * @param bool $copy
     */
    public function updateNodesIndex($oldNode = null, $newNode = null, $copy = false)
    {

        require_once(AJXP_BIN_FOLDER."/dibi.compact.php");
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
                $repoId = $this->computeIdentifier($oldNode->getRepository());
                // DELETE
                dibi::query("DELETE FROM [ajxp_index] WHERE [node_path] LIKE %like~ AND [repository_identifier] = %s", $oldNode->getPath(), $repoId);
            } else if ($oldNode == null || $copy) {
                // CREATE
                $stat = stat($newNode->getUrl());
                $res = dibi::query("INSERT INTO [ajxp_index]", array(
                    "node_path" => $newNode->getPath(),
                    "bytesize"  => $stat["size"],
                    "mtime"     => $stat["mtime"],
                    "md5"       => $newNode->isLeaf()? md5_file($newNode->getUrl()):"directory",
                    "repository_identifier" => $repoId = $this->computeIdentifier($newNode->getRepository())
                ));
            } else {
                $repoId = $this->computeIdentifier($oldNode->getRepository());
                if ($oldNode->getPath() == $newNode->getPath()) {
                    // CONTENT CHANGE
                    clearstatcache();
                    $stat = stat($newNode->getUrl());
                    $this->logDebug("Content changed", "current stat size is : " . $stat["size"]);
                    dibi::query("UPDATE [ajxp_index] SET ", array(
                        "bytesize"  => $stat["size"],
                        "mtime"     => $stat["mtime"],
                        "md5"       => md5_file($newNode->getUrl())
                    ), "WHERE [node_path] = %s AND [repository_identifier] = %s", $oldNode->getPath(), $repoId);
                } else {
                    // PATH CHANGE ONLY
                    $newNode->loadNodeInfo();
                    if ($newNode->isLeaf()) {
                        dibi::query("UPDATE [ajxp_index] SET ", array(
                            "node_path"  => $newNode->getPath(),
                        ), "WHERE [node_path] = %s AND [repository_identifier] = %s", $oldNode->getPath(), $repoId);
                    } else {
                        dibi::query("UPDATE [ajxp_index] SET [node_path]=REPLACE( REPLACE(CONCAT('$$$',[node_path]), CONCAT('$$$', %s), CONCAT('$$$', %s)) , '$$$', '') ",
                            $oldNode->getPath(),
                            $newNode->getPath()
                            , "WHERE [node_path] LIKE %like~ AND [repository_identifier] = %s", $oldNode->getPath(), $repoId);
                    }

                }
            }
        } catch (Exception $e) {
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
