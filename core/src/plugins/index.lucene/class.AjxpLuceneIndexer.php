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

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Encapsultion of the Zend_Search_Lucene component as a plugin
 * @package AjaXplorer_Plugins
 * @subpackage Index
 */
class AjxpLuceneIndexer extends AbstractSearchEngineIndexer
{
    /**
     * @var Zend_Search_Lucene_Interface
     */
    private $currentIndex;
    private $metaFields = array();
    private $indexContent = false;
    private $verboseIndexation = false;

    public function init($options)
    {
        parent::init($options);
        set_include_path(get_include_path().PATH_SEPARATOR.AJXP_INSTALL_PATH."/plugins/index.lucene");
        $metaFields = $this->getFilteredOption("index_meta_fields");
        if (!empty($metaFields)) {
            $this->metaFields = explode(",",$metaFields);
        }
        $this->indexContent = ($this->getFilteredOption("index_content") == true);
    }

    public function initMeta($accessDriver)
    {
        parent::initMeta($accessDriver);
        if (!empty($this->metaFields) || $this->indexContent) {
            $metaFields = $this->metaFields;
            $el = $this->xPath->query("/indexer")->item(0);
            if ($this->indexContent) {
                if($this->indexContent) $metaFields[] = "ajxp_document_content";
                $data = array("indexed_meta_fields" => $metaFields,
                              "additionnal_meta_columns" => array("ajxp_document_content" => "Content")
                );
                $el->setAttribute("indexed_meta_fields", json_encode($data));
            } else {
                $el->setAttribute("indexed_meta_fields", json_encode($metaFields));
            }
        }
        parent::init($this->options);
    }


    protected function setDefaultAnalyzer()
    {
        switch ($this->getFilteredOption("QUERY_ANALYSER")) {
            case "utf8num_insensitive":
                Zend_Search_Lucene_Analysis_Analyzer::setDefault(new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive());
                break;
            case "utf8num_sensitive":
                Zend_Search_Lucene_Analysis_Analyzer::setDefault(new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num());
                break;
            case "utf8_insensitive":
                Zend_Search_Lucene_Analysis_Analyzer::setDefault(new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8_CaseInsensitive());
                break;
            case "utf8_sensitive":
                Zend_Search_Lucene_Analysis_Analyzer::setDefault(new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8());
                break;
            case "textnum_insensitive":
                Zend_Search_Lucene_Analysis_Analyzer::setDefault(new Zend_Search_Lucene_Analysis_Analyzer_Common_TextNum_CaseInsensitive());
                break;
            case "textnum_sensitive":
                Zend_Search_Lucene_Analysis_Analyzer::setDefault(new Zend_Search_Lucene_Analysis_Analyzer_Common_textNum());
                break;
            case "text_insensitive":
                Zend_Search_Lucene_Analysis_Analyzer::setDefault(new Zend_Search_Lucene_Analysis_Analyzer_Common_Text_CaseInsensitive());
                break;
            case "text_sensitive":
                Zend_Search_Lucene_Analysis_Analyzer::setDefault(new Zend_Search_Lucene_Analysis_Analyzer_Common_Text());
                break;
            default:
                Zend_Search_Lucene_Analysis_Analyzer::setDefault(new Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive());
                break;
        }
        Zend_Search_Lucene_Search_Query_Wildcard::setMinPrefixLength(intval($this->getFilteredOption("WILDCARD_LIMITATION")));

    }

