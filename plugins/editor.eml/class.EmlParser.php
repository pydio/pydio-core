<?php
defined('AJXP_EXEC') or die( 'Access not allowed');

class EmlParser extends AJXP_Plugin{
	
	public static $currentListingOnlyEmails;
	
	public function switchAction($action, $httpVars, $filesVars){
		
		if(!isSet($this->actions[$action])) return false;
    	
		$repository = ConfService::getRepository();
		if(!$repository->detectStreamWrapper(true)){
			return false;
		}
		
		$streamData = $repository->streamData;
    	$destStreamURL = $streamData["protocol"]."://".$repository->getId();
    	$wrapperClassName = $streamData["classname"];
		if(empty($httpVars["file"])) return;
    	$file = $destStreamURL.AJXP_Utils::decodeSecureMagic($httpVars["file"]);
    	$mess = ConfService::getMessages();
    	
    	switch($action){
    		case "eml_get_xml_structure":
    			$params = array(
    				'include_bodies' => false,
    				'decode_bodies' => false,
    				'decode_headers' => true
    			);
    			$decoder = $this->getStructureDecoder($file, ($wrapperClassName == "imapAccessWrapper"));
    			print($decoder->getXML($decoder->decode(array($params))));    			
    		break;
    		case "eml_get_bodies":
    			require_once("Mail/mimeDecode.php");
    			$params = array(
    				'include_bodies' => true,
    				'decode_bodies' => true,
    				'decode_headers' => false
    			);    			
    			if($wrapperClassName == "imapAccessWrapper"){
    				$cache = AJXP_Cache::getItem("eml_remote", $file, null, array("EmlParser", "computeCacheId"));
    				$content = $cache->getData();
    			}else{
	    			$content = file_get_contents($file);
    			}

    			$decoder = new Mail_mimeDecode($content);
    			$structure = $decoder->decode($params);
    			$html = $this->_findPartByCType($structure, "text", "html");
    			$text = $this->_findPartByCType($structure, "text", "plain");
    			if($html != false && isSet($html->ctype_parameters) && isSet($html->ctype_parameters["charset"])){
    				$charset = $html->ctype_parameters["charset"];
    			}
    			if(isSet($charset)){
    				header('Content-Type: text/xml; charset='.$charset);
					header('Cache-Control: no-cache');
					print('<?xml version="1.0" encoding="'.$charset.'"?>');
					print('<email_body>');    			    				
    			}else{
	    			AJXP_XMLWriter::header("email_body");
    			}
    			if($html!==false){
    				print('<mimepart type="html"><![CDATA[');
    				$text = $html->body;
    				print($text);
    				print("]]></mimepart>");
    			}
    			if($text!==false){
    				print('<mimepart type="plain"><![CDATA[');
    				print($text->body);
    				print("]]></mimepart>");
    			}
    			AJXP_XMLWriter::close("email_body");    			
    			
    		break;
    		case "eml_dl_attachment":
    			$attachId = $httpVars["attachment_id"];
    			if(!isset($attachId)) break;
    			
    			require_once("Mail/mimeDecode.php");
    			$params = array(
    				'include_bodies' => true,
    				'decode_bodies' => true,
    				'decode_headers' => false
    			);    			
    			if($wrapperClassName == "imapAccessWrapper"){
    				$cache = AJXP_Cache::getItem("eml_remote", $file, null, array("EmlParser", "computeCacheId"));
    				$content = $cache->getData();
    			}else{
	    			$content = file_get_contents($file);
    			}
    			$decoder = new Mail_mimeDecode($content);
    			$structure = $decoder->decode($params);
    			$part = $this->_findAttachmentById($structure, $attachId);
    			if($part !== false){
	    			$fake = new fsAccessDriver("fake", "");
	    			$fake->readFile($part->body, "file", $part->d_parameters['filename'], true);
	    			exit();
    			}else{
    				//var_dump($structure);
    			}
    		break;
    		case "eml_cp_attachment":
    			$attachId = $httpVars["attachment_id"];
    			$destRep = AJXP_Utils::decodeSecureMagic($httpVars["destination"]);
    			if(!isset($attachId)) {
    				AJXP_XMLWriter::sendMessage(null, "Wrong Parameters");
    				break;
    			}
    			
    			require_once("Mail/mimeDecode.php");
    			$params = array(
    				'include_bodies' => true,
    				'decode_bodies' => true,
    				'decode_headers' => false
    			);    			
    			if($wrapperClassName == "imapAccessWrapper"){
    				$cache = AJXP_Cache::getItem("eml_remote", $file, null, array("EmlParser", "computeCacheId"));
    				$content = $cache->getData();
    			}else{
	    			$content = file_get_contents($file);
    			}
    			
    			$decoder = new Mail_mimeDecode($content);
    			$structure = $decoder->decode($params);
    			$part = $this->_findAttachmentById($structure, $attachId);
    			AJXP_XMLWriter::header();
    			if($part !== false){
	    			$destFile = $destStreamURL.$destRep."/".$part->d_parameters['filename'];
	    			$fp = fopen($destFile, "w");
	    			if($fp !== false){
	    				fwrite($fp, $part->body, strlen($part->body));
	    				fclose($fp);
	    				AJXP_XMLWriter::sendMessage(sprintf($mess["editor.eml.7"], $part->d_parameters["filename"], $destRep), NULL);
	    			}else{
		    			AJXP_XMLWriter::sendMessage(null, $mess["editor.eml.8"]);
	    			}
    			}else{
    				AJXP_XMLWriter::sendMessage(null, $mess["editor.eml.9"]);
    			}
    			AJXP_XMLWriter::close();
    		break;
    		
    		default: 
    		break;
    	}    	    	
	}
	
