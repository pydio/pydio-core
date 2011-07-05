<?php

define("ZOHO_API_KEY", "e9b144a8a1c29c0c3152ef921a12f1e2");
define("ZOHO_SECRET_KEY", "e008213e43df0b89f59285d319b74a59");

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
 * Description : Zoho plugin. 2011 Pawel Wolniewicz http://innodevel.net/
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

require_once(INSTALL_PATH."/server/classes/class.HttpClient.php");

class ZohoEditor extends AJXP_Plugin {

	public function switchAction($action, $httpVars, $filesVars){
		
		if(!isSet($this->actions[$action])) return false;
    	
		$repository = ConfService::getRepository();
		if(!$repository->detectStreamWrapper(true)){
			return false;
		}
		
		$streamData = $repository->streamData;
    	$destStreamURL = $streamData["protocol"]."://".$repository->getId();
		    	
		if($action == "post_to_server"){	
					
			$file = base64_decode($httpVars["file"]);
			$file = SystemTextEncoding::magicDequote(AJXP_Utils::securePath($file));
			$target = base64_decode($httpVars["parent_url"])."/plugins/editor.zoho";
			$tmp = call_user_func(array($streamData["classname"], "getRealFSReference"), $destStreamURL.$file);			
			$tmp = SystemTextEncoding::fromUTF8($tmp);
			$fData = array("tmp_name" => $tmp, "name" => urlencode(basename($file)));
			$extension = strtolower(pathinfo(urlencode(basename($file)), PATHINFO_EXTENSION));

			$httpClient = new HttpClient("export.writer.zoho.com");
			$postData = array();							
			$httpClient->setHandleRedirects(false);

			$params = array(
			'id' => $tmp,
			'apikey' => ZOHO_API_KEY,
			'output' => 'url',
			'lang' => "en",
			'skey'=> ZOHO_SECRET_KEY,
			'filename' => urlencode(basename($file)),
			'persistence' => 'false',
			 'format' => $extension,
			'mode' => 'normaledit',
			"saveurl" => $target."/save_zoho.php"
			);


			$langcode = "en";
			$httpClient->postFile("/remotedoc.im", $params, "content", $fData);
			$result = $httpClient->getContent();
			$result = trim($result);
			$matchlines = explode("\n", $result);
			$url = explode("URL=", $matchlines[0]);
			header("Location: ".$url[1]);

			
		}		
		
		return ;
				
	}
	
}
?>
