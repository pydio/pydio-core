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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Access\Indexer\Implementation;

use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\NodesList;
use Pydio\Access\Indexer\Core\AbstractSearchEngineIndexer;
use Pydio\Core\Http\Message\UserMessage;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\StatHelper;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Encapsultion of the Zend_Search_Lucene component as a plugin
 * @package AjaXplorer_Plugins
 * @subpackage Index
 */
class LuceneIndexer extends AbstractSearchEngineIndexer
{
    /**
     * @var \Zend_Search_Lucene_Interface
     */
    private $currentIndex;
    private $metaFields = [];
    private $indexContent = false;
    private $verboseIndexation = false;

    /**
     * @param ContextInterface $ctx
     * @param array $options
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        parent::init($ctx, $options);
        set_include_path(get_include_path().PATH_SEPARATOR.AJXP_INSTALL_PATH."/plugins/index.lucene");
        $metaFields = $this->getContextualOption($ctx, "index_meta_fields");
        if (!empty($metaFields)) {
            $this->metaFields = explode(",",$metaFields);
        }
        $this->indexContent = ($this->getContextualOption($ctx, "index_content") == true);
    }

    /**
     * @param ContextInterface $contextInterface
     * @param AbstractAccessDriver $accessDriver
     */
    public function initMeta(ContextInterface $contextInterface, AbstractAccessDriver $accessDriver)
    {
        parent::initMeta($contextInterface, $accessDriver);
        if (!empty($this->metaFields) || $this->indexContent) {
            $metaFields = $this->metaFields;
            $el = $this->getXPath()->query("/indexer")->item(0);
            if ($this->indexContent) {
                if($this->indexContent) $metaFields[] = "ajxp_document_content";
                $data = ["indexed_meta_fields" => $metaFields,
                              "additionnal_meta_columns" => ["ajxp_document_content" => "Content"]
                ];
                $el->setAttribute("indexed_meta_fields", json_encode($data));
            } else {
                $el->setAttribute("indexed_meta_fields", json_encode($metaFields));
            }
        }
        parent::init($contextInterface, $this->options);
    }


