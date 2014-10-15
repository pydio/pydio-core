<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 */

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Use a developer Bit.ly account to shorten publiclet links.
 * @package AjaXplorer_Plugins
 * @subpackage Shorten
 */
class BitlyShortener extends AJXP_Plugin
{
    public function postProcess($action, $httpVars, $params)
    {
        $jsonData = json_decode($params["ob_output"], true);
        if ($jsonData != false) {
            $url = $jsonData["publiclet_link"] ;
            $elementId = $jsonData["element_id"];
        } else {
            $url = $params["ob_output"];
            $elementId = -1;
        }

        $BITLY_USER = $this->getFilteredOption("BITLY_USER");
        $BITLY_APIKEY = $this->getFilteredOption("BITLY_APIKEY");

        if (empty($BITLY_USER) || empty($BITLY_APIKEY)) {
            print($url);
            $this->logError("Config", "Bitly Shortener : you must drop the conf.shorten.bitly.inc file inside conf.php and set the login/api key!");
            return;
        }
        $bitly_login = $BITLY_USER;
        $bitly_api = $BITLY_APIKEY;
        $format = 'json';
        $version = '2.0.1';
        $bitly = 'http://api.bit.ly/shorten?version='.$version.'&longUrl='.urlencode($url).'&login='.$bitly_login.'&apiKey='.$bitly_api.'&format='.$format;
        $response = AJXP_Utils::getRemoteContent($bitly);
        $json = json_decode($response, true);
        if (isSet($json['results'][$url]['shortUrl'])) {
            print($json['results'][$url]['shortUrl']);
            $this->updateMetaShort($httpVars["file"], $elementId, $json['results'][$url]['shortUrl']);
        } else {
            print($url);
        }
    }

    protected function updateMetaShort($file, $elementId, $shortUrl)
    {
        $driver = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("access");
        $streamData = $driver->detectStreamWrapper(false);
        $baseUrl = $streamData["protocol"]."://".ConfService::getRepository()->getId();
        $node = new AJXP_Node($baseUrl.$file);
        if ($node->hasMetaStore()) {
            $metadata = $node->retrieveMetadata(
                "ajxp_shared",
                true,
                AJXP_METADATA_SCOPE_REPOSITORY
            );
            if ($elementId != -1) {
                if (!is_array($metadata["element"][$elementId])) {
                    $metadata["element"][$elementId] = array();
                }
                $metadata["element"][$elementId]["short_form_url"] = $shortUrl;
            } else {
                if(isSet($metadata["shares"])){
                    $key = array_pop(array_keys($metadata["shares"]));
                    $metadata["shares"][$key]["short_form_url"] = $shortUrl;
                }else{
                    $metadata['short_form_url'] = $shortUrl;
                }
            }
            $node->setMetadata(
                "ajxp_shared",
                $metadata,
                true,
                AJXP_METADATA_SCOPE_REPOSITORY
            );
        }
    }

}
