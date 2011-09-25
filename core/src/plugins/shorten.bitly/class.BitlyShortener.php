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
 * Use a developer Bit.ly account to shorten publiclet links.
 */
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