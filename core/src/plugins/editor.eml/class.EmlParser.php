<?php
defined('AJXP_EXEC') or die( 'Access not allowed');

class EmlParser extends AJXP_Plugin{
	
	
	public function switchAction($action, $httpVars, $filesVars){
		
		if(!isSet($this->actions[$action])) return false;
    	
		$repository = ConfService::getRepository();
		if(!$repository->detectStreamWrapper(true)){
			return false;
		}
		
		$streamData = $repository->streamData;
    	$destStreamURL = $streamData["protocol"]."://".$repository->getId();
		if(empty($httpVars["file"])) return;
    	$file = $destStreamURL.AJXP_Utils::decodeSecureMagic($httpVars["file"]);
    	
    	switch($action){
    		case "eml_get_xml_structure":
    			require_once("Mail/mimeDecode.php");
    			$params = array(
    				'include_bodies' => false,
    				'decode_bodies' => false,
    				'decode_headers' => true
    			);    			
    			$content = file_get_contents($file);
    			$decoder = new Mail_mimeDecode($content);
    			
    			header('Content-Type: text/xml; charset=UTF-8');
				header('Cache-Control: no-cache');    			
    			print($decoder->getXML($decoder->decode(array($params))));    			
    		break;
    		case "eml_get_bodies":
    			require_once("Mail/mimeDecode.php");
    			$params = array(
    				'include_bodies' => true,
    				'decode_bodies' => true,
    				'decode_headers' => false
    			);    			
    			$content = file_get_contents($file);
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
    			$content = file_get_contents($file);
    			$decoder = new Mail_mimeDecode($content);
    			$structure = $decoder->decode($params);
    			$part = $this->_findAttachmentById($structure, $attachId);
    			if($part !== false){
	    			$fake = new fsAccessDriver("fake", "");
	    			$fake->readFile($part->body, "file", $part->d_parameters['filename'], true);
	    			exit();
    			}else{
    				var_dump($structure);
    			}
    		break;
    		
    		default: 
    		break;
    	}
    	
    	
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

?>