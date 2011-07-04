<?php

class AjxpLuceneIndexer extends AJXP_Plugin{

    private $currentIndex;
    private $accessDriver;

	public function init($options){
		parent::init($options);		
		set_include_path(get_include_path().PATH_SEPARATOR.AJXP_INSTALL_PATH."/plugins/index.lucene");
	}

    public function initMeta($accessDriver){
        $this->accessDriver = $accessDriver;
    }

	
	public function applyAction($actionName, $httpVars, $fileVars){
		if($actionName == "search"){
			require_once("Zend/Search/Lucene.php");
            Zend_Search_Lucene_Search_QueryParser::dontSuppressQueryParsingExceptions();
			$index =  $this->loadIndex(0);

			$index->setDefaultSearchField("basename");
			$hits = $index->find($httpVars["query"]);

			AJXP_XMLWriter::header();
			foreach ($hits as $hit){
                $meta = array();
                $isDir = false; // TO BE STORED IN INDEX
                $meta["icon"] = AJXP_Utils::mimetype($hit->node_url, "image", $isDir);
                $tmpNode = new AJXP_Node($hit->node_url, $meta);
                $meta["search_score"] = $hit->score;
				AJXP_XMLWriter::renderNode($tmpNode->getPath(), $hit->basename, $isDir, $meta);
			}
			AJXP_XMLWriter::close();
		}else if($actionName == "index"){
			$dir = AJXP_Utils::decodeSecureMagic($httpVars["dir"]);
            if(empty($dir)) $dir = "/";
            $repo = ConfService::getRepository();
			$accessType = $repo->getAccessType();
            $accessPlug = AJXP_PluginsService::getInstance()->getPluginByTypeName("access", $accessType);
            $stData = $accessPlug->detectStreamWrapper(true);
            $url = $stData["protocol"]."://".$repo->getId().$dir;
            $repoId = $repo->getId();
            // GIVE BACK THE HAND TO USER
            session_write_close();
            $this->currentIndex = $this->loadIndex($repoId);
            $this->recursiveIndexation($url);
            print("Optimizing\n");
            $this->currentIndex->optimize();
            print("Commiting\n");
            $this->currentIndex->commit();
            $this->currentIndex = null;
		}
	}

    public function recursiveIndexation($url){
        print("Indexing $url \n");
        $handle = opendir($url);
        if($handle !== false){
            while( ($child = readdir($handle)) != false){
                if($child[0] == ".") continue;
                $newUrl = $url."/".$child;
                $this->updateNodeIndex(null, new AJXP_Node($newUrl));
            }
            closedir($handle);
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
            if($oldNode == null && is_dir($newNode->getUrl())){
                $this->recursiveIndexation($newNode->getUrl());
            }else{
                $doc = $this->createIndexedDocument($newNode);
                $index->addDocument($doc);
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
                $newChildURL = str_replace($oldNode->getUrl(), $newNode->getUrl(), $oldChildURL);
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
        $doc = new Zend_Search_Lucene_Document();
        $doc->addField(Zend_Search_Lucene_Field::Keyword("node_url", $ajxpNode->getUrl()));
        $doc->addField(Zend_Search_Lucene_Field::Keyword("node_path", str_replace("/", "AJXPFAKESEP", $ajxpNode->getPath())));
        $doc->addField(Zend_Search_Lucene_Field::Text("basename", basename($ajxpNode->getPath())));
        return $doc;
    }

    /**
     * @param  Zend_Search_Lucene_Interface $index
     * @param AJXP_Node $ajxpNode
     * @return Number
     */
    public function getIndexedDocumentId($index, $ajxpNode){
        $term = new Zend_Search_Lucene_Index_Term($ajxpNode->getUrl(), "node_url");
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
        $testQ = str_replace("/", "AJXPFAKESEP", $ajxpNode->getPath());
        $pattern = new Zend_Search_Lucene_Index_Term($testQ .'*', 'node_path');
        $query = new Zend_Search_Lucene_Search_Query_Wildcard($pattern);
        $hits = $index->find($query);
        return $hits;
    }

	/**
	 * 
	 * Enter description here ...
	 * @param Integer $repositoryId
	 * @return Zend_Search_Lucene_Interface the index
	 */
	protected function loadIndex($repositoryId){
        require_once("Zend/Search/Lucene.php");
        $iPath = AJXP_CACHE_DIR."/indexes/index-$repositoryId";
        if(!is_dir(AJXP_CACHE_DIR."/indexes")) mkdir(AJXP_CACHE_DIR."/indexes",0666);
		if(is_dir($iPath)){
		    $index = Zend_Search_Lucene::open($iPath);
		}else{
		    $index = Zend_Search_Lucene::create($iPath);
		}
		return $index;		
	}
}