<?php

class AjxpLuceneIndexer extends AJXP_Plugin{

    private $currentIndex;
    private $accessDriver;
    private $metaFields = array();
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
	}

    public function initMeta($accessDriver){
        $this->accessDriver = $accessDriver;
        if(!empty($this->options["index_meta_fields"])){
            $el = $this->xPath->query("/indexer")->item(0);
            $el->setAttribute("indexed_meta_fields", json_encode($this->metaFields));
        }
        parent::init($this->options);
    }

	
	public function applyAction($actionName, $httpVars, $fileVars){
		if($actionName == "search"){
			require_once("Zend/Search/Lucene.php");
            if($this->isIndexLocked(ConfService::getRepository()->getId())){
                throw new Exception("Warning, the repository is currently being indexed, please retry later.");
            }
			$index =  $this->loadIndex(ConfService::getRepository()->getId(), false);
			if(isSet($this->metaFields) && isSet($httpVars["fields"])){
                $sParts = array();
                foreach(explode(",",$httpVars["fields"]) as $searchField){
                    if($searchField == "filename"){
                        $sParts[] = "basename:".$httpVars["query"];
                    }else if(in_array($searchField, $this->metaFields)){
                        $sParts[] = "ajxp_meta_".$searchField.":".$httpVars["query"];
                    }
                }
                $query = implode(" OR ", $sParts);
				AJXP_Logger::debug("Query : $query");
			}else{
				$index->setDefaultSearchField("basename");
				$query = $httpVars["query"];
			}
			$hits = $index->find($query);

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
                $tmpNode->search_score = sprintf("%0.2f", $hit->score);
				AJXP_XMLWriter::renderAjxpNode($tmpNode);
			}
			AJXP_XMLWriter::close();
		}else if($actionName == "index"){
			$dir = AJXP_Utils::decodeSecureMagic($httpVars["dir"]);
            if(empty($dir)) $dir = "/";
            $repo = ConfService::getRepository();
            if($this->isIndexLocked($repo->getId())){
                throw new Exception("Warning there seem to be already an indexation running! Please wait!");
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
                AJXP_XMLWriter::triggerBgAction("check_lock", array(), "Indexing $dir in background", true, 2);
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
                AJXP_XMLWriter::triggerBgAction("check_lock", array("repository_id" => $repoId), "Indexation runnning in background", true, 3);
                AJXP_XMLWriter::close();
            }else{
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::triggerBgAction("info_message", array(), "Indexation finished!", true, 5);
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
        $doc = new Zend_Search_Lucene_Document();
        $doc->addField(Zend_Search_Lucene_Field::Keyword("node_url", $ajxpNode->getUrl()), SystemTextEncoding::getEncoding());
        $doc->addField(Zend_Search_Lucene_Field::Keyword("node_path", str_replace("/", "AJXPFAKESEP", $ajxpNode->getPath())), SystemTextEncoding::getEncoding());
        $doc->addField(Zend_Search_Lucene_Field::Text("basename", basename($ajxpNode->getPath())), SystemTextEncoding::getEncoding());
        foreach ($this->metaFields as $field){
            if($ajxpNode->$field != null){
                $doc->addField(Zend_Search_Lucene_Field::Text("ajxp_meta_$field", $ajxpNode->$field), SystemTextEncoding::getEncoding());
            }
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
        if(!is_dir($iPath)) mkdir($iPath,0666, true);
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
        if(!is_dir(AJXP_CACHE_DIR."/indexes")) mkdir(AJXP_CACHE_DIR."/indexes",0666);
		if(is_dir($iPath)){
		    $index = Zend_Search_Lucene::open($iPath);
		}else{
            if(!$create){
                throw new Exception("Cannot find index for current repository! You should trigger the indexation of the data first!");
            }
		    $index = Zend_Search_Lucene::create($iPath);
		}
		return $index;		
	}
}