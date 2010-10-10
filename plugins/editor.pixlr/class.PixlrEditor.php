<?php
/**
 * @package info.ajaxplorer
 * 
 * Copyright 2007-2009 Charles du Jeu
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 * 
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 * 
 * The main conditions are as follow : 
 * You must conspicuously and appropriately publish on each copy distributed 
 * an appropriate copyright notice and disclaimer of warranty and keep intact 
 * all the notices that refer to this License and to the absence of any warranty; 
 * and give any other recipients of the Program a copy of the GNU Lesser General 
 * Public License along with the Program. 
 * 
 * If you modify your copy or copies of the library or any portion of it, you may 
 * distribute the resulting library provided you do so under the GNU Lesser 
 * General Public License. However, programs that link to the library may be 
 * licensed under terms of your choice, so long as the library itself can be changed. 
 * Any translation of the GNU Lesser General Public License must be accompanied by the 
 * GNU Lesser General Public License.
 * 
 * If you copy or distribute the program, you must accompany it with the complete 
 * corresponding machine-readable source code or with a written offer, valid for at 
 * least three years, to furnish the complete corresponding machine-readable source code. 
 * 
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * Description : Interface with PixlrEditor, online image editor. Very powerfull!
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

require_once(INSTALL_PATH."/server/classes/class.HttpClient.php");

class PixlrEditor extends AJXP_Plugin {

	public function switchAction($action, $httpVars, $filesVars){
		
		if(!isSet($this->actions[$action])) return false;
    	
		$repository = ConfService::getRepository();
		if(!$repository->detectStreamWrapper(true)){
			return false;
		}
		
		$streamData = $repository->streamData;
    	$destStreamURL = $streamData["protocol"]."://".$repository->getId();
		    	
		if($action == "post_to_server"){	
					
			$file = base64_decode(AJXP_Utils::decodeSecureMagic($httpVars["file"]));
			$target = base64_decode($httpVars["parent_url"])."/plugins/editor.pixlr";
			$tmp = call_user_func(array($streamData["classname"], "getRealFSReference"), $destStreamURL.$file);
			$fData = array("tmp_name" => $tmp, "name" => urlencode(basename($file)), "type" => "image/jpg");
			$httpClient = new HttpClient("pixlr.com");
			//$httpClient->setDebug(true);
			$postData = array();							
			$httpClient->setHandleRedirects(false);
			$params = array(
				"referrer"	=> "AjaXplorer",
				"method"	=> "get",
				"loc"		=> ConfService::getLanguage(),
				"target"	=> $target."/fake_save_pixlr.php",
				"exit"		=> $target."/fake_close_pixlr.php",
				"title"		=> urlencode(basename($file)),
				"locktarget"=> "false",
				"locktitle" => "true",
				"locktype"	=> "source"
			);
			$httpClient->postFile("/editor/", $params, "image", $fData);
			$loc = $httpClient->getHeader("location");
			header("Location:$loc");
			
		}else if($action == "retrieve_pixlr_image"){
			$file = AJXP_Utils::decodeSecureMagic($httpVars["original_file"]);
			$url = $httpVars["new_url"];
			$urlParts = parse_url($url);
			$query = $urlParts["query"];
			$params = array();
			$parameters = parse_str($query, $params);

			$image = $params['image'];
			/*
			$type = $params['type'];
			$state = $params['state'];
			$filename = $params['title'];		
			*/
				
			if (strpos($image, "pixlr.com") == 0){
				throw new AJXP_Exception("Invalid Referrer");
			}
			$headers = get_headers($image, 1);
			$content_type = explode("/", $headers['Content-Type']);
			if ($content_type[0] != "image"){
				throw new AJXP_Exception("File Type");
			}
			
			$orig = fopen($image, "r");
			$target = fopen($destStreamURL.$file, "w");
			while(!feof($orig)){
				fwrite($target, fread($orig, 4096));
			}
			fclose($orig);
			fclose($target);
			
			header("Content-Type:text/plain");
			print($mess[115]);
			
		}
		
		
		return ;
				
	}
	
}
?>