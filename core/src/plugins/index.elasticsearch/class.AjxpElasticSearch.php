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
 * Autoload function for Elastica classes
 * @param $class
 */
function __autoload_elastica ($class)
{
    $path = AJXP_INSTALL_PATH."/plugins/index.elasticsearch/";
    $class = str_replace('\\', '/', $class);

    if (file_exists($path . $class . '.php')) {
        require_once($path . $class . '.php');
    }
}

spl_autoload_register('__autoload_elastica');


/**
 * Encapsultion of the Elastica component as a plugin
 * @package AjaXplorer_Plugins
 * @subpackage Index
 * @property Elastica\Client $client
 * @property Elastica\Index $currentIndex
 * @property Elastica\Type $currentType
 */
class AjxpElasticSearch extends AJXP_AbstractMetaSource
{
    private $client;
    private $currentIndex;
    private $currentType;
    private $nextId;
    private $lastIdPath;

    private $metaFields = array();
    private $indexContent = false;
    private $specificId = "";
    private $verboseIndexation = false;

    public function init($options)
    {
        parent::init($options);;
        $metaFields = $this->getFilteredOption("index_meta_fields");
        $specKey = $this->getFilteredOption("repository_specific_keywords");
        if (!empty($metaFields)) {
            $this->metaFields = explode(",",$metaFields);
        }

        if (!empty($specKey)) {
            $this->specificId = "-".str_replace(array(",", "/"), array("-", "__"), AJXP_VarsFilter::filter($specKey));
        }

        /* Connexion to Elastica Client with the default parameters */
        $this->client = new Elastica\Client(array("host" => "localhost", "port" => "9200"));

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

    /*protected function setDefaultAnalyzer(){

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

    }*/

    public function applyAction($actionName, $httpVars, $fileVars)
    {
        $messages = ConfService::getMessages();
        $repoId = $this->accessDriver->repository->getId();

        if ($actionName == "search") {
            // TMP
            if (strpos($httpVars["query"], "keyword:") === 0) {
                $parts = explode(":", $httpVars["query"]);
                $this->applyAction("search_by_keyword", array("field" => $parts[1]), array());
                return;
            }

            if ($this->isIndexLocked($repoId)) {
                throw new Exception($messages["index.lucene.6"]);
            }
            try {
                $this->loadIndex($repoId, false);
            } catch (Exception $ex) {
                $this->applyAction("index", array(), array());
                throw new Exception($messages["index.lucene.7"]);
            }

           /*

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
           */
            $this->currentIndex->open();
            $query = $httpVars["query"];
            $fieldQuery = new Elastica\Query\Field();

            //}
            //$this->setDefaultAnalyzer();
            if ($query == "*") {
                $fieldQuery->setField("ajxp_node");
                $fieldQuery->setQueryString("yes");
            } else {
                $fieldQuery->setField("basename");
                $fieldQuery->setQueryString($query);
            }

            /*
                We create this object search because it'll allow us to fetch the number of results we want at once.
                We just have to set some parameters, the query type and the size of the result set.
             */
            $search = new Elastica\Search($this->client);
            $search->addIndex($this->currentIndex)->addType($this->currentType);

            $maxResults = $this->getFilteredOption("MAX_RESULTS");
            $searchOptions = array(
                \Elastica\Search::OPTION_SEARCH_TYPE => \Elastica\Search::OPTION_SEARCH_TYPE_QUERY_THEN_FETCH,
                \Elastica\Search::OPTION_SIZE => $maxResults);

            $result = $search->search($fieldQuery, $searchOptions);
            $total_hits = $result->getTotalHits();
            $hits = $result->getResults();

            AJXP_XMLWriter::header();
            for ($i=0, $count=count($hits); $i < $count; $i++) {
                $hit = $hits[$i];
                $source = $hit->getSource();

                if ($source["serialized_metadata"] != null) {
                    $meta = unserialize(base64_decode($source["serialized_metadata"]));
                    $tmpNode = new AJXP_Node(SystemTextEncoding::fromUTF8($source["node_url"]), $meta);
                } else {
                    $tmpNode = new AJXP_Node(SystemTextEncoding::fromUTF8($source["node_url"]), array());
                    $tmpNode->loadNodeInfo();
                }

                if (!file_exists($tmpNode->getUrl())) {
                    $this->currentType->deleteById($hit->getId());
                    continue;
                }

                $tmpNode->search_score = sprintf("%0.2f", $hit->getScore());
                AJXP_XMLWriter::renderAjxpNode($tmpNode);
            }

            AJXP_XMLWriter::close();
            $this->currentIndex->close();
        } else if ($actionName == "search_by_keyword") {
        /*    require_once("Zend/Search/Lucene.php");
            $scope = "user";

            if ($this->isIndexLocked(ConfService::getRepository()->getId())) {
                throw new Exception($messages["index.lucene.6"]);
            }
            try {
                $this->currentInd =  $this->loadIndex(ConfService::getRepository()->getId(), false);
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
            if ($scope == "user") {
                if (AuthService::usersEnabled() && AuthService::getLoggedUser() == null) {
                    throw new Exception("Cannot find current user");
                }
                $sParts[] = "ajxp_scope:user";
                $sParts[] = "ajxp_user:".AuthService::getLoggedUser()->getId();
            } else {
                $sParts[] = "ajxp_scope:shared";
            }
            $query = implode(" AND ", $sParts);
            $this->logDebug("Query : $query");
            $hits = $this->currentIndex->find($query);

            $commitIndex = false;

            AJXP_XMLWriter::header();
            foreach ($hits as $hit) {
                if ($hit->serialized_metadata!=null) {
                    $meta = unserialize(base64_decode($hit->serialized_metadata));
                    $tmpNode = new AJXP_Node(SystemTextEncoding::fromUTF8($hit->node_url), $meta);
                } else {
                    $tmpNode = new AJXP_Node(SystemTextEncoding::fromUTF8($hit->node_url), array());
                    $tmpNode->loadNodeInfo();
                }
                if (!file_exists($tmpNode->getUrl())) {
                    $this->currentIndex->delete($hit->id);
                    $commitIndex = true;
                    continue;
                }
                $tmpNode->search_score = sprintf("%0.2f", $hit->score);
                AJXP_XMLWriter::renderAjxpNode($tmpNode);
            }
            AJXP_XMLWriter::close();
            if ($commitIndex) {
                $this->currentIndex->commit();
            }*/
        } else if ($actionName == "index") {
            $dir = AJXP_Utils::decodeSecureMagic($httpVars["dir"]);
            if(empty($dir)) $dir = "/";
            $repo = $this->accessDriver->repository;
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
                AJXP_XMLWriter::close();
                return;
            }

            $this->lockIndex($repoId);

            // GIVE BACK THE HAND TO USER
            session_write_close();
            $this->loadIndex($repoId);
            $this->currentIndex->open();
            $this->recursiveIndexation($url);

            if (ConfService::currentContextIsCommandLine() && $this->verboseIndexation) {
                print("Optimizing\n");
                $this->currentIndex->optimize();
            }

            $this->currentIndex->close();
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

    }

    public function recursiveIndexation($url)
    {
        //print("Indexing $url \n");
        $this->logDebug("Indexing content of folder ".$url);
        if (ConfService::currentContextIsCommandLine() && $this->verboseIndexation) {
            print("Indexing content of ".$url."\n");
        }
        @set_time_limit(60);
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
        /*
        if (!isSet($this->currentIndex)) {
            $this->currentIndex =  $this->loadIndex(ConfService::getRepository()->getId());
        }
        Zend_Search_Lucene_Analysis_Analyzer::setDefault( new Zend_Search_Lucene_Analysis_Analyzer_Common_TextNum_CaseInsensitive());

        if (AuthService::usersEnabled() && AuthService::getLoggedUser()!=null) {
            $term = new Zend_Search_Lucene_Index_Term(SystemTextEncoding::toUTF8($node->getUrl()), "node_url");
            $hits = $this->currentIndex->termDocs($term);
            foreach ($hits as $hitId) {
                $hit = $this->currentIndex->getDocument($hitId);
                if ($hit->ajxp_scope == 'shared' || ($hit->ajxp_scope == 'user' && $hit->ajxp_user == AuthService::getLoggedUser()->getId())) {
                    $this->currentIndex->delete($hitId);
                }
            }
        } else {
            $id = $this->getIndexedDocumentId($this->currentInd, $node);
            if($id != null) $this->currentIndex->delete($id);
        }
        $this->createIndexedDocument($node, $this->currentInd);
        */
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
        if (!isSet($this->currentIndex)) {
            if($oldNode == null){
                $this->loadIndex($newNode->getRepositoryId());
            }else{
                $this->loadIndex($oldNode->getRepositoryId());
            }
        }

        //$this->setDefaultAnalyzer();
        if ($oldNode != null && $copy == false) {
            $oldDocId = $this->getIndexedDocumentId($oldNode);
            if ($oldDocId != null) {
                $this->currentType->deleteById($oldDocId);
                $childrenHits = $this->getIndexedChildrenDocuments($newNode);

                if ($childrenHits != null) {
                    $childrenHits = $childrenHits->getResults();

                    foreach ($childrenHits as $hit) {
                        $this->currentType->deleteById($hit->getId());
                    }
                }
            }
        }

        if ($newNode != null) {
            // Make sure it does not already exists anyway
            $newDocId = $this->getIndexedDocumentId($newNode);
            if ($newDocId != null) {
                $this->currentType->deleteById($newDocId);
                $childrenHits = $this->getIndexedChildrenDocuments($newNode);

                if ($childrenHits != null) {
                    $childrenHits = $childrenHits->getResults();

                    foreach ($childrenHits as $hit) {
                        $this->currentType->deleteById($hit->getId());
                    }
                }
            }

            $this->createIndexedDocument($newNode);

            if ($oldNode == null && is_dir($newNode->getUrl())) {
                $this->recursiveIndexation($newNode->getUrl());
            }
        }


        if ($oldNode != null && $newNode != null && is_dir($newNode->getUrl())) { // Copy / Move / Rename
            // Get old node children docs, and update them manually, no need to scan real directory
            $childrenHits = $this->getIndexedChildrenDocuments($oldNode);
            if ($childrenHits != null) {
                $childrenHits = $childrenHits->getResults();

                foreach ($childrenHits as $hit) {
                    $oldChildURL = $this->currentType->getDocument($hit->getId())->get("node_url");
                    if ($copy == false) {
                        $this->currentType->deleteById($hit->getId());
                    }
                    $newChildURL = str_replace(SystemTextEncoding::toUTF8($oldNode->getUrl()),
                        SystemTextEncoding::toUTF8($newNode->getUrl()),
                        $oldChildURL);
                    $newChildURL = SystemTextEncoding::fromUTF8($newChildURL);
                    $this->createIndexedDocument(new AJXP_Node($newChildURL));
                }
            }
        }
    }

    /**
     * @param AJXP_Node $ajxpNode
     * @throws Exception
     */
    public function createIndexedDocument($ajxpNode)
    {
        $ajxpNode->loadNodeInfo();

        /*

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

        */

        $mapping_properties = array();

        /* we construct the array that will contain all the data for the document we create */
        $data = array();

        $data["node_url"] = $ajxpNode->getUrl();
        $data["node_path"] = str_replace("/", "AJXPFAKESEP", $ajxpNode->getPath());
        $data["basename"] = basename($ajxpNode->getPath());
        $data["ajxp_node"] = "yes";
        $data["ajxp_scope"] = "shared";
        $data["serialized_metadata"] = base64_encode(serialize($ajxpNode->metadata));

        if (isSet($ajxpNode->indexableMetaKeys["shared"])) {
            foreach ($ajxpNode->indexableMetaKeys["shared"] as $sharedField) {
                if ($ajxpNode->$sharedField) {
                    $data[$sharedField] = $ajxpNode->$sharedField;
                }
            }
        }

        foreach ($this->metaFields as $field) {
            if ($ajxpNode->$field != null) {
                $data["ajxp_meta_$field"] = $ajxpNode->$field;
            }
        }

        /*
            We want some fields not to be analyzed when they are indexed.
            To achieve this we have to dynamically define a mapping.
            When you define a mapping you have to set the type of data you will put
            in each field and you can define other parameters.
            Here we want some fields not to be analyzed so we just precise the
            property index and set it to "not_analyzed".
        */
        foreach ($data as $key => $value) {
            if ($key == "node_url" || $key = "node_path") {
                $mapping_properties[$key] = array("type" => "string", "index" => "not_analyzed");
            } else {
                $type = gettype($value);

                if ($type != "integer" && $type != "boolean" && $type != "double") {
                    $type = "string";
                }
                $mapping_properties[$key] = array("type" => $type, "index" => "simple");
            }
        }

        $mapping = new Elastica\Type\Mapping();
        $mapping->setType($this->currentType);
        $mapping->setProperties($mapping_properties);
        $mapping->send();

        $doc = new Elastica\Document($this->nextId, $data);
        $this->nextId++;

        /*if (isSet($ajxpNode->indexableMetaKeys["user"]) && count($ajxpNode->indexableMetaKeys["user"]) && AuthService::usersEnabled() && AuthService::getLoggedUser() != null) {
            $privateDoc = new Zend_Search_Lucene_Document();
            $privateDoc->addField(Zend_Search_Lucene_Field::Keyword("node_url", $ajxpNode->getUrl()), SystemTextEncoding::getEncoding());
            $privateDoc->addField(Zend_Search_Lucene_Field::Keyword("node_path", str_replace("/", "AJXPFAKESEP", $ajxpNode->getPath())), SystemTextEncoding::getEncoding());

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

        /*
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
        */

        /* we update the last id in the file */
        $file = fopen($this->lastIdPath, "w");
        fputs($file, $this->nextId-1);
        fclose($file);

        $this->currentType->addDocument($doc);
    }

    /**
     * @param AJXP_Node $ajxpNode
     * @return Number
     */
    public function getIndexedDocumentId($ajxpNode)
    {
        /*
            we used a term query that will check for the exact term (here is the path to a file)
            that can't be duplicate.Thus it will get the right result.
         */
        $query = new Elastica\Query\Term();
        $query->setTerm("node_url", $ajxpNode->getUrl());

        /* returns a result set from the query */
        $results = $this->currentIndex->search($query);

        if($results->getTotalHits() == 0) return null;

        return $results->current()->getId();
    }

    /**
     * Find all existing lucene documents based on the parent url
     * @param AJXP_Node $ajxpNode
     * @return Elastica\ResultSet
     */
    public function getIndexedChildrenDocuments($ajxpNode)
    {
        $testQ = str_replace("/", "AJXPFAKESEP", SystemTextEncoding::toUTF8($ajxpNode->getPath()));

        /* we use a wildcard query to fetch all children documents relatively to their paths */
        $query = new Elastica\Query\Wildcard("node_path", $testQ."*");

        /* returns a result set from the query */
        $results = $this->currentIndex->search($query);

        if($results->getTotalHits() == 0) return null;

        return $results;
    }

    protected function lockIndex($repositoryId)
    {
        $iPath = (defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR)."/indexes";
        if(!is_dir($iPath)) mkdir($iPath,0755, true);
        touch($iPath."/.ajxp_lock-".$repositoryId.$this->specificId);
    }

    protected function isIndexLocked($repositoryId)
    {
        $test = (defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR)."/indexes/.ajxp_lock-".$repositoryId.$this->specificId;
        return file_exists((defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR)."/indexes/.ajxp_lock-".$repositoryId.$this->specificId);
    }

    protected function releaseLock($repositoryId)
    {
        @unlink((defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR)."/indexes/.ajxp_lock-".$repositoryId.$this->specificId);
    }

    /**
     *
     * load the index into the class parameter currentIndex
     * @param Integer $repositoryId
     * @param bool $create
     */
    protected function loadIndex($repositoryId, $create = true)
    {
        $this->currentIndex = $this->client->getIndex($repositoryId);

        /* if the cache directory for the repository index is not created we do create it */
        $iPath = (defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR)."/indexes/".$repositoryId;
        if(!is_dir($iPath)) mkdir($iPath,0755, true);

        if (!$this->currentIndex->exists() && $create) {
            $this->currentIndex->create();
        }

        if ($this->currentType == null) {
            $this->currentType = new Elastica\Type($this->currentIndex, "type_".$repositoryId);
        }

        /* we fetch the last id we used to create a document and set the variable nextId */
        $this->lastIdPath = (defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR)."/indexes/".$repositoryId."/last_id";
        if (file_exists($this->lastIdPath)) {
            $file = fopen($this->lastIdPath, "r");
            $this->nextId = floatval(fgets($file)) + 1;
            fclose($file);
        } else {
            touch($this->lastIdPath);
            $this->nextId = 1;
        }
    }
}
