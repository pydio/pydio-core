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
class AjxpLuceneIndexer extends AJXP_Plugin
{
    private $currentIndex;
    private $accessDriver;
    private $metaFields = array();
    private $indexContent = false;
    private $specificId = "";
    private $verboseIndexation = false;

    public function init($options)
    {
        parent::init($options);
        set_include_path(get_include_path().PATH_SEPARATOR.AJXP_INSTALL_PATH."/plugins/index.lucene");
        $metaFields = $this->getFilteredOption("index_meta_fields");
        $specKey = $this->getFilteredOption("repository_specific_keywords");
        if (!empty($metaFields)) {
            $this->metaFields = explode(",",$metaFields);
        }
        if (!empty($specKey)) {
            $this->specificId = "-".str_replace(array(",", "/"), array("-", "__"), AJXP_VarsFilter::filter($specKey));
        }
        $this->indexContent = ($this->getFilteredOption("index_content") == true);
    }

    public function initMeta($accessDriver)
    {
        $this->accessDriver = $accessDriver;
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

    protected function filterSearchRangesKeywords($query)
    {
        if (strpos($query, "AJXP_SEARCH_RANGE_TODAY") !== false) {
            $t1 = date("Ymd");
            $t2 = date("Ymd");
            $query = str_replace("AJXP_SEARCH_RANGE_TODAY", "[$t1 TO  $t2]", $query);
        } else if (strpos($query, "AJXP_SEARCH_RANGE_YESTERDAY") !== false) {
            $t1 = date("Ymd", mktime(0,0,0,date('m'), date('d')-1, date('Y')));
            $t2 = date("Ymd", mktime(0,0,0,date('m'), date('d')-1, date('Y')));
            $query = str_replace("AJXP_SEARCH_RANGE_YESTERDAY", "[$t1 TO $t2]", $query);
        } else if (strpos($query, "AJXP_SEARCH_RANGE_LAST_WEEK") !== false) {
            $t1 = date("Ymd", mktime(0,0,0,date('m'), date('d')-7, date('Y')));
            $t2 = date("Ymd", mktime(0,0,0,date('m'), date('d'), date('Y')));
            $query = str_replace("AJXP_SEARCH_RANGE_LAST_WEEK", "[$t1 TO $t2]", $query);
        } else if (strpos($query, "AJXP_SEARCH_RANGE_LAST_MONTH") !== false) {
            $t1 = date("Ymd", mktime(0,0,0,date('m')-1, date('d'), date('Y')));
            $t2 = date("Ymd", mktime(0,0,0,date('m'), date('d'), date('Y')));
            $query = str_replace("AJXP_SEARCH_RANGE_LAST_MONTH", "[$t1 TO $t2]", $query);
        } else if (strpos($query, "AJXP_SEARCH_RANGE_LAST_YEAR") !== false) {
            $t1 = date("Ymd", mktime(0,0,0,date('m'), date('d'), date('Y')-1));
            $t2 = date("Ymd", mktime(0,0,0,date('m'), date('d'), date('Y')));
            $query = str_replace("AJXP_SEARCH_RANGE_LAST_YEAR", "[$t1 TO $t2]", $query);
        }
        return $query;
    }

    public function applyAction($actionName, $httpVars, $fileVars)
    {
        $messages = ConfService::getMessages();
        if ($actionName == "search") {

            // TMP
            if (strpos($httpVars["query"], "keyword:") === 0) {
                $parts = explode(":", $httpVars["query"]);
                $this->applyAction("search_by_keyword", array("field" => $parts[1]), array());
                return;
            }

            require_once("Zend/Search/Lucene.php");
            if ($this->isIndexLocked(ConfService::getRepository()->getId())) {
                throw new Exception($messages["index.lucene.6"]);
            }
            try {
                $index =  $this->loadIndex(ConfService::getRepository()->getId(), false);
            } catch (Exception $ex) {
                $this->applyAction("index", array("inner_apply" => "true"), array());
                throw new Exception($messages["index.lucene.7"]);
            }
            if ((isSet($this->metaFields) || $this->indexContent) && isSet($httpVars["fields"])) {
                $sParts = array();
                foreach (explode(",",$httpVars["fields"]) as $searchField) {
                    if ($searchField == "filename") {
                        $sParts[] = "basename:".$httpVars["query"];
                    } else if (in_array($searchField, $this->metaFields)) {
                        $sParts[] = "ajxp_meta_".$searchField.":".$httpVars["query"];
                    } else if ($searchField == "ajxp_document_content") {
                        $sParts[] = "title:".$httpVars["query"];
                        $sParts[] = "body:".$httpVars["query"];
                        $sParts[] = "keywords:".$httpVars["query"];
                    }
                }
                $query = implode(" OR ", $sParts);
                $query = "ajxp_scope:shared AND ($query)";
                $this->logDebug("Query : $query");
            } else {
                $index->setDefaultSearchField("basename");
                $query = $this->filterSearchRangesKeywords($httpVars["query"]);
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
        } else if ($actionName == "search_by_keyword") {
            require_once("Zend/Search/Lucene.php");
            $scope = "user";

            if ($this->isIndexLocked(ConfService::getRepository()->getId())) {
                throw new Exception($messages["index.lucene.6"]);
            }
            try {
                $index =  $this->loadIndex(ConfService::getRepository()->getId(), false);
            } catch (Exception $ex) {
                $this->applyAction("index", array(), array());
                throw new Exception($messages["index.lucene.7"]);
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
        } else if ($actionName == "index") {
            $dir = AJXP_Utils::decodeSecureMagic($httpVars["dir"]);
            if(empty($dir)) $dir = "/";
            $repo = ConfService::getRepository();
            if ($this->isIndexLocked($repo->getId())) {
                throw new Exception($messages["index.lucene.6"]);
            }
            $accessType = $repo->getAccessType();
            $accessPlug = AJXP_PluginsService::getInstance()->getPluginByTypeName("access", $accessType);
            $stData = $accessPlug->detectStreamWrapper(true);
            $repoId = $repo->getId();
            $url = $stData["protocol"]."://".$repoId.$dir;
            if (isSet($httpVars["verbose"]) && $httpVars["verbose"] == "true") {
                $this->verboseIndexation = true;
            }

            if (ConfService::backgroundActionsSupported() && !ConfService::currentContextIsCommandLine()) {
                AJXP_Controller::applyActionInBackground($repoId, "index", $httpVars);
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::triggerBgAction("check_lock", array("repository_id" => $repoId), sprintf($messages["index.lucene.8"], $dir), true, 2);
                if(!isSet($httpVars["inner_apply"])){
                    AJXP_XMLWriter::close();
                }
                return;
            }

            $this->lockIndex($repoId);

            // GIVE BACK THE HAND TO USER
            session_write_close();
            $this->currentIndex = $this->loadIndex($repoId);
            $this->recursiveIndexation($url);
            if (ConfService::currentContextIsCommandLine() && $this->verboseIndexation) {
                print("Optimizing\n");
            }
            $this->currentIndex->optimize();
            if (ConfService::currentContextIsCommandLine() && $this->verboseIndexation) {
                print("Commiting Index\n");
            }
            $this->currentIndex->commit();
            $this->currentIndex = null;
            $this->releaseLock($repoId);
        } else if ($actionName == "check_lock") {
            $repoId = $httpVars["repository_id"];
            if ($this->isIndexLocked($repoId)) {
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::triggerBgAction("check_lock", array("repository_id" => $repoId), $messages["index.lucene.10"], true, 3);
                AJXP_XMLWriter::close();
            } else {
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::triggerBgAction("info_message", array(), $messages["index.lucene.5"], true, 5);
                AJXP_XMLWriter::close();
            }
        }
        if(isSet($returnNodes)) return $returnNodes;

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
                $this->logDebug("Indexing Node ".$newUrl);
                try {
                    $this->updateNodeIndex(null, new AJXP_Node($newUrl));
                } catch (Exception $e) {
                    $this->logDebug("Error Indexing Node ".$newUrl." (".$e->getMessage().")");
                }
            }
            closedir($handle);
        } else {
            $this->logDebug("Cannot open $url!!");
        }
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
        if (isSet($this->currentIndex)) {
            $index = $this->currentIndex;
        } else {
            $index =  $this->loadIndex(ConfService::getRepository()->getId());
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
     */
    public function updateNodeIndex($oldNode, $newNode = null, $copy = false)
    {
        require_once("Zend/Search/Lucene.php");
        if (isSet($this->currentIndex)) {
            $index = $this->currentIndex;
        } else {
               $index =  $this->loadIndex(ConfService::getRepository()->getId());
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
            $doc = $this->createIndexedDocument($newNode, $index);
            //$index->addDocument($doc);
            if ($oldNode == null && is_dir($newNode->getUrl())) {
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
        $ajxpNode->loadNodeInfo();
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
        if ($parseContent && in_array($ext, explode(",",$this->getFilteredOption("PARSE_CONTENT_TXT")))) {
            $doc->addField(Zend_Search_Lucene_Field::unStored("body", file_get_contents($ajxpNode->getUrl())));
        }
        $unoconv = $this->getFilteredOption("UNOCONV");
        if ($parseContent && !empty($unoconv) && in_array($ext, array("doc", "odt", "xls", "ods"))) {
            $targetExt = "txt";
            $pipe = false;
            if (in_array($ext, array("xls", "ods"))) {
                $targetExt = "csv";
            } else if (in_array($ext, array("odp", "ppt"))) {
                $targetExt = "pdf";
                $pipe = true;
            }
            $realFile = call_user_func(array($ajxpNode->wrapperClassName, "getRealFSReference"), $ajxpNode->getUrl());
            $unoconv = "HOME=".AJXP_Utils::getAjxpTmpDir()." ".$unoconv." --stdout -f $targetExt ".escapeshellarg($realFile);
            if ($pipe) {
                $newTarget = str_replace(".$ext", ".pdf", $realFile);
                $unoconv.= " > $newTarget";
                register_shutdown_function("unlink", $newTarget);
            }
            $output = array();
            exec($unoconv, $output, $return);
            if (!$pipe) {
                $out = implode("\n", $output);
                $enc = 'ISO-8859-1';
                $asciiString = iconv($enc, 'ASCII//TRANSLIT//IGNORE', $out);
                   $doc->addField(Zend_Search_Lucene_Field::unStored("body", $asciiString));
            } else {
                $ext = "pdf";
            }
        }
        $pdftotext = $this->getFilteredOption("PDFTOTEXT");
        if ($parseContent && !empty($pdftotext) && in_array($ext, array("pdf"))) {
            $realFile = call_user_func(array($ajxpNode->wrapperClassName, "getRealFSReference"), $ajxpNode->getUrl());
            if ($pipe && isset($newTarget) && is_file($newTarget)) {
                $realFile = $newTarget;
            }
            $cmd = $pdftotext." ".escapeshellarg($realFile)." -";
            $output = array();
            exec($cmd, $output, $return);
            $out = implode("\n", $output);
            $enc = 'UTF8';
            $asciiString = iconv($enc, 'ASCII//TRANSLIT//IGNORE', $out);
               $doc->addField(Zend_Search_Lucene_Field::unStored("body", $asciiString));
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

    protected function lockIndex($repositoryId)
    {
        $iPath = (defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR)."/indexes";
        if(!is_dir($iPath)) mkdir($iPath,0755, true);
        touch($iPath."/.ajxp_lock-".$repositoryId.$this->specificId);
    }

    protected function isIndexLocked($repositoryId)
    {
        return file_exists((defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR)."/indexes/.ajxp_lock-".$repositoryId.$this->specificId);
    }

    protected function releaseLock($repositoryId)
    {
        @unlink((defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR)."/indexes/.ajxp_lock-".$repositoryId.$this->specificId);
    }

    /**
     *
     * Enter description here ...
     * @param Integer $repositoryId
     * @param bool $create
     * @return Zend_Search_Lucene_Interface the index
     */
    protected function loadIndex($repositoryId, $create = true)
    {
        require_once("Zend/Search/Lucene.php");
        $mainCacheDir = (defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR);
        $iPath = $mainCacheDir."/indexes/index-$repositoryId".$this->specificId;
        if(!is_dir($mainCacheDir."/indexes")) mkdir($mainCacheDir."/indexes",0755,true);
        if (is_dir($iPath)) {
            $index = Zend_Search_Lucene::open($iPath);
        } else {
            if (!$create) {
                $messages = ConfService::getMessages();
                throw new Exception($messages["index.lucene.9"]);
            }
            $index = Zend_Search_Lucene::create($iPath);
        }
        return $index;
    }
}
