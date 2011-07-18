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
 * Description : Bitly shortener for publiclet generation
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

class BitlyShortener extends AJXP_Plugin {
			
	public function postProcess($action, $httpVars, $params){
        $url = $params["ob_output"];
        if(!isSet($this->pluginConf["BITLY_USER"]) || !isSet($this->pluginConf["BITLY_APIKEY"])){
            print($url);
            AJXP_Logger::logAction("error", "Bitly Shortener : you must drop the conf.shorten.bitly.inc file inside conf.php and set the login/api key!");
            return;
        }
        $bitly_login = $this->pluginConf["BITLY_USER"];
        $bitly_api = $this->pluginConf["BITLY_APIKEY"];
        $format = 'json';
        $version = '2.0.1';
        $bitly = 'http://api.bit.ly/shorten?version='.$version.'&longUrl='.urlencode($url).'&login='.$bitly_login.'&apiKey='.$bitly_api.'&format='.$format;
        $response = file_get_contents($bitly);
        $json = json_decode($response, true);
        if(isSet($json['results'][$url]['shortUrl'])){
            print($json['results'][$url]['shortUrl']);
        }else{
            print($url);
        }
	}

}

?>