	public function extractMimeHeaders($currentNode, &$metadata, $wrapperClassName, &$realFile){
		$noMail = true;
		if($metadata["is_file"] && ($wrapperClassName == "imapAccessWrapper" || preg_match("/\.eml$/i",$currentNode))){
			$noMail = false;
		}
		$parsed = parse_url($currentNode);
		if( $noMail || ( isSet($parsed["fragment"]) && strpos($parsed["fragment"], "attachments") === 0 ) ){
			EmlParser::$currentListingOnlyEmails = FALSE;
			return;
		}
		if(EmlParser::$currentListingOnlyEmails === NULL){
			EmlParser::$currentListingOnlyEmails = true;
		}
		if(!isSet($realFile)){
			if($wrapperClassName == "imapAccessWrapper"){
				$cachedFile = AJXP_Cache::getItem("eml_remote", $currentNode, null, array("EmlParser", "computeCacheId"));
				$realFile = $cachedFile->getId();
				if(!is_file($realFile)){
					$cachedFile->getData();// trigger loading!
				}
			}else{
				$realFile = call_user_func(array($wrapperClassName, "getRealFSReference"), $currentNode);
			}
		}
		$cacheItem = AJXP_Cache::getItem("eml_mimes", $realFile, array($this, "mimeExtractorCallback"));
		$data = unserialize($cacheItem->getData());
		$data["ajxp_mime"] = "eml";
		$metadata = array_merge($metadata, $data);
		if($wrapperClassName == "imapAccessWrapper" && $metadata["eml_attachments"]!= "0"){
			$metadata["is_file"] = false;
			$metadata["nodeName"] = basename($currentNode)."#attachments"; 
		}
	}	

	public function mimeExtractorCallback($masterFile, $targetFile){
		$metadata = array();
		require_once("Mail/mimeDecode.php");
    	$params = array(
    		'include_bodies' => true,
    		'decode_bodies' => false,
    		'decode_headers' => true
    	);    			
		$mess = ConfService::getMessages();    	
    	$content = file_get_contents($masterFile);
    	$decoder = new Mail_mimeDecode($content);
		$structure = $decoder->decode($params);
		$allowedHeaders = array("to", "from", "subject", "message-id", "mime-version", "date", "return-path");
		foreach ($structure->headers as $hKey => $hValue){
			if(!in_array($hKey, $allowedHeaders)) continue;
			if(is_array($hValue)){
				$hValue = implode(", ", $hValue);
			}
			if($hKey == "date"){
				$date = strtotime($hValue);
				//$hValue = date($mess["date_format"], $date);
				$metadata["eml_time"] = $date;
			}
			$metadata["eml_".$hKey] = AJXP_Utils::xmlEntities(htmlentities($hValue), true);
		}
		$metadata["eml_attachments"] = 0;
		$parts = $structure->parts;
		if(!empty($parts)){
			foreach($parts as $mimePart){
				if(!empty($mimePart->disposition) && $mimePart->disposition == "attachment"){
					$metadata["eml_attachments"]++;
				}
			}
		}	
		$metadata["icon"] = "eml_images/ICON_SIZE/mail_mime.png";
		file_put_contents($targetFile, serialize($metadata));			
	}
		
