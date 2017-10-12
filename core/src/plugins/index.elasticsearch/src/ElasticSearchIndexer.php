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

namespace Pydio\Access\Indexer\Implementation;

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Indexer\Core\AbstractSearchEngineIndexer;
use Pydio\Core\Http\Message\UserMessage;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\VarsFilter;
use \Elastica;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Encapsultion of the Elastica component as a plugin
 * @package AjaXplorer_Plugins
 * @subpackage Index
 * @property Elastica\Client $client
 * @property Elastica\Index $currentIndex
 * @property Elastica\Type $currentType
 */
class ElasticSearchIndexer extends AbstractSearchEngineIndexer
{
    private $client;
    private $currentIndex;
    private $currentType;
    private $nextId;
    private $lastIdPath;

    private $metaFields = [];
    private $indexContent = false;
    private $specificId = "";
    private $verboseIndexation = false;

    /**
     * @param ContextInterface $ctx
     * @param array $options
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        parent::init($ctx, $options);
        $metaFields = $this->getContextualOption($ctx, "index_meta_fields");
        $specKey = $this->getContextualOption($ctx, "repository_specific_keywords");
        if (!empty($metaFields)) {
            $this->metaFields = explode(",",$metaFields);
        }

        if (!empty($specKey)) {
            $this->specificId = "-".str_replace([",", "/"], ["-", "__"], VarsFilter::filter($specKey, $ctx));
        }

        /* Connexion to Elastica Client with the default parameters */
        $this->client = new Elastica\Client([
            "host" => $this->getContextualOption($ctx, "ELASTICSEARCH_HOST"),
            "port" => $this->getContextualOption($ctx, "ELASTICSEARCH_PORT")]
        );