    public function applyAction($actionName, $httpVars, $fileVars)
    {
        $messages = ConfService::getMessages();
        $repoId = $this->accessDriver->repository->getId();

        if ($actionName == "search") {

            // TMP
            if (strpos($httpVars["query"], "keyword:") === 0) {
                $parts = explode(":", $httpVars["query"]);
                $this->applyAction("search_by_keyword", array("field" => $parts[1]), array());
                return null;
            }

            require_once("Zend/Search/Lucene.php");
            try {
                $index =  $this->loadIndex($repoId, false);
            } catch (Exception $ex) {
                AJXP_XMLWriter::header();
                if ($this->seemsCurrentlyIndexing($repoId, 3)){
                    AJXP_XMLWriter::sendMessage($messages["index.lucene.11"], null);
                }else if (ConfService::backgroundActionsSupported() && !ConfService::currentContextIsCommandLine()) {
                    AJXP_Controller::applyActionInBackground($repoId, "index", array());
                    sleep(2);
                    AJXP_XMLWriter::triggerBgAction("check_index_status", array("repository_id" => $repoId), sprintf($messages["index.lucene.8"], "/"), true, 5);
                    AJXP_XMLWriter::sendMessage($messages["index.lucene.7"], null);
                }else{
                    AJXP_XMLWriter::sendMessage($messages["index.lucene.12"], null);
                }
                AJXP_XMLWriter::close();
                return null;
            }
            $textQuery = $httpVars["query"];
            if($this->getFilteredOption("AUTO_WILDCARD") === true && strlen($textQuery) > 0 && ctype_alnum($textQuery)){
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
                $sParts = array();
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
                $index->setDefaultSearchField("basename");
                $query = $this->filterSearchRangesKeywords($textQuery);
            }
            $this->setDefaultAnalyzer();
            if ($query == "*") {
                $index->setDefaultSearchField("ajxp_node");
                $query = "yes";
                $hits = $index->find($query, "node_url", SORT_STRING);
            } else {
                $hits = $index->find($query);
            }
            $commitIndex = false;

            if (isSet($httpVars['return_selection'])) {
                $returnNodes = array();
            } else {
                AJXP_XMLWriter::header();
            }
            $cursor = 0;
            if(isSet($httpVars['limit'])){
                $limit = intval($httpVars['limit']);
            }
            foreach ($hits as $hit) {
                if ($hit->serialized_metadata!=null) {
                    $meta = unserialize(base64_decode($hit->serialized_metadata));
                    $tmpNode = new AJXP_Node(SystemTextEncoding::fromUTF8($hit->node_url), $meta);
                } else {
                    $tmpNode = new AJXP_Node(SystemTextEncoding::fromUTF8($hit->node_url), array());
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
                $tmpNode->search_score = sprintf("%0.2f", $hit->score);
                if (isSet($returnNodes)) {
                    $returnNodes[] = $tmpNode;
                } else {
                    AJXP_XMLWriter::renderAjxpNode($tmpNode);
                }
                $cursor++;
                if(isSet($limit) && $cursor > $limit) break;
            }
            if(!isSet($returnNodes)) AJXP_XMLWriter::close();
            if ($commitIndex) {
                $index->commit();
            }
        } else if ($actionName == "search_by_keyword") {
            require_once("Zend/Search/Lucene.php");
            $scope = "user";

            try {
                $index =  $this->loadIndex($repoId, false);
            } catch (Exception $ex) {
                AJXP_XMLWriter::header();
                if (ConfService::backgroundActionsSupported() && !ConfService::currentContextIsCommandLine()) {
                    AJXP_Controller::applyActionInBackground($repoId, "index", array());
                    AJXP_XMLWriter::triggerBgAction("check_index_status", array("repository_id" => $repoId), sprintf($messages["index.lucene.8"], "/"), true, 2);
                }
                AJXP_XMLWriter::sendMessage($messages["index.lucene.7"], null);
                AJXP_XMLWriter::close();
                return null;
            }
            $sParts = array();
            $searchField = $httpVars["field"];
            if ($searchField == "ajxp_node") {
                $sParts[] = "$searchField:yes";
            } else {
                $sParts[] = "$searchField:true";
            }
            if ($scope == "user" && AuthService::usersEnabled()) {
                if (AuthService::getLoggedUser() == null) {
                    throw new Exception("Cannot find current user");
                }
                $sParts[] = "ajxp_scope:user";
                $sParts[] = "ajxp_user:".AuthService::getLoggedUser()->getId();
            } else {
                $sParts[] = "ajxp_scope:shared";
            }
            $query = implode(" AND ", $sParts);
            $this->logDebug("Query : $query");
            $hits = $index->find($query);

            $commitIndex = false;

            if (isSet($httpVars['return_selection'])) {
                $returnNodes = array();
            } else {
                AJXP_XMLWriter::header();
            }
            foreach ($hits as $hit) {
                if ($hit->serialized_metadata!=null) {
                    $meta = unserialize(base64_decode($hit->serialized_metadata));
                    $tmpNode = new AJXP_Node(SystemTextEncoding::fromUTF8($hit->node_url), $meta);
                } else {
                    $tmpNode = new AJXP_Node(SystemTextEncoding::fromUTF8($hit->node_url), array());
                    $tmpNode->loadNodeInfo();
                }
                if (!file_exists($tmpNode->getUrl())) {
                    $index->delete($hit->id);
                    $commitIndex = true;
                    continue;
                }
                $tmpNode->search_score = sprintf("%0.2f", $hit->score);
                if (isSet($returnNodes)) {
                    $returnNodes[] = $tmpNode;
                } else {
                    AJXP_XMLWriter::renderAjxpNode($tmpNode);
                }
            }
            if(!isSet($returnNodes)) AJXP_XMLWriter::close();
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
        $this->currentIndex = $this->loadTemporaryIndex($parentNode->getRepositoryId());
    }

    /**
     * @param AJXP_Node $parentNode
     */
    public function indexationEnds($parentNode){
        $this->logDebug('INDEX.END', 'Optimizing Index');
        $this->currentIndex->optimize();
        $this->logDebug('INDEX.END', 'Commiting Index');
        $this->currentIndex->commit();
        unset($this->currentIndex);
        $this->logDebug('INDEX.END', 'Merging Temporary in main');
        $this->mergeTemporaryIndexToMain($parentNode->getRepositoryId());
        $this->logDebug('INDEX.END', 'Done');
    }

    /**
     * @param AJXP_Node $node
     */
    public function indexationIndexNode($node){
        $this->updateNodeIndex(null, $node, false, false);
    }

    public function recursiveIndexation($url)
    {
        //print("Indexing $url \n");
        $this->logDebug("Indexing content of folder ".$url);
        if (ConfService::currentContextIsCommandLine() && $this->verboseIndexation) {
            print("Indexing content of ".$url."\n");
        }
        if(!ConfService::currentContextIsCommandLine()) @set_time_limit(60);
        $handle = opendir($url);
        if ($handle !== false) {
            while ( ($child = readdir($handle)) != false) {
                if($child[0] == ".") continue;
                $newUrl = $url."/".$child;
                if (ConfService::currentContextIsCommandLine() && $this->verboseIndexation) {
                    print("Indexing node ".$newUrl."\n");
                }
                $this->logDebug("Indexing Node ".$newUrl);
                try {
                    $newNode = new AJXP_Node($newUrl);
                    $this->updateNodeIndex(null, $newNode, false, true);
                    AJXP_Controller::applyHook("node.index.add", array($newNode));
                } catch (Exception $e) {
                    if (ConfService::currentContextIsCommandLine() && $this->verboseIndexation) {
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
                $index =  $this->loadIndex($node->getRepositoryId(), true, $node->getUser());
            }
            Zend_Search_Lucene_Analysis_Analyzer::setDefault( new Zend_Search_Lucene_Analysis_Analyzer_Common_TextNum_CaseInsensitive());

            if (AuthService::usersEnabled() && AuthService::getLoggedUser()!=null) {
                $term = new Zend_Search_Lucene_Index_Term(SystemTextEncoding::toUTF8($node->getUrl()), "node_url");
                $hits = $index->termDocs($term);
                foreach ($hits as $hitId) {
                    $hit = $index->getDocument($hitId);
                    if ($hit->ajxp_scope == 'shared' || ($hit->ajxp_scope == 'user' && $hit->ajxp_user == AuthService::getLoggedUser()->getId())) {
                        $index->delete($hitId);
                    }
                }
            } else {
                $id = $this->getIndexedDocumentId($index, $node);
                if($id != null) $index->delete($id);
            }
            $this->createIndexedDocument($node, $index);
            $this->logDebug(__FILE__, "Indexation passed ".$node->getUrl());
        } catch (Exception $e){
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
     * @param AJXP_Node $oldNode
     * @param AJXP_Node $newNode
     * @param Boolean $copy
     * @param bool $recursive
     */
    public function updateNodeIndex($oldNode, $newNode = null, $copy = false, $recursive = false)
    {
        require_once("Zend/Search/Lucene.php");
        if (isSet($this->currentIndex)) {
            $index = $this->currentIndex;
        } else {
            if($oldNode == null){
                $index =  $this->loadIndex($newNode->getRepositoryId(), true, $newNode->getUser());
            }else{
                $index = $this->loadIndex($oldNode->getRepositoryId(), true, $oldNode->getUser());
            }
        }
        $this->setDefaultAnalyzer();
        if ($oldNode != null && $copy == false) {
            $oldDocId = $this->getIndexedDocumentId($index, $oldNode);
            if ($oldDocId != null) {
                $index->delete($oldDocId);
                if ($newNode == null) { // DELETION
                    $childrenHits = $this->getIndexedChildrenDocuments($index, $oldNode);
                    foreach ($childrenHits as $hit) {
                        $index->delete($hit->id);
                    }
                }
            }
        }

        if ($newNode != null) {
            // Make sure it does not already exists anyway
            $newDocId = $this->getIndexedDocumentId($index, $newNode);
            if ($newDocId != null) {
                $index->delete($newDocId);
                $childrenHits = $this->getIndexedChildrenDocuments($index, $newNode);
                foreach ($childrenHits as $hit) {
                    $index->delete($hit->id);
                }
            }
            $this->createIndexedDocument($newNode, $index);
            if ( $recursive && $oldNode == null && is_dir($newNode->getUrl())) {
                $this->recursiveIndexation($newNode->getUrl());
            }
        }

        if ($oldNode != null && $newNode != null && is_dir($newNode->getUrl())) { // Copy / Move / Rename
            // Get old node children docs, and update them manually, no need to scan real directory
            $childrenHits = $this->getIndexedChildrenDocuments($index, $oldNode);
            foreach ($childrenHits as $hit) {
                $oldChildURL = $index->getDocument($hit->id)->node_url;
                if ($copy == false) {
                    $index->delete($hit->id);
                }
                $newChildURL = str_replace(SystemTextEncoding::toUTF8($oldNode->getUrl()),
                                           SystemTextEncoding::toUTF8($newNode->getUrl()),
                                           $oldChildURL);
                $newChildURL = SystemTextEncoding::fromUTF8($newChildURL);
                $this->createIndexedDocument(new AJXP_Node($newChildURL), $index);
            }
        }

        if (!isSet($this->currentIndex)) {
            $index->commit();
        }
    }

    /**
     * @param AJXP_Node $ajxpNode
     * @param Zend_Search_Lucene_Interface $index
     * @throws Exception
     * @return Zend_Search_Lucene_Document
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
        if ($parseContent && $ajxpNode->bytesize > $this->getFilteredOption("PARSE_CONTENT_MAX_SIZE")) {
            $parseContent = false;
        }
        if ($parseContent && in_array($ext, explode(",",$this->getFilteredOption("PARSE_CONTENT_HTML")))) {
            $doc = @Zend_Search_Lucene_Document_Html::loadHTMLFile($ajxpNode->getUrl());
        } elseif ($parseContent && $ext == "docx" && class_exists("Zend_Search_Lucene_Document_Docx")) {
            $realFile = call_user_func(array($ajxpNode->wrapperClassName, "getRealFSReference"), $ajxpNode->getUrl());
            $doc = @Zend_Search_Lucene_Document_Docx::loadDocxFile($realFile);
        } elseif ($parseContent && $ext == "docx" && class_exists("Zend_Search_Lucene_Document_Pptx")) {
            $realFile = call_user_func(array($ajxpNode->wrapperClassName, "getRealFSReference"), $ajxpNode->getUrl());
            $doc = @Zend_Search_Lucene_Document_Pptx::loadPptxFile($realFile);
        } elseif ($parseContent && $ext == "xlsx" && class_exists("Zend_Search_Lucene_Document_Xlsx")) {
            $realFile = call_user_func(array($ajxpNode->wrapperClassName, "getRealFSReference"), $ajxpNode->getUrl());
            $doc = @Zend_Search_Lucene_Document_Xlsx::loadXlsxFile($realFile);
        } else {
            $doc = new Zend_Search_Lucene_Document();
        }
        if($doc == null) throw new Exception("Could not load document");

        $doc->addField(Zend_Search_Lucene_Field::Keyword("node_url", $ajxpNode->getUrl()), SystemTextEncoding::getEncoding());
        $doc->addField(Zend_Search_Lucene_Field::Keyword("node_path", str_replace("/", "AJXPFAKESEP", $ajxpNode->getPath())), SystemTextEncoding::getEncoding());
        $doc->addField(Zend_Search_Lucene_Field::Text("basename", basename($ajxpNode->getPath())), SystemTextEncoding::getEncoding());
        $doc->addField(Zend_Search_Lucene_Field::Keyword("ajxp_node", "yes"), SystemTextEncoding::getEncoding());
        $doc->addField(Zend_Search_Lucene_Field::Keyword("ajxp_scope", "shared"));
        $doc->addField(Zend_Search_Lucene_Field::Keyword("ajxp_modiftime", date("Ymd", $ajxpNode->ajxp_modiftime)));
        $doc->addField(Zend_Search_Lucene_Field::Keyword("ajxp_bytesize", $ajxpNode->bytesize));
        $ajxpMime = $ajxpNode->ajxp_mime;
        if (empty($ajxpMime)) {
            $doc->addField(Zend_Search_Lucene_Field::Keyword("ajxp_mime", pathinfo($ajxpNode->getLabel(), PATHINFO_EXTENSION)));
        } else {
            $doc->addField(Zend_Search_Lucene_Field::Keyword("ajxp_mime", $ajxpNode->ajxp_mime));
        }


        // Store a cached copy of the metadata
        $serializedMeta = base64_encode(serialize($ajxpNode->metadata));
        $doc->addField(Zend_Search_Lucene_Field::Binary("serialized_metadata", $serializedMeta));
        if (isSet($ajxpNode->indexableMetaKeys["shared"])) {
            foreach ($ajxpNode->indexableMetaKeys["shared"] as $sharedField) {
                if($ajxpNode->$sharedField) $doc->addField(Zend_search_Lucene_Field::keyword($sharedField, $ajxpNode->$sharedField));
            }
        }
        foreach ($this->metaFields as $field) {
            if ($ajxpNode->$field != null) {
                $doc->addField(Zend_Search_Lucene_Field::Text("ajxp_meta_$field", $ajxpNode->$field), SystemTextEncoding::getEncoding());
            }
        }
        if (isSet($ajxpNode->indexableMetaKeys["user"]) && count($ajxpNode->indexableMetaKeys["user"]) && AuthService::usersEnabled() && AuthService::getLoggedUser() != null) {
            $privateDoc = new Zend_Search_Lucene_Document();
            $privateDoc->addField(Zend_Search_Lucene_Field::Keyword("node_url", $ajxpNode->getUrl(), SystemTextEncoding::getEncoding()));
            $privateDoc->addField(Zend_Search_Lucene_Field::Keyword("node_path", str_replace("/", "AJXPFAKESEP", $ajxpNode->getPath()), SystemTextEncoding::getEncoding()));

            $privateDoc->addField(Zend_Search_Lucene_Field::Keyword("ajxp_scope", "user"));
            $privateDoc->addField(Zend_Search_Lucene_Field::Keyword("ajxp_user", AuthService::getLoggedUser()->getId()));
            foreach ($ajxpNode->indexableMetaKeys["user"] as $userField) {
                if ($ajxpNode->$userField) {
                    $privateDoc->addField(Zend_search_Lucene_Field::keyword($userField, $ajxpNode->$userField));
                }
            }
            $privateDoc->addField(Zend_Search_Lucene_Field::Binary("serialized_metadata", $serializedMeta));

            $index->addDocument($privateDoc);
        }

        if($parseContent){
            $body = $this->extractIndexableContent($ajxpNode);
            if(!empty($body)) $doc->addField(Zend_Search_Lucene_Field::unStored("body", $body));
        }
        $index->addDocument($doc);
        return $doc;
    }

    /**
     * @param  Zend_Search_Lucene_Interface $index
     * @param AJXP_Node $ajxpNode
     * @return Number
     */
    public function getIndexedDocumentId($index, $ajxpNode)
    {
        $term = new Zend_Search_Lucene_Index_Term(SystemTextEncoding::toUTF8($ajxpNode->getUrl()), "node_url");
        $docIds = $index->termDocs($term);
        if(!count($docIds)) return null;
        return $docIds[0];
    }

    /**
     * Find all existing lucene documents based on the parent url
     * @param Zend_Search_Lucene_Interface $index
     * @param AJXP_Node $ajxpNode
     * @return Zend_Search_Lucene_Search_QueryHit
     */
    public function getIndexedChildrenDocuments($index, $ajxpNode)
    {
        // Try getting doc by url
        $testQ = str_replace("/", "AJXPFAKESEP", SystemTextEncoding::toUTF8($ajxpNode->getPath()));
        $pattern = new Zend_Search_Lucene_Index_Term($testQ .'*', 'node_path');
        $query = new Zend_Search_Lucene_Search_Query_Wildcard($pattern);
        $hits = $index->find($query);
        return $hits;
    }

    /**
     * @param $repositoryId
     * @param null $resolveUserId
     * @return string
     */
    protected function getIndexPath($repositoryId, $resolveUserId = null){
        $mainCacheDir = (defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR);
        if(!is_dir($mainCacheDir."/indexes")) mkdir($mainCacheDir."/indexes",0755,true);
        $iPath = $mainCacheDir."/indexes/index-".$this->buildSpecificId($repositoryId, $resolveUserId);
        return $iPath;
    }

    /**
     * @param $repositoryId
     * @return Zend_Search_Lucene_Interface
     */
    protected function loadTemporaryIndex($repositoryId){
        $indexPath = $this->getIndexPath($repositoryId);
        $tmpIndexPath = $indexPath."-PYDIO_TMP";
        $this->clearIndexIfExists($tmpIndexPath);
        $this->copyIndex($indexPath, $tmpIndexPath);
        return $this->loadIndex($repositoryId, true, null, $tmpIndexPath);
    }

    /**
     * @param String $repositoryId
     * @param int $checkInterval
     * @return bool
     */
    protected function seemsCurrentlyIndexing($repositoryId, $checkInterval){
        $tmpIndexPath = $this->getIndexPath($repositoryId)."-PYDIO_TMP";
        if(is_dir($tmpIndexPath)){
            $mtime = filemtime($tmpIndexPath);
            if(time() - $mtime <= 60 * $checkInterval){
                return true;
            }
        }
        return false;
    }

    /**
     * @param $repositoryId
     */
    protected function mergeTemporaryIndexToMain($repositoryId){
        $indexPath = $this->getIndexPath($repositoryId);
        $tmpIndexPath = $indexPath."-PYDIO_TMP";
        $this->clearIndexIfExists($indexPath);
        $this->moveIndex($tmpIndexPath, $indexPath);
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
     * Enter description here ...
     * @param Integer $repositoryId
     * @param bool $create
     * @param null $resolveUserId
     * @param null $iPath
     * @throws Exception
     * @return Zend_Search_Lucene_Interface the index
     */
    protected function loadIndex($repositoryId, $create = true, $resolveUserId = null, $iPath = null)
    {
        require_once("Zend/Search/Lucene.php");
        if($iPath == null){
            $iPath = $this->getIndexPath($repositoryId, $resolveUserId);
        }
        if (is_dir($iPath)) {
            try{
                $index = Zend_Search_Lucene::open($iPath);
            }catch (Zend_Search_Lucene_Exception $se){
                $this->logError(__FUNCTION__, "Error while trying to load lucene index at path ".$iPath."! Maybe a permission issue?");
                throw $se;
            }
        } else {
            if (!$create) {
                $messages = ConfService::getMessages();
                throw new Exception($messages["index.lucene.9"]);
            }
            try{
                $index = Zend_Search_Lucene::create($iPath);
            }catch (Zend_Search_Lucene_Exception $se){
                $this->logError(__FUNCTION__, "Error while trying to create lucene index at path ".$iPath."! Maybe a permission issue?");
                throw $se;
            }
        }
        return $index;
    }
}
