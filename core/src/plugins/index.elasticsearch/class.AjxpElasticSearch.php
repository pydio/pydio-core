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
class AjxpElasticSearch extends AbstractSearchEngineIndexer
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
        parent::init($options);
        $metaFields = $this->getFilteredOption("index_meta_fields");
        $specKey = $this->getFilteredOption("repository_specific_keywords");
        if (!empty($metaFields)) {
            $this->metaFields = explode(",",$metaFields);
        }

        if (!empty($specKey)) {
            $this->specificId = "-".str_replace(array(",", "/"), array("-", "__"), AJXP_VarsFilter::filter($specKey));
        }

        /* Connexion to Elastica Client with the default parameters */
        $this->client = new Elastica\Client(array(
            "host" => $this->getFilteredOption("ELASTICSEARCH_HOST"),
            "port" => $this->getFilteredOption("ELASTICSEARCH_PORT"))
        );

        $this->indexContent = ($this->getFilteredOption("index_content") == true);
    }

    public function initMeta($accessDriver)
    {
        $this->accessDriver = $accessDriver;
        if (!empty($this->metaFields) || $this->indexContent) {
            $metaFields = $this->metaFields;
            $el = $this->getXPath()->query("/indexer")->item(0);
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

    /**
     * @param AJXP_Node $node
     */
    public function indexationIndexNode($node){
        $this->updateNodeIndex(null, $node, false, false);
    }


    /**
     * @param AJXP_Node $parentNode
     */
    public function indexationStarts($parentNode){
        $this->loadIndex($parentNode->getRepositoryId(), true, $parentNode->getUser());
    }

    /**
     * @param AJXP_Node $parentNode
     */
    public function indexationEnds($parentNode){
        if($this->currentIndex) {
            $this->currentIndex->optimize();
        }
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
                return;
            }

            try {
                $this->loadIndex($repoId, false);
            } catch (Exception $ex) {
                $this->applyAction("index", array(), array());
                throw new Exception($messages["index.lucene.7"]);
            }

            $textQuery = $httpVars["query"];
            if($this->getFilteredOption("AUTO_WILDCARD") === true && strlen($textQuery) > 0 && ctype_alnum($textQuery)){
                if($textQuery[0] == '"' && $textQuery[strlen($textQuery)-1] == '"'){
                    $textQuery = substr($textQuery, 1, -1);
                }else if($textQuery[strlen($textQuery)-1] != "*" ){
                    $textQuery.="*";
                }
            }


            $this->currentIndex->open();
            $fieldQuery = new Elastica\Query\QueryString();
            $fieldQuery->setAllowLeadingWildcard(false);
            $fieldQuery->setFuzzyMinSim(0.8);

            if($textQuery == "*"){

                $fields = array("ajxp_node");
                $fieldQuery->setQuery("yes");
                $fieldQuery->setFields($fields);

            }else if(strpos($textQuery, ":") !== false){

                // USE LUCENE DSL DIRECTLY (key1:value1 AND key2:value2...)
                $textQuery = str_replace("ajxp_meta_ajxp_document_content:","body:", $textQuery);
                $textQuery = $this->filterSearchRangesKeywords($textQuery);
                $fieldQuery->setQuery($textQuery);

            } else{

                $fields = array("basename","ajxp_meta_*", "node_*","body");
                $fieldQuery->setQuery($textQuery);
                $fieldQuery->setFields($fields);

            }

            /*
            TODO : READAPT QUERY WITH EACH FIELD
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

            //}
            /*
                We create this object search because it'll allow us to fetch the number of results we want at once.
                We just have to set some parameters, the query type and the size of the result set.
             */
            $search = new Elastica\Search($this->client);
            $search->addIndex($this->currentIndex)->addType($this->currentType);

            $maxResults = $this->getFilteredOption("MAX_RESULTS");
            if(isSet($httpVars['limit'])){
                $maxResults = intval($httpVars['limit']);
            }
            $searchOptions = array(
                \Elastica\Search::OPTION_SEARCH_TYPE => \Elastica\Search::OPTION_SEARCH_TYPE_QUERY_THEN_FETCH,
                \Elastica\Search::OPTION_SIZE => $maxResults);

            $this->logDebug(__FUNCTION__,"Executing query: ", $textQuery);
            $fullQuery = new Elastica\Query();
            $fullQuery->setQuery($fieldQuery);

            $qb = new Elastica\QueryBuilder();
            $fullQuery = new Elastica\Query();
            $fullQuery->setQuery(
                $qb->query()->filtered(
                    $fieldQuery,
                    $qb->filter()->bool()
                        ->addMust(new Elastica\Filter\Term(array("ajxp_scope" => "shared")))
                )
            );


            $result = $search->search($fullQuery, $searchOptions);
            $this->logDebug(__FUNCTION__,"Search finished. ");
            $hits = $result->getResults();

            AJXP_XMLWriter::header();
            foreach ($hits as $hit) {
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

        } else if ($actionName == "search_by_keyword") {

            $scope = "user";
            try {
                $this->loadIndex($repoId, false);
            } catch (Exception $ex) {
                throw new Exception($messages["index.lucene.7"]);
            }
            $sParts = array();
            $searchField = $httpVars["field"];

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


            $fieldQuery = new Elastica\Query\QueryString();
            $fields = array($searchField);
            $fieldQuery->setQuery($searchField == "ajxp_node"?"yes":"true");

            $fieldQuery->setFields($fields);
            $fieldQuery->setAllowLeadingWildcard(false);
            $fieldQuery->setFuzzyMinSim(0.8);


            $search = new Elastica\Search($this->client);
            $search->addIndex($this->currentIndex)->addType($this->currentType);

            $maxResults = $this->getFilteredOption("MAX_RESULTS");
            if(isSet($httpVars['limit'])){
                $maxResults = intval($httpVars['limit']);
            }
            $searchOptions = array(
                \Elastica\Search::OPTION_SEARCH_TYPE => \Elastica\Search::OPTION_SEARCH_TYPE_QUERY_THEN_FETCH,
                \Elastica\Search::OPTION_SIZE => $maxResults);

            // ADD SCOPE FILTER
            $term = new Elastica\Filter\Term();
            $term->setTerm("ajxp_scope", "user");

            $qb = new Elastica\QueryBuilder();
            $fullQuery = new Elastica\Query();
            $fullQuery->setQuery(
                $qb->query()->filtered(
                    $fieldQuery,
                    $qb->filter()->bool()
                        ->addMust(new Elastica\Filter\Term(array("ajxp_scope" => "user")))
                        ->addMust(new Elastica\Filter\Term(array("user" => AuthService::getLoggedUser()->getId())))
                )
            );

            $result = $search->search($fullQuery, $searchOptions);
            $this->logDebug(__FUNCTION__,"Search finished. ");
            $hits = $result->getResults();

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
                    $this->currentType->deleteById($hit->id);
                    continue;
                }
                $tmpNode->search_score = sprintf("%0.2f", $hit->score);
                AJXP_XMLWriter::renderAjxpNode($tmpNode);
            }
            AJXP_XMLWriter::close();
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
        $this->loadIndex($node->getRepositoryId(), true, $node->getUser());
        if (AuthService::usersEnabled() && AuthService::getLoggedUser()!=null) {

            $query = new Elastica\Query\Term();
            $query->setTerm("node_url", $node->getUrl());
            $results = $this->currentType->search($query);
            $hits = $results->getResults();
            foreach ($hits as $hit) {
                $source = $hit->getSource();
                if ($source['ajxp_scope'] == 'shared' || ($source['ajxp_scope'] == 'user' && $source['ajxp_user'] == AuthService::getLoggedUser()->getId())) {
                    $this->currentType->deleteById($hit->getId());
                }
            }
        } else {
            $id = $this->getIndexedDocumentId($node);
            if($id != null) $this->currentType->deleteById($id);
        }
        $this->createIndexedDocument($node);

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
        if($oldNode == null){
            $this->loadIndex($newNode->getRepositoryId(), true, $newNode->getUser());
        }else{
            $this->loadIndex($oldNode->getRepositoryId(), true, $oldNode->getUser());
        }

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
                try{
                    $this->currentType->deleteById($newDocId);
                }catch (Elastica\Exception\NotFoundException $eEx){
                    $this->logError(__FUNCTION__, "Trying to delete a non existing document");
                }
                $childrenHits = $this->getIndexedChildrenDocuments($newNode);
                if ($childrenHits != null) {
                    $childrenHits = $childrenHits->getResults();

                    foreach ($childrenHits as $hit) {
                        try{
                            $this->currentType->deleteById($hit->getId());
                        }catch (Elastica\Exception\NotFoundException $eEx){
                            $this->logError(__FUNCTION__, "Trying to delete a non existing document");
                        }
                    }
                }
            }

            $this->createIndexedDocument($newNode);

            if ($recursive && $oldNode == null && is_dir($newNode->getUrl())) {
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

        $parseContent = $this->indexContent;
        if ($parseContent && $ajxpNode->bytesize > $this->getFilteredOption("PARSE_CONTENT_MAX_SIZE")) {
            $parseContent = false;
        }

        $data = array();
        $data["node_url"] = $ajxpNode->getUrl();
        $data["node_path"] = str_replace("/", "AJXPFAKESEP", $ajxpNode->getPath());
        $data["basename"] = basename($ajxpNode->getPath());
        $data["ajxp_node"] = "yes";
        $data["ajxp_scope"] = "shared";
        $data["serialized_metadata"] = base64_encode(serialize($ajxpNode->metadata));
        $data["ajxp_modiftime"] = date("Ymd", $ajxpNode->ajxp_modiftime);
        $data["ajxp_bytesize"] = $ajxpNode->bytesize;
        $ajxpMime = $ajxpNode->ajxp_mime;
        if (empty($ajxpMime)) {
            $data["ajxp_mime"] = pathinfo($ajxpNode->getLabel(), PATHINFO_EXTENSION);
        } else {
            $data["ajxp_mime"] = $ajxpNode->ajxp_mime;
        }

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
        if($parseContent){
            $body = $this->extractIndexableContent($ajxpNode);
            if(!empty($body)){
                $data["body"] = $body;
            }
        }

        $mapping = new Elastica\Type\Mapping();
        $mapping->setType($this->currentType);
        $mapping->setProperties($this->dataToMappingProperties($data));
        $mapping->send();
        $doc = new Elastica\Document($this->nextId, $data);
        $this->currentType->addDocument($doc);
        $this->nextId++;

        if (isSet($ajxpNode->indexableMetaKeys["user"]) && count($ajxpNode->indexableMetaKeys["user"]) && AuthService::usersEnabled() && AuthService::getLoggedUser() != null) {

            $userData = array(
                "ajxp_scope" => "user",
                "user"      => AuthService::getLoggedUser()->getId(),
                "serialized_metadata" => $data["serialized_metadata"],
                "node_url"  => $data["node_url"],
                "node_path"  => $data["node_path"]
            );
            $userData["ajxp_user"] = AuthService::getLoggedUser()->getId();
            foreach ($ajxpNode->indexableMetaKeys["user"] as $userField) {
                if ($ajxpNode->$userField) {
                    $userData[$userField] = $ajxpNode->$userField;
                }
            }
            $mapping = new Elastica\Type\Mapping();
            $mapping->setType($this->currentType);
            $mapping->setProperties($this->dataToMappingProperties($userData));
            $mapping->send();
            $doc = new Elastica\Document($this->nextId, $userData);
            $this->currentType->addDocument($doc);
            $this->nextId++;
        }

        /* we update the last id in the file */
        $file = fopen($this->lastIdPath, "w");
        fputs($file, $this->nextId-1);
        fclose($file);

    }

    protected function dataToMappingProperties($data){

        $mapping_properties = array();
        foreach ($data as $key => $value) {
            if ($key == "node_url" || $key == "node_path") {
                $mapping_properties[$key] = array("type" => "string", "index" => "not_analyzed");
            } else if($key == "serialized_metadata"){
                $mapping_properties[$key] = array("type" => "string" /*, "index" => "no" */);
            } else if ($key == "ajxp_bytesize"){
                $mapping_properties[$key] = array("type" => "long");
            } else {
                $type = gettype($value);
                if ($type != "integer" && $type != "boolean" && $type != "double") {
                    $type = "string";
                }
                $mapping_properties[$key] = array("type" => $type);
            }
        }
        return $mapping_properties;

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
        $testQ = str_replace("/", "AJXPFAKESEP", SystemTextEncoding::toUTF8($ajxpNode->getPath()."/"));

        /* we use a wildcard query to fetch all children documents relatively to their paths */
        $query = new Elastica\Query\Wildcard("node_path", $testQ."*");

        /* returns a result set from the query */
        $results = $this->currentIndex->search($query);

        if($results->getTotalHits() == 0) return null;

        return $results;
    }

    /**
     *
     * load the index into the class parameter currentIndex
     * @param Integer $repositoryId
     * @param bool $create
     * @param null $resolveUserId
     */
    protected function loadIndex($repositoryId, $create = true, $resolveUserId = null)
    {
        $specificId = $this->buildSpecificId($repositoryId, $resolveUserId);

        $this->currentIndex = $this->client->getIndex($specificId);

        /* if the cache directory for the repository index is not created we do create it */
        $iPath = (defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR)."/indexes/".$specificId;
        if(!is_dir($iPath)) mkdir($iPath,0755, true);

        if ($create && !$this->currentIndex->exists()) {
            $this->currentIndex->create();
        }

        $this->currentType = new Elastica\Type($this->currentIndex, "type_".$specificId);

        /* we fetch the last id we used to create a document and set the variable nextId */
        $this->lastIdPath = (defined('AJXP_SHARED_CACHE_DIR')?AJXP_SHARED_CACHE_DIR:AJXP_CACHE_DIR)."/indexes/".$specificId."/last_id";
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