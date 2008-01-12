<?php

class AbstractDriver {
	
	/**
	 * @var String
	 */
	var $driverName;
	/**
	 * @var String
	 */
	var $xmlFilePath;
	/**
	* @var Repository
	*/
	var $repository;
	/**
	 * @var array
	 */
	var $actions;
	var $xml_parser;
	var $xml_data;
	var $crtAction;
	
	function AbstractDriver($driverName, $filePath, $repository) {
		$this->driverName = $driverName;
		$this->xmlFilePath = $filePath;
		$this->repository = $repository;
		$this->parseXMLActions();
		// Create fake action for sending its own actions to client.
		$this->actions["get_driver_actions"] = array();
		$this->actions["get_driver_info_panels"] = array();
	}
	
	function hasAction($actionName){
		return isSet($this->actions[$actionName]);
	}
	
	function actionNeedsRight($actionName, $right){
		if($right == 'r') $rString = "READ";
		else $rString = "WRITE";
		$action = $this->actions[$actionName];
		if(isSet($action["rights"]) && isSet($action["rights"][$rString]) && $action["rights"][$rString] == "true"){
			return true;
		}
		return false;
	}
	
	function applyAction($actionName, $httpVars, $filesVar)
	{
		if($actionName == "get_driver_actions" || $actionName == "get_ajxp_actions"){
			$this->sendActionsToClient(false, null);
			exit(1);
		}
		else if($actionName == "get_ajxp_info_panels" || $actionName == "get_driver_info_panels"){
			$this->sendInfoPanelsDef();
			return;
		}
		if(isSet($this->actions[$actionName])){
			// use callback;
			$action = $this->actions[$actionName];
			$callBack = $action["callback"];
			return call_user_func(array(&$this, $callBack), $actionName, $httpVars, $filesVar);
		}
	}
	
	/**
	 * Print the XML for actions
	 *
	 * @param boolean $filterByRight
	 * @param User $user
	 */
	function sendActionsToClient($filterByRight, $user){
		AJXP_XMLWriter::header();
		foreach($this->actions as $name => $action){
			if($name == "get_driver_actions" || $name == "get_ajxp_actions") continue;
			if($filterByRight && $this->actionNeedsRight($name, "r")){
				if($user==null || !$user->canRead($this->repository->getId())) continue;
			}
			if($filterByRight && $this->actionNeedsRight($name, "w")){
				if($user==null || !$user->canWrite($this->repository->getId())) continue;
			}
			if(isSet($action["XML"])){
				$xml = $action["XML"];
				$xml = $this->replaceAjxpXmlKeywords($xml);
				AJXP_XMLWriter::write($xml, true);
			}
		}
		AJXP_XMLWriter::close();
	}
	
	function replaceAjxpXmlKeywords($xml){
		$messages = ConfService::getMessages();			
		$matches = array();
		$xml = str_replace("AJXP_CLIENT_RESOURCES_FOLDER", CLIENT_RESOURCES_FOLDER, $xml);
		$xml = str_replace("AJXP_SERVER_ACCESS", SERVER_ACCESS, $xml);
		$xml = str_replace("AJXP_MIMES_EDITABLE", Utils::getAjxpMimes("editable"), $xml);
		$xml = str_replace("AJXP_MIMES_IMAGE", Utils::getAjxpMimes("image"), $xml);
		$xml = str_replace("AJXP_MIMES_AUDIO", Utils::getAjxpMimes("audio"), $xml);
		if(preg_match_all("/AJXP_MESSAGE(\[.*?\])/", $xml, $matches, PREG_SET_ORDER)){
			foreach($matches as $match){
				$messId = str_replace("]", "", str_replace("[", "", $match[1]));
				$xml = str_replace("AJXP_MESSAGE[$messId]", utf8_encode($messages[$messId]), $xml);
			}
		}
		return $xml;		
	}
	
	function fillActionsWithXML(){
		$fileData = file_get_contents($this->xmlFilePath);
		$matches = array();
		foreach ($this->actions as $actionName => $actionData){
			preg_match_all('/(<action name=\"'.$actionName.'\".*?>.*?<\/action>)/', str_replace("\n", "", $fileData), $matches);
			if(count($matches) && count($matches[0])){
				$actionXML = $matches[0][0];
				$this->actions[$actionName]["XML"] = $actionXML;
			}
		}
	}
	
	function sendInfoPanelsDef(){
		$fileData = file_get_contents($this->xmlFilePath);
		$matches = array();
		preg_match('/<infoPanels>.*<\/infoPanels>/', str_replace("\n", "",$fileData), $matches);
		if(count($matches)){
			AJXP_XMLWriter::header();
			AJXP_XMLWriter::write($this->replaceAjxpXmlKeywords(str_replace("\n", "",$matches[0])), true);
			AJXP_XMLWriter::close();
			exit(1);
		}		
	}
	
	function parseXMLActions()
	{
		$this->xml_data = file_get_contents($this->xmlFilePath);
	    $this->xml_parser = xml_parser_create( "UTF-8" );
	
	    //xml_parser_set_option( $this->xml_parser, XML_OPTION_CASE_FOLDING, false );
	    xml_set_object( $this->xml_parser, $this );
	    xml_set_element_handler( $this->xml_parser, "_startElement", "_endElement");
	    xml_set_character_data_handler( $this->xml_parser, "_cData" );
	    xml_parse( $this->xml_parser, $this->xml_data, true );
	    xml_parser_free( $this->xml_parser );

	    $this->fillActionsWithXML();
	    //print_r($this->actions);	
	}
	
	function _startElement($parser, $tag, $attributeList){
		if($tag == 'ACTION'){
			$this->crtAction = array();
			$this->crtAction["name"] = $attributeList["NAME"];
		}else if($tag == 'SERVERCALLBACK'){
			$this->crtAction["callback"] = $attributeList["METHODNAME"];
		}else if($tag == "RIGHTSCONTEXT"){
			$this->crtAction["rights"] = $attributeList;
		}
	}
	
	function _endElement($parser, $tag){		
		if($tag == "ACTION"){
			$this->actions[$this->crtAction["name"]] = $this->crtAction;
		}
	}
	
	function _cData($parser, $data){}
		
}

?>