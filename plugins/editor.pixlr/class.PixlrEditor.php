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
 * Uses Pixlr.com service to edit images online.
 */
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
          
      $file = base64_decode($httpVars["file"]);
      $file = SystemTextEncoding::magicDequote(AJXP_Utils::securePath($file));
      $target = base64_decode($httpVars["parent_url"])."/plugins/editor.pixlr";
      $tmp = call_user_func(array($streamData["classname"], "getRealFSReference"), $destStreamURL.$file);      
      $tmp = SystemTextEncoding::fromUTF8($tmp);
      $fData = array("tmp_name" => $tmp, "name" => urlencode(basename($file)), "type" => "image/jpg");
      //var_dump($fData);
      $httpClient = new HttpClient("pixlr.com");
      //$httpClient->setDebug(true);
      $postData = array();              
      $httpClient->setHandleRedirects(false);
      $saveTarget = $target."/fake_save_pixlr.php";
      if($this->pluginConf["CHECK_SECURITY_TOKEN"]){
          $saveTarget = $target."/fake_save_pixlr_".md5($httpVars["secure_token"]).".php";
      }
      $params = array(
        "referrer"  => "AjaXplorer",
        "method"  => "get",
        "loc"    => ConfService::getLanguage(),
        "target"  => $saveTarget,
        "exit"    => $target."/fake_close_pixlr.php",
        "title"    => urlencode(basename($file)),
        "locktarget"=> "false",
        "locktitle" => "true",
        "locktype"  => "source"
      );
      $httpClient->postFile("/editor/", $params, "image", $fData);
      $loc = $httpClient->getHeader("location");
      header("Location:$loc");
      
    }else if($action == "retrieve_pixlr_image"){
      $file = AJXP_Utils::decodeSecureMagic($httpVars["original_file"]);
      $url = $httpVars["new_url"];
      $urlParts = parse_url($url);
      $query = $urlParts["query"];
        if($this->pluginConf["CHECK_SECURITY_TOKEN"]){
            $scriptName = basename($urlParts["path"]);
            $token = str_replace(array("fake_save_pixlr_", ".php"), "", $scriptName);
            if($token != md5($httpVars["secure_token"])){
                throw new AJXP_Exception("Invalid Token, this could mean some security problem!");
            }
        }
      $params = array();
      parse_str($query, $params);

      $image = $params['image'];      
      $headers = get_headers($image, 1);
      $content_type = explode("/", $headers['Content-Type']);
      if ($content_type[0] != "image"){
        throw new AJXP_Exception("Invalid File Type");
      }
      
      $orig = fopen($image, "r");
      $target = fopen($destStreamURL.$file, "w");
      while(!feof($orig)){
        fwrite($target, fread($orig, 4096));
      }
      fclose($orig);
      fclose($target);
      
      //header("Content-Type:text/plain");
      //print($mess[115]);
      
    }
    
    
    return ;
        
  }
  
}
?>