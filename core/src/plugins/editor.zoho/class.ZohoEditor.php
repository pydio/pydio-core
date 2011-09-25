<?php

define("ZOHO_API_KEY", "e9b144a8a1c29c0c3152ef921a12f1e2");
define("ZOHO_SECRET_KEY", "e008213e43df0b89f59285d319b74a59");

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
 *
 * Description : Zoho plugin. 2011 Pawel Wolniewicz http://innodevel.net/
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

require_once(AJXP_BIN_FOLDER."/class.HttpClient.php");

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