        $this->indexContent = ($this->getContextualOption($ctx, "index_content") == true);
    }

    /**
     * @param ContextInterface $ctx
     * @param \Pydio\Access\Core\AbstractAccessDriver $accessDriver
     */
    public function initMeta(ContextInterface $ctx, \Pydio\Access\Core\AbstractAccessDriver $accessDriver)
    {
        $messages = LocaleService::getMessages();
        if (!empty($this->metaFields) || $this->indexContent) {
            $metaFields = $this->metaFields;
            /** @var \DOMElement $el */
            $el = $this->getXPath()->query("/indexer")->item(0);
            if ($this->indexContent) {
                if($this->indexContent) $metaFields[] = "ajxp_document_content";
                $data = ["indexed_meta_fields" => $metaFields,
                    "additionnal_meta_columns" => ["ajxp_document_content" => $messages["index.lucene.13"]]
                ];
                $el->setAttribute("indexed_meta_fields", json_encode($data));
            } else {
                $el->setAttribute("indexed_meta_fields", json_encode($metaFields));
            }
        }
        parent::init($ctx, $this->options);
    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     */
    public function indexationIndexNode($node){
        $this->updateNodeIndex(null, $node, false, false);
    }


    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $parentNode
     */
    public function indexationStarts($parentNode){
        $this->loadIndex($parentNode->getContext(), true);
    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $parentNode
     * @param bool $success
     */
    public function indexationEnds($parentNode, $success){
        if($success && $this->currentIndex) {
            $this->currentIndex->optimize();
        }
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @return null
     * @throws \Exception
     */
    public function applyAction(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {
        $actionName = $requestInterface->getAttribute("action");
        $httpVars = $requestInterface->getParsedBody();
        /** @var ContextInterface $ctx */
        $ctx = $requestInterface->getAttribute("ctx");
        $ctxUser = $ctx->getUser();

        $messages = LocaleService::getMessages();

        $x = new \Pydio\Core\Http\Response\SerializableResponseStream();
        $nodesList = new \Pydio\Access\Core\Model\NodesList();
        $responseInterface = $responseInterface->withBody($x);
        $x->addChunk($nodesList);

        if ($actionName == "search") {
            // TMP
            if (strpos($httpVars["query"], "keyword:") === 0) {
                $parts = explode(":", $httpVars["query"]);
                $requestInterface = $requestInterface->withAttribute("action", "search_by_keyword")->withParsedBody(["field" => $parts[1]]);
                $this->applyAction($requestInterface, $responseInterface);
                return null;
            }

            try {
                $this->loadIndex($ctx, false);
            } catch (\Exception $ex) {
                if (ConfService::backgroundActionsSupported() && !ApplicationState::sapiIsCli() && !isSet($httpVars["skip_unindexed"])) {
                    $task = \Pydio\Tasks\TaskService::actionAsTask($ctx, "index", []);
                    $task->setLabel($messages["index.lucene.7"]);
                    $responseInterface = \Pydio\Tasks\TaskService::getInstance()->enqueueTask($task, $requestInterface, $responseInterface);
                    $x->addChunk(new UserMessage($messages["index.lucene.7"]));
                }else{
                    $x->addChunk(new UserMessage($messages["index.lucene.12"]));
                }
            }

            $textQuery = $httpVars["query"];
            if($this->getContextualOption($ctx, "AUTO_WILDCARD") === true && strlen($textQuery) > 0 && ctype_alnum($textQuery)){
                if($textQuery[0] == '"' && $textQuery[strlen($textQuery)-1] == '"'){
                    $textQuery = substr($textQuery, 1, -1);
                }else if($textQuery[strlen($textQuery)-1] != "*" ){
                    $textQuery.="*";
                }
            }


            $this->currentIndex->open();
            $fieldQuery = new Elastica\Query\QueryString();
            $fieldQuery->setAllowLeadingWildcard(true);

            if($textQuery == "*"){

                $fields = ["ajxp_node"];
                $fieldQuery->setQuery("yes");
                $fieldQuery->setFields($fields);

            }else if(strpos($textQuery, ":") !== false){

                // USE LUCENE DSL DIRECTLY (key1:value1 AND key2:value2...)
                $textQuery = str_replace("ajxp_meta_ajxp_document_content:","body:", $textQuery);
                $textQuery = $this->filterSearchRangesKeywords($textQuery);
                $fieldQuery->setQuery($textQuery);

            } else{

                $fields = ["basename","ajxp_meta_*", "body"];
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

            $maxResults = $this->getContextualOption($ctx, "MAX_RESULTS");
            if(isSet($httpVars['limit'])){
                $maxResults = intval($httpVars['limit']);
            }
            $searchOptions = [
                \Elastica\Search::OPTION_SEARCH_TYPE => \Elastica\Search::OPTION_SEARCH_TYPE_QUERY_THEN_FETCH,
                \Elastica\Search::OPTION_SIZE => $maxResults];

            $this->logDebug(__FUNCTION__,"Executing query: ", $textQuery);
            $fullQuery = new Elastica\Query();
            $fullQuery->setQuery($fieldQuery);

            $qb = new Elastica\QueryBuilder();
            $fullQuery = new Elastica\Query();
            $filter = $qb->query()->match("ajxp_scope", "shared");
            $fullQuery->setQuery(
                $qb->query()->bool()->addMust($fieldQuery)->addFilter($filter)
            );
            $result = $search->search($fullQuery, $searchOptions);
            $this->logDebug(__FUNCTION__,"Search finished. ");
            $hits = $result->getResults();

            foreach ($hits as $hit) {
                $source = $hit->getSource();

                if ($source["serialized_metadata"] != null) {
                    $meta = unserialize(base64_decode($source["serialized_metadata"]));
                    $tmpNode = new AJXP_Node($source["node_url"], $meta);
                    if(!$tmpNode->hasUser()){
                        if($source['ajxp_scope'] === "user" && !empty($source['ajxp_user'])) $tmpNode->setUserId($source['ajxp_user']);
                        else $tmpNode->setUserId($ctx->getUser()->getId());
                    }
                } else {
                    $tmpNode = new AJXP_Node($source["node_url"], []);
                    if(!$tmpNode->hasUser()){
                        if($source['ajxp_scope'] === "user" && !empty($source['ajxp_user'])) $tmpNode->setUserId($source['ajxp_user']);
                        else $tmpNode->setUserId($ctx->getUser()->getId());
                    }
                    $tmpNode->loadNodeInfo();
                }

                if (!file_exists($tmpNode->getUrl())) {
                    try{
                        $this->currentType->deleteById($hit->getId());
                    }catch (Elastica\Exception\NotFoundException $nfe){}
                    continue;
                }

                $tmpNode->search_score = sprintf("%0.2f", $hit->getScore());
                $nodesList->addBranch($tmpNode);
            }

        } else if ($actionName == "search_by_keyword") {

            try {
                $this->loadIndex($ctx, false);
            } catch (\Exception $ex) {
                throw new \Exception($messages["index.lucene.7"]);
            }

            $searchField = InputFilter::sanitize($httpVars["field"], InputFilter::SANITIZE_ALPHANUM);

            $fieldQuery = new Elastica\Query\QueryString();
            $fields = [$searchField];
            $fieldQuery->setQuery($searchField == "ajxp_node"?"yes":"true");

            $fieldQuery->setFields($fields);
            $fieldQuery->setAllowLeadingWildcard(false);

            $search = new Elastica\Search($this->client);
            $search->addIndex($this->currentIndex)->addType($this->currentType);

            $maxResults = $this->getContextualOption($ctx, "MAX_RESULTS");
            if(isSet($httpVars['limit'])){
                $maxResults = intval($httpVars['limit']);
            }
            $searchOptions = [
                \Elastica\Search::OPTION_SEARCH_TYPE => \Elastica\Search::OPTION_SEARCH_TYPE_QUERY_THEN_FETCH,
                \Elastica\Search::OPTION_SIZE => $maxResults];

            $qb = new Elastica\QueryBuilder();
            $fullQuery = new Elastica\Query();
            $fullQuery->setQuery(
                $qb->query()->bool()
                    ->addMust($fieldQuery)
                    ->addMust($qb->query()->match("ajxp_scope", "user"))
                    ->addMust($qb->query()->match("user", $ctxUser->getId()))
            );

            $result = $search->search($fullQuery, $searchOptions);
            $this->logDebug(__FUNCTION__,"Search finished. ");
            $hits = $result->getResults();

            foreach ($hits as $hit) {

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
                    try{
                        $this->currentType->deleteById($hit->getId());
                    }catch (Elastica\Exception\NotFoundException $eEx){}
                    continue;
                }
                $tmpNode->search_score = sprintf("%0.2f", $hit->score);
                $nodesList->addBranch($tmpNode);

            }
        }

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
        @set_time_limit(60);
        $handle = opendir($url);

        if ($handle !== false) {
            while ( ($child = readdir($handle)) != false) {
                if($child[0] == ".") continue;
                $newUrl = $url."/".$child;
                $this->logDebug("Indexing Node ".$newUrl);
                try {
                    $this->updateNodeIndex(null, new AJXP_Node($newUrl));
                } catch (\Exception $e) {
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
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     */
    public function updateNodeIndexMeta($node)
    {
        $this->loadIndex($node->getContext(), true);
        if (UsersService::usersEnabled() && $node->getContext()->hasUser()) {

            $query = new Elastica\Query\Term();
            $query->setTerm("node_url", $node->getUrl());
            $results = $this->currentType->search($query);
            $hits = $results->getResults();
            foreach ($hits as $hit) {
                $source = $hit->getSource();
                if ($source['ajxp_scope'] == 'shared' || ($source['ajxp_scope'] == 'user' && $source['ajxp_user'] == $node->getContext()->getUser()->getId())) {
                    try{
                        $this->currentType->deleteById($hit->getId());
                    }catch (Elastica\Exception\NotFoundException $eEx){}
                }
            }
        } else {
            $id = $this->getIndexedDocumentId($node);
            if($id != null) {
                try{
                    $this->currentType->deleteById($id);
                }catch (Elastica\Exception\NotFoundException $eEx){}
            }
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
     * @param \Pydio\Access\Core\Model\AJXP_Node $oldNode
     * @param \Pydio\Access\Core\Model\AJXP_Node $newNode
     * @param Boolean $copy
     * @param bool $recursive
     */
    public function updateNodeIndex($oldNode, $newNode = null, $copy = false, $recursive = false)
    {
        if($oldNode == null){
            $this->loadIndex($newNode->getContext(), true);
        }else{
            $this->loadIndex($oldNode->getContext(), true);
        }

        if ($oldNode != null && $copy == false) {
            $oldDocId = $this->getIndexedDocumentId($oldNode);
            if ($oldDocId != null) {
                $this->currentType->deleteById($oldDocId);
                $childrenHits = $this->getIndexedChildrenDocuments($oldNode);

                if ($childrenHits != null) {
                    $childrenHits = $childrenHits->getResults();

                    foreach ($childrenHits as $hit) {
                        try{
                            $this->currentType->deleteById($hit->getId());
                        }catch (Elastica\Exception\NotFoundException $eEx){}
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
                        try{
                            $this->currentType->deleteById($hit->getId());
                        }catch (Elastica\Exception\NotFoundException $eEx){}
                    }
                    $newChildURL = str_replace($oldNode->getUrl(),
                        $newNode->getUrl(),
                        $oldChildURL);
                    $this->createIndexedDocument(new AJXP_Node($newChildURL));
                }
            }
        }
    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $ajxpNode
     * @throws \Exception
     */
    public function createIndexedDocument($ajxpNode)
    {
        $ajxpNode->loadNodeInfo();

        $parseContent = $this->indexContent;
        if ($parseContent && $ajxpNode->bytesize > $this->getContextualOption($ajxpNode->getContext(), "PARSE_CONTENT_MAX_SIZE")) {
            $parseContent = false;
        }

        $data = [];
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

        if (isSet($ajxpNode->indexableMetaKeys["user"]) && count($ajxpNode->indexableMetaKeys["user"]) && UsersService::usersEnabled() && $ajxpNode->getContext()->hasUser()) {

            $userData = [
                "ajxp_scope" => "user",
                "user"      => $ajxpNode->getUser()->getId(),
                "serialized_metadata" => $data["serialized_metadata"],
                "node_url"  => $data["node_url"],
                "node_path"  => $data["node_path"]
            ];
            $userData["ajxp_user"] = $ajxpNode->getContext()->getUser()->getId();
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

    /**
     * Transform not data to ready to store mapping
     * @param $data
     * @return array
     */
    protected function dataToMappingProperties($data){

        $mapping_properties = [];
        foreach ($data as $key => $value) {
            if ($key == "node_url" || $key == "node_path") {
                $mapping_properties[$key] = ["type" => "string", "index" => "not_analyzed"];
            } else if($key == "serialized_metadata"){
                $mapping_properties[$key] = ["type" => "string" /*, "index" => "no" */];
            } else if ($key == "ajxp_bytesize"){
                $mapping_properties[$key] = ["type" => "long"];
            } else {
                $type = gettype($value);
                if ($type != "integer" && $type != "boolean" && $type != "double") {
                    $type = "string";
                }
                $mapping_properties[$key] = ["type" => $type];
            }
        }
        return $mapping_properties;

    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $ajxpNode
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
        $testQ = str_replace("/", "AJXPFAKESEP", $ajxpNode->getPath()."/");

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
     * @param ContextInterface $ctx
     * @param bool $create
     */
    protected function loadIndex(ContextInterface $ctx, $create = true)
    {
        $specificId = $this->buildSpecificId($ctx);

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
