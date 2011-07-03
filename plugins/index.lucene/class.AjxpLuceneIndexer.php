<?php

class AjxpLuceneIndexer extends AJXP_Plugin{
	
	public function init($options){
		parent::init($options);		
		set_include_path(get_include_path().":".AJXP_INSTALL_PATH."/plugins/index.lucene");			
	}
	
	public function applyAction($actionName, $httpVars, $fileVars){
		if($actionName == "search"){
			require_once("Zend/Search/Lucene.php");		
			$index =  $this->loadIndex(0);
			$index->setDefaultSearchField("basename");
			$hits = $index->find($httpVars["query"]);
			AJXP_XMLWriter::header();
			foreach ($hits as $hit){
				AJXP_XMLWriter::renderNode($hit->node_path, $hit->basename, true);
			}
			AJXP_XMLWriter::close();
		}else if($actionName == "index"){
			$dir = AJXP_Utils::decodeSecureMagic($httpVars["dir"]);
			
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
		$index =  $this->loadIndex(0);
		$doc = new Zend_Search_Lucene_Document();
		$doc->addField(Zend_Search_Lucene_Field::unIndexed("node_path", $newNode->getPath()));
		$doc->addField(Zend_Search_Lucene_Field::Text("basename", basename($newNode->getPath())));
		$index->addDocument($doc);
		$index->commit();		
	}

	/**
	 * 
	 * Enter description here ...
	 * @param Integer $repositoryId
	 * @return Zend_Search_Lucene_Interface the index
	 */
	protected function loadIndex($repositoryId){
		$iPath = AJXP_INSTALL_PATH."/plugins/index.lucene/an-index";
		if(is_dir($iPath)){
		    $index = Zend_Search_Lucene::open($iPath);
		}else{
		    $index = Zend_Search_Lucene::create($iPath);
		}
		return $index;		
	}
}