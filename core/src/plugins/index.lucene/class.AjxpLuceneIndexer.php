<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package info.ajaxplorer.plugins
 * Encapsultion of the Zend_Search_Lucene component as a plugin
 */
class AjxpLuceneIndexer extends AJXP_Plugin{

    private $currentIndex;
    private $accessDriver;
    private $metaFields = array();
    private $indexContent = false;
    private $specificId = "";
    private $verboseIndexation = false;

	public function init($options){
		//parent::init($options);
        $this->options = $options;
		set_include_path(get_include_path().PATH_SEPARATOR.AJXP_INSTALL_PATH."/plugins/index.lucene");
        if(!empty($this->options["index_meta_fields"])){
        	$this->metaFields = explode(",",$this->options["index_meta_fields"]);
        }
        if(!empty($this->options["repository_specific_keywords"])){
            $this->specificId = "-".str_replace(",", "-", AJXP_VarsFilter::filter($this->options["repository_specific_keywords"]));
        }
        $this->indexContent = ($this->options["index_content"] == true);
	}

    public function initMeta($accessDriver){
        $this->accessDriver = $accessDriver;
        if(!empty($this->options["index_meta_fields"]) || $this->indexContent){
            $metaFields = $this->metaFields;
            $el = $this->xPath->query("/indexer")->item(0);
            if($this->indexContent){
                if($this->indexContent) $metaFields[] = "ajxp_document_content";
                $data = array("indexed_meta_fields" => $metaFields,
                              "additionnal_meta_columns" => array("ajxp_document_content" => "Content")
                );
                $el->setAttribute("indexed_meta_fields", json_encode($data));
            }else{
                $el->setAttribute("indexed_meta_fields", json_encode($metaFields));
            }
        }
        parent::init($this->options);
    }

	
	public function applyAction($actionName, $httpVars, $fileVars){
        $messages = ConfService::getMessages();
		if($actionName == "search"){
			require_once("Zend/Search/Lucene.php");
            if($this->isIndexLocked(ConfService::getRepository()->getId())){
                throw new Exception($messages["index.lucene.6"]);
            }
            try{
                $index =  $this->loadIndex(ConfService::getRepository()->getId(), false);
            }catch(Exception $ex){
                $this->applyAction("index", array(), array());
                throw new Exception($messages["index.lucene.7"]);
            }
			if((isSet($this->metaFields) || $this->indexContent) && isSet($httpVars["fields"])){
                $sParts = array();
                foreach(explode(",",$httpVars["fields"]) as $searchField){
                    if($searchField == "filename"){
                        $sParts[] = "basename:".$httpVars["query"];
                    }else if(in_array($searchField, $this->metaFields)){
                        $sParts[] = "ajxp_meta_".$searchField.":".$httpVars["query"];
                    }else if($searchField == "ajxp_document_content"){
                        $sParts[] = "title:".$httpVars["query"];
                        $sParts[] = "body:".$httpVars["query"];
                        $sParts[] = "keywords:".$httpVars["query"];
                    }
                }
                $query = implode(" OR ", $sParts);
				AJXP_Logger::debug("Query : $query");
			}else{
				$index->setDefaultSearchField("basename");
				$query = $httpVars["query"];
			}
			$hits = $index->find($query);
            $commitIndex = false;

			AJXP_XMLWriter::header();
			foreach ($hits as $hit){
                $meta = array();
                //$isDir = false; // TO BE STORED IN INDEX
                //$meta["icon"] = AJXP_Utils::mimetype(SystemTextEncoding::fromUTF8($hit->node_url), "image", $isDir);
                if($hit->serialized_metadata!=null){
                    $meta = unserialize(base64_decode($hit->serialized_metadata));
                	$tmpNode = new AJXP_Node(SystemTextEncoding::fromUTF8($hit->node_url), $meta);
                }else{
                	$tmpNode = new AJXP_Node(SystemTextEncoding::fromUTF8($hit->node_url), array());
                    $tmpNode->loadNodeInfo();
                }
                if(!file_exists($tmpNode->getUrl())){
                    $index->delete($hit->id);
                    $commitIndex = true;
                    continue;
                }
                $tmpNode->search_score = sprintf("%0.2f", $hit->score);
				AJXP_XMLWriter::renderAjxpNode($tmpNode);
			}
			AJXP_XMLWriter::close();
            if($commitIndex){
                $index->commit();
            }
		}else if($actionName == "index"){
			$dir = AJXP_Utils::decodeSecureMagic($httpVars["dir"]);
            if(empty($dir)) $dir = "/";
            $repo = ConfService::getRepository();
            if($this->isIndexLocked($repo->getId())){
                throw new Exception($messages["index.lucene.6"]);
            }
            $accessType = $repo->getAccessType();
            $accessPlug = AJXP_PluginsService::getInstance()->getPluginByTypeName("access", $accessType);
            $stData = $accessPlug->detectStreamWrapper(true);
            $repoId = $repo->getId();
            $url = $stData["protocol"]."://".$repoId.$dir;
            if(isSet($httpVars["verbose"]) && $httpVars["verbose"] == "true"){
                $this->verboseIndexation = true;
            }

            if(ConfService::backgroundActionsSupported() && !ConfService::currentContextIsCommandLine()){
                AJXP_Controller::applyActionInBackground($repoId, "index", $httpVars);
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::triggerBgAction("check_lock", array(), sprintf($messages["index.lucene.8"], $dir), true, 2);
                AJXP_XMLWriter::close();
                return;
            }

            $this->lockIndex($repoId);

            // GIVE BACK THE HAND TO USER
            session_write_close();
            $this->currentIndex = $this->loadIndex($repoId);
            $this->recursiveIndexation($url);
            if(ConfService::currentContextIsCommandLine() && $this->verboseIndexation){
                print("Optimizing\n");
            }
            $this->currentIndex->optimize();
            if(ConfService::currentContextIsCommandLine() && $this->verboseIndexation){
                print("Commiting Index\n");
            }
            $this->currentIndex->commit();
            $this->currentIndex = null;
            $this->releaseLock($repoId);
        }else if($actionName == "check_lock"){
            $repoId = $httpVars["repository_id"];
            if($this->isIndexLocked($repoId)){
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::triggerBgAction("check_lock", array("repository_id" => $repoId), sprintf($messages["index.lucene.8"], ""), true, 3);
                AJXP_XMLWriter::close();
            }else{
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::triggerBgAction("info_message", array(), $messages["index.lucene.5"], true, 5);
                AJXP_XMLWriter::close();
            }
        }

	}