    /**
     * @param string $queryAnalyzer
     * @param int $wildcardLimitation
     */
    protected function setDefaultAnalyzer($queryAnalyzer, $wildcardLimitation)
    {
        switch ($queryAnalyzer) {
            case "utf8num_insensitive":
                \Zend_Search_Lucene_Analysis_Analyzer::setDefault(new \Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive());
                break;
            case "utf8num_sensitive":
                \Zend_Search_Lucene_Analysis_Analyzer::setDefault(new \Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num());
                break;
            case "utf8_insensitive":
                \Zend_Search_Lucene_Analysis_Analyzer::setDefault(new \Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8_CaseInsensitive());
                break;
            case "utf8_sensitive":
                \Zend_Search_Lucene_Analysis_Analyzer::setDefault(new \Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8());
                break;
            case "textnum_insensitive":
                \Zend_Search_Lucene_Analysis_Analyzer::setDefault(new \Zend_Search_Lucene_Analysis_Analyzer_Common_TextNum_CaseInsensitive());
                break;
            case "textnum_sensitive":
                \Zend_Search_Lucene_Analysis_Analyzer::setDefault(new \Zend_Search_Lucene_Analysis_Analyzer_Common_TextNum());
                break;
            case "text_insensitive":
                \Zend_Search_Lucene_Analysis_Analyzer::setDefault(new \Zend_Search_Lucene_Analysis_Analyzer_Common_Text_CaseInsensitive());
                break;
            case "text_sensitive":
                \Zend_Search_Lucene_Analysis_Analyzer::setDefault(new \Zend_Search_Lucene_Analysis_Analyzer_Common_Text());
                break;
            default:
                \Zend_Search_Lucene_Analysis_Analyzer::setDefault(new \Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive());
                break;
        }
        \Zend_Search_Lucene_Search_Query_Wildcard::setMinPrefixLength($wildcardLimitation);

    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @return array|null
     * @throws \Exception
     */
    public function applyAction(\Psr\Http\Message\ServerRequestInterface &$requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {
        $messages = LocaleService::getMessages();
        $actionName = $requestInterface->getAttribute("action");
        $httpVars   = $requestInterface->getParsedBody();
        /** @var ContextInterface $ctx */
        $ctx = $requestInterface->getAttribute("ctx");
        $ctxUser = $ctx->getUser();
        $repoId = $ctx->getRepositoryId();

        $x = new \Pydio\Core\Http\Response\SerializableResponseStream();
        $responseInterface = $responseInterface->withBody($x);

        if ($actionName == "search") {

            // TMP
            if (strpos($httpVars["query"], "keyword:") === 0) {
                $parts = explode(":", $httpVars["query"]);
                $requestInterface = $requestInterface->withAttribute("action", "search_by_keyword")->withParsedBody(["field" => $parts[1]]);
                $this->applyAction($requestInterface, $responseInterface);
                return null;
            }

            require_once("Zend/Search/Lucene.php");
            try {
                $index =  $this->loadIndex($ctx, false);
            } catch (\Exception $ex) {
                if ($this->seemsCurrentlyIndexing($ctx, 3)){
                    $x->addChunk(new UserMessage($messages["index.lucene.11"]));
                }else if (ConfService::backgroundActionsSupported() && !ApplicationState::sapiIsCli() && !isSet($httpVars["skip_unindexed"])) {
                    $task = \Pydio\Tasks\TaskService::actionAsTask($ctx, "index", []);
                    $task->setLabel($messages["index.lucene.7"]);
                    $responseInterface = \Pydio\Tasks\TaskService::getInstance()->enqueueTask($task, $requestInterface, $responseInterface);
                    $x->addChunk(new UserMessage($messages["index.lucene.7"]));
                }else{
                    $x->addChunk(new UserMessage($messages["index.lucene.12"]));
                }
                return null;
            }
            $textQuery = $httpVars["query"];
            if($this->getContextualOption($ctx, "APPLY_ASCII_TRANSLIT") === true){
                $textQuery = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $textQuery);
            }
            if($this->getContextualOption($ctx, "AUTO_WILDCARD") === true && strlen($textQuery) > 0 && ctype_alnum($textQuery)){
                if($textQuery[0] == '"' && $textQuery[strlen($textQuery)-1] == '"'){
                    $textQuery = substr($textQuery, 1, -1);
                }else if($textQuery[strlen($textQuery)-1] != "*" ){
                    $textQuery.="*";
                }
            }
            if(strpos($textQuery, ":") !== false){
                $textQuery = str_replace("ajxp_meta_ajxp_document_content:","body:", $textQuery);
                $textQuery = $this->filterSearchRangesKeywords($textQuery);
                $query = "ajxp_scope:shared AND ($textQuery)";
            }
            else if ((isSet($this->metaFields) || $this->indexContent) && isSet($httpVars["fields"])) {
                $sParts = [];
                foreach (explode(",",$httpVars["fields"]) as $searchField) {
                    if ($searchField == "filename") {
                        $sParts[] = "basename:".$textQuery;
                    } else if ($searchField == "ajxp_document_content"){
                        $sParts[] = $textQuery;
                    } else if (in_array($searchField, $this->metaFields)) {
                        $sParts[] = "ajxp_meta_".$searchField.":".$textQuery;
                    } else if ($searchField == "ajxp_document_content") {
                        $sParts[] = "title:".$textQuery;
                        $sParts[] = "body:".$textQuery;
                        $sParts[] = "keywords:".$textQuery;
                    }
                }
                $query = implode(" OR ", $sParts);
                $query = "ajxp_scope:shared AND ($query)";
                $this->logDebug("Query : $query");
            } else {
                $textQuery = $this->filterSearchRangesKeywords($textQuery);
                $query = "basename:".$textQuery;
            }

            $this->setDefaultAnalyzer(
                $this->getContextualOption($ctx, "QUERY_ANALYSER"),
                intval($this->getContextualOption($ctx, "WILDCARD_LIMITATION"))
            );

            if (isSet($httpVars["current_dir"]) && !empty($httpVars["current_dir"]) && $httpVars["current_dir"] !== "/") {
                $dir = InputFilter::sanitize($httpVars["current_dir"], InputFilter::SANITIZE_DIRNAME);
                $mangledDir = str_replace("/", "AJXPFAKESEP", $dir);
                $dirQuery = new \Zend_Search_Lucene_Search_Query_Wildcard(new \Zend_Search_Lucene_Index_Term($mangledDir .'*', 'node_path'));
                \Zend_Search_Lucene_Search_Query_Wildcard::setMinPrefixLength(0);
                $tQL = \Zend_Search_Lucene::getTermsPerQueryLimit();
                $stringQuery = \Zend_Search_Lucene_Search_QueryParser::parse($query);
                $query = new \Zend_Search_Lucene_Search_Query_Boolean([$stringQuery, $dirQuery], [true, true]);
            }else{
                $query = \Zend_Search_Lucene_Search_QueryParser::parse($query);
            }

            if ($query === "basename:*") {
                $index->setDefaultSearchField("ajxp_node");
                $hits = $index->find("yes");
            } else {
                if(isSet($httpVars["limit"])){
                    $limit = intval($httpVars["limit"]);
                    // Ask one more to detect if there is "more" results or not.
                    $index->setResultSetLimit( $limit + 50 );
                }
                $hits = $index->find($query);
            }
            $commitIndex = false;

            $nodesList = new NodesList();
            $x->addChunk($nodesList);

            $cursor = 0;
            if(!empty($limit) && count($hits) > $limit){
                $nodesList->setPaginationData(count($hits), 1, 1);
            }
            foreach ($hits as $hit) {
                // Backward compatibility
                $hit->node_url = preg_replace("#ajxp\.[a-z_]+://#", "pydio://", $hit->node_url);
                if ($hit->serialized_metadata!=null) {
                    $meta = unserialize(base64_decode($hit->serialized_metadata));
                    if(isSet($meta["ajxp_modiftime"])){
                        $meta["ajxp_relativetime"] = $meta["ajxp_description"] = $messages[4]." ". StatHelper::relativeDate($meta["ajxp_modiftime"], $messages);
                    }
                    $tmpNode = new AJXP_Node($hit->node_url, $meta);
                    if(!$tmpNode->hasUser()){
                        if($hit->ajxp_scope === "user" && $hit->ajxp_user) $tmpNode->setUserId($hit->ajxp_user);
                        else $tmpNode->setUserId($ctx->getUser()->getId());
                    }
                } else {
                    $tmpNode = new AJXP_Node($hit->node_url, []);
                    if(!$tmpNode->hasUser()){
                        if($hit->ajxp_scope === "user" && $hit->ajxp_user) $tmpNode->setUserId($hit->ajxp_user);
                        else $tmpNode->setUserId($ctx->getUser()->getId());
                    }
                    $tmpNode->loadNodeInfo();
                }
                if($tmpNode->getRepositoryId() != $repoId){
                    $this->logDebug(__CLASS__, "Strange case, search retrieves a node from wrong repository!");
                    $index->delete($hit->id);
                    $commitIndex = true;
                    continue;
                }
                if (!file_exists($tmpNode->getUrl())) {
                    $index->delete($hit->id);
                    $commitIndex = true;
                    continue;
                }
                if (!is_readable($tmpNode->getUrl())){
                    continue;
                }
                $basename = basename($tmpNode->getPath());
                $isLeaf = $tmpNode->isLeaf();
                if (!$ctx->getRepository()->getDriverInstance($ctx)->filterNodeName($ctx, $tmpNode->getPath(), $basename, $isLeaf, ["d" => true, "f" => true, "z" => true])){
                    continue;
                }
                $tmpNode->search_score = sprintf("%0.2f", $hit->score);
                $nodesList->addBranch($tmpNode);
                $cursor++;
                if(isSet($limit) && $cursor >= $limit) break;
            }

            if ($commitIndex) {
                $index->commit();
            }
        } else if ($actionName == "search_by_keyword") {
            require_once("Zend/Search/Lucene.php");
            $scope = "user";

            try {
                $index =  $this->loadIndex($ctx, false);
            } catch (\Exception $ex) {
                if (ConfService::backgroundActionsSupported() && !ApplicationState::sapiIsCli()) {
                    $task = \Pydio\Tasks\TaskService::actionAsTask($ctx, "index", []);
                    $task->setLabel($messages["index.lucene.7"]);
                    $responseInterface = \Pydio\Tasks\TaskService::getInstance()->enqueueTask($task, $requestInterface, $responseInterface);
                    $x->addChunk(new UserMessage($messages["index.lucene.7"]));
                }
                return null;
            }
            $sParts = [];
            $searchField = $httpVars["field"];
            if ($searchField == "ajxp_node") {
                $sParts[] = "$searchField:yes";
            } else {
                $sParts[] = "$searchField:true";
            }
            if ($scope == "user" && UsersService::usersEnabled()) {
                if ($ctxUser == null) {
                    throw new \Exception("Cannot find current user");
                }
                $sParts[] = "ajxp_scope:user";
                $sParts[] = "ajxp_user:".$ctxUser->getId();
            } else {
                $sParts[] = "ajxp_scope:shared";
            }
            $query = implode(" AND ", $sParts);
            $this->logDebug("Query : $query");
            $index->setResultSetLimit(isSet($httpVars["limit"]) ? intval($httpVars["limit"]) : 50);
            $hits = $index->find($query);

            $commitIndex = false;

            $nodesList = new NodesList();
            $x->addChunk($nodesList);
            $leafNodes = [];
            foreach ($hits as $hit) {
                // Backward compat with old protocols
                $hit->node_url = preg_replace("#ajxp\.[a-z_]+://#", "pydio://", $hit->node_url);
                if ($hit->serialized_metadata!=null) {
                    $meta = unserialize(base64_decode($hit->serialized_metadata));
                    $tmpNode = new AJXP_Node($hit->node_url, $meta);
                    if(!$tmpNode->hasUser()){
                        if($hit->ajxp_user) $tmpNode->setUserId($hit->ajxp_user);
                        else $tmpNode->setUserId($ctx->getUser()->getId());
                    }
                } else {
                    $tmpNode = new AJXP_Node($hit->node_url, []);
                    if(!$tmpNode->hasUser()){
                        if($hit->ajxp_user) $tmpNode->setUserId($hit->ajxp_user);
                        else $tmpNode->setUserId($ctx->getUser()->getId());
                    }
                    $tmpNode->loadNodeInfo();
                }
                if (!file_exists($tmpNode->getUrl())) {
                    $index->delete($hit->id);
                    $commitIndex = true;
                    continue;
                }
                if (!is_readable($tmpNode->getUrl())){
                    continue;
                }
                $basename = basename($tmpNode->getPath());
                $isLeaf = $tmpNode->isLeaf();
                if (!$ctx->getRepository()->getDriverInstance($ctx)->filterNodeName($ctx, $tmpNode->getPath(), $basename, $isLeaf, ["d"=>true, "f"=>true])){
                    continue;
                }
                $tmpNode->search_score = sprintf("%0.2f", $hit->score);
                if($isLeaf){
                    $leafNodes[]= $tmpNode;
                }else{
                    $nodesList->addBranch($tmpNode);
                }
            }
            foreach ($leafNodes as $leaf){
                $nodesList->addBranch($leaf);
            }
            if ($commitIndex) {
                $index->commit();
            }
        }
        if(isSet($returnNodes)) return $returnNodes;
        else return null;
    }

    /**
     * @param AJXP_Node $parentNode
     */
    public function indexationStarts($parentNode){
        $this->currentIndex = $this->loadTemporaryIndex($parentNode->getContext());
    }

    /**
     * @param AJXP_Node $parentNode
     * @param bool $success
     */
    public function indexationEnds($parentNode, $success){
        if($success){
            $this->logDebug('INDEX.END', 'Optimizing Index');
            $this->currentIndex->optimize();
            $this->logDebug('INDEX.END', 'Commiting Index');
            $this->currentIndex->commit();
            unset($this->currentIndex);
            $this->logDebug('INDEX.END', 'Merging Temporary in main');
            $this->mergeTemporaryIndexToMain($parentNode->getContext());
            $this->logDebug('INDEX.END', 'Done');
        }else{
            $this->deleteTemporaryIndex($parentNode->getContext());
        }
    }

    /**
     * @param AJXP_Node $node
     */
    public function indexationIndexNode($node){
        $this->updateNodeIndex(null, $node, false, false);
    }

    /**
     * @param $url
     */
    public function recursiveIndexation($url)
    {
        //print("Indexing $url \n");
        $this->logDebug("Indexing content of folder ".$url);
        if (ApplicationState::sapiIsCli() && $this->verboseIndexation) {
            print("Indexing content of ".$url."\n");
        }
        if(!ApplicationState::sapiIsCli()) @set_time_limit(60);
        $handle = opendir($url);
        if ($handle !== false) {
            while ( ($child = readdir($handle)) != false) {
                if($child[0] == ".") continue;
                $newUrl = $url."/".$child;
                if (ApplicationState::sapiIsCli() && $this->verboseIndexation) {
                    print("Indexing node ".$newUrl."\n");
                }
                $this->logDebug("Indexing Node ".$newUrl);
                try {
                    $newNode = new AJXP_Node($newUrl);
                    $this->updateNodeIndex(null, $newNode, false, true);
                    Controller::applyHook("node.index.add", [$newNode]);
                } catch (\Exception $e) {
                    if (ApplicationState::sapiIsCli() && $this->verboseIndexation) {
                        print("Error indexing node ".$newUrl." (".$e->getMessage().") \n");
                    }
                    $this->logDebug("Error Indexing Node ".$newUrl." (".$e->getMessage().")");
                }
            }
            closedir($handle);
        } else {
            $this->logDebug("Cannot open $url!!");
        }
    }

    /**
     * Passes the array of META_SOURCES options before creating the shared workspace.
     * Used here to disable the repository_specific_keywords, as path is in fact already fully resolved.
     * @param ContextInterface $context
     * @param $metaOptions array
     */
    public function updateSharedChildOptions($context, &$metaOptions){
        if(isSet($metaOptions["index.lucene"]) && isSet($metaOptions["index.lucene"]["repository_specific_keywords"])){
            unset($metaOptions["index.lucene"]["repository_specific_keywords"]);
        }
    }

    /**
     * Called on workspace.after_delete event, clear the index!
     * @param $repoId
     */
    public function clearWorkspaceIndexes($repoId){
        $iPath = $this->getIndexPath($repoId);
        $this->clearIndexIfExists($iPath);
        $this->clearIndexIfExists($iPath."-PYDIO_TMP");
    }

    /**
     *
     * Hooked to node.meta_change, this will update the index
     *
     * @param AJXP_Node $node
     */
    public function updateNodeIndexMeta($node)
    {
        require_once("Zend/Search/Lucene.php");
        try{

            if (isSet($this->currentIndex)) {
                $index = $this->currentIndex;
            } else {
                $index =  $this->loadIndex($node->getContext(), true);
            }
            \Zend_Search_Lucene_Analysis_Analyzer::setDefault( new \Zend_Search_Lucene_Analysis_Analyzer_Common_TextNum_CaseInsensitive());

            if (UsersService::usersEnabled() && $node->getContext()->hasUser()) {
                $term = new \Zend_Search_Lucene_Index_Term($node->getUrl(), "node_url");
                $hits = $index->termDocs($term);
                foreach ($hits as $hitId) {
                    $hit = $index->getDocument($hitId);
                    if ($hit->ajxp_scope == 'shared' || ($hit->ajxp_scope == 'user' && $hit->ajxp_user == $node->getContext()->getUser()->getId())) {
                        $index->delete($hitId);
                    }
                }
            } else {
                $id = $this->getIndexedDocumentId($index, $node);
                if($id != null) $index->delete($id);
            }
            if(file_exists($node->getUrl())){
                $this->createIndexedDocument($node, $index);
            }
            $this->logDebug(__FILE__, "Indexation passed ".$node->getUrl());
        } catch (\Exception $e){
            $this->logError(__FILE__, "Lucene indexation failed for ".$node->getUrl()." (".$e->getMessage().")");
        }
    }

    /**
     *
     * Hooked to node.change, this will update the index
     * if $oldNode = null => create node $newNode
     * if $newNode = null => delete node $oldNode
     * Else copy or move oldNode to newNode.
     *
     * @param \Pydio\Access\Core\Model\AJXP_Node $oldNode
     * @param \Pydio\Access\Core\Model\AJXP_Node $newNode
     * @param Boolean $copy
     * @param bool $recursive
     */
    public function updateNodeIndex($oldNode, $newNode = null, $copy = false, $recursive = false)
    {
        require_once("Zend/Search/Lucene.php");
        if (isSet($this->currentIndex)) {
            $oldIndex = $newIndex = $this->currentIndex;
        } else {
            if($oldNode == null){
                $newIndex = $oldIndex = $this->loadIndex($newNode->getContext(), true);
            }else if($newNode == null){
                $oldIndex = $newIndex = $this->loadIndex($oldNode->getContext(), true);
            }else{
                $newId = $newNode->getRepositoryId();
                $oldId = $oldNode->getRepositoryId();
                if($newId == $oldId){
                    $newIndex = $oldIndex = $this->loadIndex($newNode->getContext(), true);
                }else{
                    $newIndex = $this->loadIndex($newNode->getContext(), true);
                    $oldIndex = $this->loadIndex($oldNode->getContext(), true);
                }
            }
        }
        $refNode = ($oldNode != null ? $oldNode : $newNode);
        $this->setDefaultAnalyzer(
            $this->getContextualOption($refNode->getContext(), "QUERY_ANALYSER"),
            intval($this->getContextualOption($refNode->getContext(), "WILDCARD_LIMITATION"))
        );
        if ($oldNode != null && $copy == false) {
            $oldDocId = $this->getIndexedDocumentId($oldIndex, $oldNode);
            if ($oldDocId != null) {
                $oldIndex->delete($oldDocId);
                if ($newNode == null) { // DELETION
                    $childrenHits = $this->getIndexedChildrenDocuments($oldIndex, $oldNode);
                    foreach ($childrenHits as $hit) {
                        $oldIndex->delete($hit->id);
                    }
                }
            }
        }

        if ($newNode != null) {
            // Make sure it does not already exists anyway
            $newDocId = $this->getIndexedDocumentId($newIndex, $newNode);
            if ($newDocId != null) {
                $newIndex->delete($newDocId);
                $childrenHits = $this->getIndexedChildrenDocuments($newIndex, $newNode);
                foreach ($childrenHits as $hit) {
                    $check = $hit->node_path;
                    $newIndex->delete($hit->id);
                }
            }
            $this->createIndexedDocument($newNode, $newIndex);
            if ( $recursive && $oldNode == null && is_dir($newNode->getUrl())) {
                $this->recursiveIndexation($newNode->getUrl());
            }
        }

        if ($oldNode != null && $newNode != null && is_dir($newNode->getUrl()) && ($newIndex == $oldIndex)) { // Copy / Move / Rename
            // Get old node children docs, and update them manually, no need to scan real directory
            $childrenHits = $this->getIndexedChildrenDocuments($oldIndex, $oldNode);
            foreach ($childrenHits as $hit) {
                $oldChildURL = $oldIndex->getDocument($hit->id)->node_url;
                if ($copy == false) {
                    $oldIndex->delete($hit->id);
                }
                $newChildURL = str_replace($oldNode->getUrl(),$newNode->getUrl(),$oldChildURL);
                $this->createIndexedDocument(new AJXP_Node($newChildURL), $oldIndex);
            }
        }

        if (!isSet($this->currentIndex)) {
            $oldIndex->commit();
            if($newIndex != $oldIndex){
                $newIndex->commit();
            }
        }
    }

    /**
     * @param AJXP_Node $ajxpNode
     * @param \Zend_Search_Lucene_Interface $index
     * @throws \Exception
     * @return \Zend_Search_Lucene_Document
     */
    public function createIndexedDocument($ajxpNode, &$index)
    {
        if(!empty($this->metaFields)){
            $ajxpNode->loadNodeInfo(false, false, "all");
        }else{
            $ajxpNode->loadNodeInfo();
        }
        $ext = strtolower(pathinfo($ajxpNode->getLabel(), PATHINFO_EXTENSION));
        $parseContent = $this->indexContent;
        if ($parseContent && $ajxpNode->bytesize > $this->getContextualOption($ajxpNode->getContext(), "PARSE_CONTENT_MAX_SIZE")) {
            $parseContent = false;
        }
        if ($parseContent && in_array($ext, explode(",", $this->getContextualOption($ajxpNode->getContext(), "PARSE_CONTENT_HTML")))) {
            $doc = @\Zend_Search_Lucene_Document_Html::loadHTMLFile($ajxpNode->getUrl());
        } elseif ($parseContent && $ext == "docx" && class_exists("\Zend_Search_Lucene_Document_Docx")) {
            $realFile = $ajxpNode->getRealFile();
            $doc = @\Zend_Search_Lucene_Document_Docx::loadDocxFile($realFile);
        } elseif ($parseContent && $ext == "docx" && class_exists("\Zend_Search_Lucene_Document_Pptx")) {
            $realFile = $ajxpNode->getRealFile();
            $doc = @\Zend_Search_Lucene_Document_Pptx::loadPptxFile($realFile);
        } elseif ($parseContent && $ext == "xlsx" && class_exists("\Zend_Search_Lucene_Document_Xlsx")) {
            $realFile = $ajxpNode->getRealFile();
            $doc = @\Zend_Search_Lucene_Document_Xlsx::loadXlsxFile($realFile);
        } else {
            $doc = new \Zend_Search_Lucene_Document();
        }
        if($doc == null) throw new \Exception("Could not load document");

        $doc->addField(\Zend_Search_Lucene_Field::keyword("node_url", $ajxpNode->getUrl(), "UTF-8"));
        $doc->addField(\Zend_Search_Lucene_Field::keyword("node_path", str_replace("/", "AJXPFAKESEP", $ajxpNode->getPath()), "UTF-8"));
        $basename = basename($ajxpNode->getPath());
        if(class_exists("Normalizer") && !\Normalizer::isNormalized($basename, \Normalizer::FORM_C)){
            $basename = \Normalizer::normalize($basename, \Normalizer::FORM_C);
        }
        $doc->addField(\Zend_Search_Lucene_Field::text("basename", $basename, "UTF-8"));
        $doc->addField(\Zend_Search_Lucene_Field::keyword("ajxp_node", "yes"));
        $doc->addField(\Zend_Search_Lucene_Field::keyword("ajxp_scope", "shared"));
        $doc->addField(\Zend_Search_Lucene_Field::keyword("ajxp_modiftime", date("Ymd", $ajxpNode->ajxp_modiftime)));
        $doc->addField(\Zend_Search_Lucene_Field::keyword("ajxp_bytesize", $ajxpNode->bytesize));
        $ajxpMime = $ajxpNode->ajxp_mime;
        if (empty($ajxpMime)) {
            $doc->addField(\Zend_Search_Lucene_Field::keyword("ajxp_mime", pathinfo($ajxpNode->getLabel(), PATHINFO_EXTENSION)));
        } else {
            $doc->addField(\Zend_Search_Lucene_Field::keyword("ajxp_mime", $ajxpNode->ajxp_mime));
        }


        // Store a cached copy of the metadata
        $serializedMeta = base64_encode(serialize($ajxpNode->metadata));
        $doc->addField(\Zend_Search_Lucene_Field::binary("serialized_metadata", $serializedMeta));
        if (isSet($ajxpNode->indexableMetaKeys["shared"])) {
            foreach ($ajxpNode->indexableMetaKeys["shared"] as $sharedField) {
                if($ajxpNode->$sharedField) $doc->addField(\Zend_Search_Lucene_Field::keyword($sharedField, $ajxpNode->$sharedField));
            }
        }
        foreach ($this->metaFields as $field) {
            if ($ajxpNode->$field != null) {
                $doc->addField(\Zend_Search_Lucene_Field::text("ajxp_meta_$field", $ajxpNode->$field, "UTF-8"));
            }
        }
        if (isSet($ajxpNode->indexableMetaKeys["user"]) && count($ajxpNode->indexableMetaKeys["user"]) && UsersService::usersEnabled() && $ajxpNode->getContext()->hasUser() ) {
            $privateDoc = new \Zend_Search_Lucene_Document();
            $privateDoc->addField(\Zend_Search_Lucene_Field::keyword("node_url", $ajxpNode->getUrl(), "UTF-8"));
            $privateDoc->addField(\Zend_Search_Lucene_Field::keyword("node_path", str_replace("/", "AJXPFAKESEP", $ajxpNode->getPath()), "UTF-8"));

            $privateDoc->addField(\Zend_Search_Lucene_Field::keyword("ajxp_scope", "user"));
            $privateDoc->addField(\Zend_Search_Lucene_Field::keyword("ajxp_user", $ajxpNode->getContext()->getUser()->getId()));
            foreach ($ajxpNode->indexableMetaKeys["user"] as $userField) {
                if ($ajxpNode->$userField) {
                    $privateDoc->addField(\Zend_Search_Lucene_Field::keyword($userField, $ajxpNode->$userField));
                }
            }
            $privateDoc->addField(\Zend_Search_Lucene_Field::binary("serialized_metadata", $serializedMeta));

            $index->addDocument($privateDoc);
        }

        if($parseContent){
            $body = $this->extractIndexableContent($ajxpNode);
            if(!empty($body)) $doc->addField(\Zend_Search_Lucene_Field::unStored("body", $body));
        }
        $index->addDocument($doc);
        return $doc;
    }

    /**
     * @param  \Zend_Search_Lucene_Interface $index
     * @param AJXP_Node $ajxpNode
     * @return Number
     */
    public function getIndexedDocumentId($index, $ajxpNode)
    {
        $term = new \Zend_Search_Lucene_Index_Term($ajxpNode->getUrl(), "node_url");
        $docIds = $index->termDocs($term);
        if(!count($docIds)) return null;
        return $docIds[0];
    }

    /**
     * Find all existing lucene documents based on the parent url
     * @param \Zend_Search_Lucene_Interface $index
     * @param AJXP_Node $ajxpNode
     * @return \Zend_Search_Lucene_Search_QueryHit
     */
    public function getIndexedChildrenDocuments($index, $ajxpNode)
    {
        // Try getting doc by url
        $testQ = str_replace("/", "AJXPFAKESEP", $ajxpNode->getPath());
        $pattern = new \Zend_Search_Lucene_Index_Term($testQ .'*', 'node_path');
        $query = new \Zend_Search_Lucene_Search_Query_Wildcard($pattern);
        $hits = $index->find($query);
        return $hits;
    }

    /**
     * @param ContextInterface $ctx
     * @return string
     */
    protected function getIndexPath(ContextInterface $ctx){
        $mainCacheDir = (defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR);
        if(!is_dir($mainCacheDir."/indexes")) mkdir($mainCacheDir."/indexes",0755,true);
        $iPath = $mainCacheDir."/indexes/index-".$this->buildSpecificId($ctx);
        return $iPath;
    }

    /**
     * @param ContextInterface $ctx
     * @return \Zend_Search_Lucene_Interface
     */
    protected function loadTemporaryIndex(ContextInterface $ctx){
        $indexPath = $this->getIndexPath($ctx);
        $tmpIndexPath = $indexPath."-PYDIO_TMP";
        $this->clearIndexIfExists($tmpIndexPath);
        $this->copyIndex($indexPath, $tmpIndexPath);
        return $this->loadIndex($ctx, true, $tmpIndexPath);
    }

    /**
     * @param ContextInterface $ctx
     * @param int $checkInterval
     * @return bool
     */
    protected function seemsCurrentlyIndexing(ContextInterface $ctx, $checkInterval){
        $tmpIndexPath = $this->getIndexPath($ctx)."-PYDIO_TMP";
        if(is_dir($tmpIndexPath)){
            $mtime = filemtime($tmpIndexPath);
            if(time() - $mtime <= 60 * $checkInterval){
                return true;
            }
        }
        return false;
    }

    /**
     * @param ContextInterface $ctx
     */
    protected function mergeTemporaryIndexToMain(ContextInterface $ctx){
        $indexPath = $this->getIndexPath($ctx);
        $tmpIndexPath = $indexPath."-PYDIO_TMP";
        $this->clearIndexIfExists($indexPath);
        $this->moveIndex($tmpIndexPath, $indexPath);
        $this->clearIndexIfExists($tmpIndexPath);
    }

    /**
     * @param ContextInterface $ctx
     */
    protected function deleteTemporaryIndex(ContextInterface $ctx){
        $indexPath = $this->getIndexPath($ctx);
        $tmpIndexPath = $indexPath."-PYDIO_TMP";
        $this->clearIndexIfExists($tmpIndexPath);
    }

    /**
     * @param $folder
     */
    private function clearIndexIfExists($folder){
        if(!is_dir($folder))return;
        $content = scandir($folder);
        foreach($content as $file){
            if($file == "." || $file == "..") continue;
            unlink($folder.DIRECTORY_SEPARATOR.$file);
        }
        rmdir($folder);
    }

    /**
     * @param $folder1
     * @param $folder2
     */
    private function copyIndex($folder1, $folder2){
        if(!is_dir($folder1))return;
        if(!is_dir($folder2)) mkdir($folder2, 0755);
        $content = scandir($folder1);
        foreach($content as $file){
            if($file == "." || $file == "..") continue;
            copy($folder1.DIRECTORY_SEPARATOR.$file, $folder2.DIRECTORY_SEPARATOR.$file);
        }
    }


    /**
     * @param $folder1
     * @param $folder2
     */
    private function moveIndex($folder1, $folder2){
        if(!is_dir($folder1))return;
        if(!is_dir($folder2)) mkdir($folder2, 0755);
        $content = scandir($folder1);
        foreach($content as $file){
            if($file == "." || $file == "..") continue;
            rename($folder1.DIRECTORY_SEPARATOR.$file, $folder2.DIRECTORY_SEPARATOR.$file);
        }
    }


    /**
     *
     * Load index for context
     * @param ContextInterface $ctx
     * @param bool $create
     * @param null $iPath
     * @throws \Exception
     * @return \Zend_Search_Lucene_Interface the index
     */
    protected function loadIndex(ContextInterface $ctx, $create = true, $iPath = null)
    {
        require_once("Zend/Search/Lucene.php");
        if($iPath == null){
            $iPath = $this->getIndexPath($ctx);
        }
        if (is_dir($iPath)) {
            try{
                $index = \Zend_Search_Lucene::open($iPath);
            }catch (\Zend_Search_Lucene_Exception $se){
                $this->logError(__FUNCTION__, "Error while trying to load lucene index at path ".$iPath."! Maybe a permission issue?");
                throw $se;
            }
        } else {
            if (!$create) {
                $messages = LocaleService::getMessages();
                throw new \Exception($messages["index.lucene.9"]);
            }
            try{
                $index = \Zend_Search_Lucene::create($iPath);
            }catch (\Zend_Search_Lucene_Exception $se){
                $this->logError(__FUNCTION__, "Error while trying to create lucene index at path ".$iPath."! Maybe a permission issue?");
                throw $se;
            }
        }
        return $index;
    }
}