	public function lsPostProcess($action, $httpVars, $outputVars){
		if(!EmlParser::$currentListingOnlyEmails){
			if(isSet($httpVars["playlist"])) return;
			header('Content-Type: text/xml; charset=UTF-8');
			header('Cache-Control: no-cache');			
			print($outputVars["ob_output"]);
			return;			
		}
		
		$config = '<columns template_name="eml.list">
			<column messageId="editor.eml.1" attributeName="ajxp_label" sortType="String"/>
			<column messageId="editor.eml.2" attributeName="eml_to" sortType="String"/>
			<column messageId="editor.eml.3" attributeName="eml_subject" sortType="String"/>
			<column messageId="editor.eml.4" attributeName="ajxp_modiftime" sortType="MyDate"/>
			<column messageId="2" attributeName="filesize" sortType="NumberKo"/>
			<column messageId="editor.eml.5" attributeName="eml_attachments" sortType="Number" modifier="EmlViewer.prototype.attachmentCellRenderer" fixedWidth="30"/>
		</columns>';
					
		$dom = new DOMDocument("1.0", "UTF-8");
		$dom->loadXML($outputVars["ob_output"]);
		if(EmlParser::$currentListingOnlyEmails === true){
			// Replace all text attributes by the "from" value
			foreach ($dom->documentElement->childNodes as $child){
				$child->setAttribute("text", $child->getAttribute("eml_from"));
				$child->setAttribute("ajxp_modiftime", $child->getAttribute("eml_time"));
			}
		}
		
		// Add the columns template definition
		$insert = new DOMDocument("1.0", "UTF-8");		
		$config = "<client_configs><component_config className=\"FilesList\" local=\"true\">$config</component_config></client_configs>";			
		$insert->loadXML($config);
		$imported = $dom->importNode($insert->documentElement, true);
		$dom->documentElement->appendChild($imported);
		header('Content-Type: text/xml; charset=UTF-8');
		header('Cache-Control: no-cache');			
		print($dom->saveXML());
	}
	
	/**
	 * 
	 * Enter description here ...
	 * @param string $file url
	 * @param boolean $cacheRemoteContent
	 * @return Mail_mimeDecode
	 */
	public function getStructureDecoder($file, $cacheRemoteContent = false){
		require_once ("Mail/mimeDecode.php");
		if ($cacheRemoteContent) {
			$cache = AJXP_Cache::getItem ( "eml_remote", $file , null, array("EmlParser", "computeCacheId"));
			$content = $cache->getData ();
		} else {
			$content = file_get_contents ( $file );
		}
		$decoder = new Mail_mimeDecode ( $content );
		
		header ( 'Content-Type: text/xml; charset=UTF-8' );
		header ( 'Cache-Control: no-cache' );    			
		return $decoder;
	}
	
	public function listAttachments($file, $cacheRemoteContent = false, &$attachments,  $structure = null){
		if($structure == null){
			$decoder = $this->getStructureDecoder($file, $cacheRemoteContent);
	   		$params = array(
	   			'include_bodies' => false,
	   			'decode_bodies' => false,
	   			'decode_headers' => true
	   		);
			$structure = $decoder->decode($params);
		}
		if(isSet($structure->disposition) && $structure->disposition == "attachment"){
			$attachments[] = array(
				"filename" => $structure->d_parameters['filename'],
				"content-type" => $structure->ctype_primary."/".$structure->ctype_secondary,
				"x-attachment-id" => (isSet($structure->headers["x-attachment-id"])?$structure->headers["x-attachment-id"]:count($attachments))
			);
		}else if(isset($structure->parts)){
			foreach ($structure->parts as $partObject){
				$this->listAttachments($file, true, $attachments, $partObject);
			}
		}
	}
	
	public function getAttachmentBody($file, $attachmentId, $cacheRemoteContent = false){
		$decoder = $this->getStructureDecoder($file, $cacheRemoteContent);
	   	$params = array(
	   		'include_bodies' => true,
	   		'decode_bodies' => true,
	   		'decode_headers' => false
	   	);
		$structure = $decoder->decode($params);
		$part = $this->_findAttachmentById($structure, $attachmentId);
		if($part == false) return false;
		return $part->body;
	}
	
	protected function _findPartByCType($structure, $primary, $secondary){
		if($structure->ctype_primary == $primary && $structure->ctype_secondary == $secondary){
			return $structure;
		}
		if(empty($structure->parts)) return false;
		foreach($structure->parts as $part){
			$res = $this->_findPartByCType($part, $primary, $secondary);
			if($res !== false){
				return $res;
			}
		}
		return false;
	}
	 
	protected function _findAttachmentById($structure, $attachId){
		if(is_numeric($attachId)){
			$attachId = intval($attachId);
			if(empty($structure->parts)) return false;
			$index = 0;
			foreach ($structure->parts as $part){
				if(!empty($part->disposition) &&  $part->disposition == "attachment"){
					if($index == $attachId) return $part;
					$index++;
				}
			}
			return false;
		}else{
			if(!empty($structure->disposition) &&  $structure->disposition == "attachment" 
				&& ($structure->headers["x-attachment-id"] == $attachId || $attachId == "0" )){
				return $structure;
			}
			if(empty($structure->parts)) return false;
			foreach($structure->parts as $part){
				$res = $this->_findAttachmentById($part, $attachId);
				if($res !== false){
					return $res;
				}
			}
			return false;
		}
	}
	
	static public function computeCacheId($mailPath){
		$header = file_get_contents($mailPath."#header");
		//AJXP_Logger::debug("Headers ", $header);
		return md5(basename($header));
	}
	 
}

?>