    public function recursiveIndexation($url){
        //print("Indexing $url \n");
        AJXP_Logger::debug("Indexing content of folder ".$url);
        if(ConfService::currentContextIsCommandLine() && $this->verboseIndexation){
            print("Indexing content of ".$url."\n");
        }
        $handle = opendir($url);
        if($handle !== false){
            while( ($child = readdir($handle)) != false){
                if($child[0] == ".") continue;
                $newUrl = $url."/".$child;
                AJXP_Logger::debug("Indexing Node ".$newUrl);
                $this->updateNodeIndex(null, new AJXP_Node($newUrl));
            }
            closedir($handle);
        }else{
            AJXP_Logger::debug("Cannot open $url!!");
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
	 */
	public function updateNodeIndex($oldNode, $newNode = null, $copy = false){
		require_once("Zend/Search/Lucene.php");
        if(isSet($this->currentIndex)){
            $index = $this->currentIndex;
        }else{
       		$index =  $this->loadIndex(ConfService::getRepository()->getId());
        }

        if($oldNode != null && $copy == false){
            $oldDocId = $this->getIndexedDocumentId($index, $oldNode);
            if($oldDocId != null){
                $index->delete($oldDocId);
                if($newNode == null){ // DELETION
                    $childrenHits = $this->getIndexedChildrenDocuments($index, $oldNode);
                    foreach ($childrenHits as $hit){
                        $index->delete($hit->id);
                    }
                }
            }
        }

        if($newNode != null){
            // Make sure it does not already exists anyway
            $newDocId = $this->getIndexedDocumentId($index, $newNode);
            if($newDocId != null){
                $index->delete($newDocId);
            }
            $doc = $this->createIndexedDocument($newNode);
            $index->addDocument($doc);
            if($oldNode == null && is_dir($newNode->getUrl())){
                $this->recursiveIndexation($newNode->getUrl());
            }
        }

        if($oldNode != null && $newNode != null && is_dir($newNode->getUrl())){ // Copy / Move / Rename
            // Get old node children docs, and update them manually, no need to scan real directory
            $childrenHits = $this->getIndexedChildrenDocuments($index, $oldNode);
            foreach ($childrenHits as $hit){
                $oldChildURL = $index->getDocument($hit->id)->node_url;
                if($copy == false){
                    $index->delete($hit->id);
                }
                $newChildURL = str_replace(SystemTextEncoding::toUTF8($oldNode->getUrl()),
                                           SystemTextEncoding::toUTF8($newNode->getUrl()),
                                           $oldChildURL);
                $newChildURL = SystemTextEncoding::fromUTF8($newChildURL);
                $index->addDocument($this->createIndexedDocument(new AJXP_Node($newChildURL)));
            }
        }
        
        if(!isSet($this->currentIndex)){
		    $index->commit();
        }
	}

    /**
     * @param AJXP_Node $ajxpNode
     * @return Zend_Search_Lucene_Document
     */
    public function createIndexedDocument($ajxpNode){
        $ajxpNode->loadNodeInfo();
        $ext = pathinfo($ajxpNode->getLabel(), PATHINFO_EXTENSION);
        $parseContent = $this->indexContent;
        if($parseContent && $ajxpNode->bytesize > $this->pluginConf["PARSE_CONTENT_MAX_SIZE"]){
            $parseContent = false;
        }
        if($parseContent && in_array($ext, explode(",",$this->pluginConf["PARSE_CONTENT_HTML"]))){
            $doc = Zend_Search_Lucene_Document_Html::loadHTMLFile($ajxpNode->getUrl());
        }else{
            $doc = new Zend_Search_Lucene_Document();
        }
        $doc->addField(Zend_Search_Lucene_Field::Keyword("node_url", $ajxpNode->getUrl()), SystemTextEncoding::getEncoding());
        $doc->addField(Zend_Search_Lucene_Field::Keyword("node_path", str_replace("/", "AJXPFAKESEP", $ajxpNode->getPath())), SystemTextEncoding::getEncoding());
        $doc->addField(Zend_Search_Lucene_Field::Text("basename", basename($ajxpNode->getPath())), SystemTextEncoding::getEncoding());
        foreach ($this->metaFields as $field){
            if($ajxpNode->$field != null){
                $doc->addField(Zend_Search_Lucene_Field::Text("ajxp_meta_$field", $ajxpNode->$field), SystemTextEncoding::getEncoding());
            }
        }
        if($parseContent && in_array($ext, explode(",",$this->pluginConf["PARSE_CONTENT_TXT"]))){
            $doc->addField(Zend_Search_Lucene_Field::unStored("body", file_get_contents($ajxpNode->getUrl())));
        }
        // Store a cached copy of the metadata
        $doc->addField(Zend_Search_Lucene_Field::Binary("serialized_metadata", base64_encode(serialize($ajxpNode->metadata))));
        return $doc;
    }

    /**
     * @param  Zend_Search_Lucene_Interface $index
     * @param AJXP_Node $ajxpNode
     * @return Number
     */
    public function getIndexedDocumentId($index, $ajxpNode){
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
    public function getIndexedChildrenDocuments($index, $ajxpNode){
        // Try getting doc by url
        $testQ = str_replace("/", "AJXPFAKESEP", SystemTextEncoding::toUTF8($ajxpNode->getPath()));
        $pattern = new Zend_Search_Lucene_Index_Term($testQ .'*', 'node_path');
        $query = new Zend_Search_Lucene_Search_Query_Wildcard($pattern);
        $hits = $index->find($query);
        return $hits;
    }

    protected function lockIndex($repositoryId){
        $iPath = AJXP_CACHE_DIR."/indexes";
        if(!is_dir($iPath)) mkdir($iPath,0755, true);
        touch($iPath."/.ajxp_lock-".$repositoryId.$this->specificId);
    }

    protected function isIndexLocked($repositoryId){
        return file_exists(AJXP_CACHE_DIR."/indexes/.ajxp_lock-".$repositoryId.$this->specificId);
    }

    protected function releaseLock($repositoryId){
        @unlink(AJXP_CACHE_DIR."/indexes/.ajxp_lock-".$repositoryId.$this->specificId);
    }

	/**
	 * 
	 * Enter description here ...
	 * @param Integer $repositoryId
     * @param bool $create
	 * @return Zend_Search_Lucene_Interface the index
	 */
	protected function loadIndex($repositoryId, $create = true){
        require_once("Zend/Search/Lucene.php");
        $iPath = AJXP_CACHE_DIR."/indexes/index-$repositoryId".$this->specificId;
        if(!is_dir(AJXP_CACHE_DIR."/indexes")) mkdir(AJXP_CACHE_DIR."/indexes",0755,true);
		if(is_dir($iPath)){
		    $index = Zend_Search_Lucene::open($iPath);
		}else{
            if(!$create){
                $messages = ConfService::getMessages();
                throw new Exception($messages["index.lucene.9"]);
            }
		    $index = Zend_Search_Lucene::create($iPath);
		}
		return $index;		
	}
}