<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\LinkShortener;

use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Utils\FileHelper;

use Pydio\Core\PluginFramework\Plugin;
use Pydio\Share\Model\ShareLink;
use Pydio\Share\View\PublicAccessManager;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Multiple implementation to choose from various webservices
 * @package AjaXplorer_Plugins
 * @subpackage Shorten
 */
class MultiShortener extends Plugin
{

    /**
     * @param ContextInterface $ctx
     * @param ShareLink $shareObject
     * @param PublicAccessManager $publicAccessManager
     */
    public function processShortenHook(ContextInterface $ctx, &$shareObject, $publicAccessManager){
        
        $existingShortForm = $shareObject->getShortFormUrl();
        if(empty($existingShortForm)){
            $url = $publicAccessManager->buildPublicLink($shareObject->getHash());
            $shortForm = $this->generateLink($ctx, $url);
            if(!empty($shortForm)){
                $shareObject->setShortFormUrl($shortForm);
                $shareObject->save();
            }
        }
    }

    /**
     * @param ContextInterface $ctx
     * @param $url
     * @return bool|mixed|null|string
     */
    protected function generateLink(ContextInterface $ctx, $url){

        $type = $this->getContextualOption($ctx, "SHORTEN_TYPE");
        if(empty($type)) return null;
        switch (intval($type["shorten_type"])) {
            case 0:
                if (!isSet($type["ADFLY_TYPE"]) || !isSet($type["ADFLY_APIKEY"]) || !isSet($type["ADFLY_UID"]) || !isSet($type["ADFLY_DOMAIN"])) {
                    $this->logError("Config", "adFly Shortener : you must set the api key!");
                    break;
                }
                $adfly_type = $type["ADFLY_TYPE"];
                $adfly_api = $type["ADFLY_APIKEY"];
                $adfly_uid = $type["ADFLY_UID"];
                $adfly_dom = $type["ADFLY_DOMAIN"];
                $adfly = 'http://api.adf.ly/api.php?key='.$adfly_api.'&uid='.$adfly_uid.'&advert_type='.$adfly_type.'&domain='.$adfly_dom.'&url='.urlencode($url);
                $response = FileHelper::getRemoteContent($adfly);
                $response = strip_tags($response, '<body>');
                $response = strip_tags($response);
                if (isSet($response)) {
                    return $response;
                }
                break;

            case 1:
                if (!isSet($type["BITLY_USER"]) || !isSet($type["BITLY_APIKEY"])) {
                    $this->logError("Config", "Bitly Shortener : you must drop the conf.shorten.bitly.inc file inside conf.php and set the login/api key!");
                    break;
                }
                $bitly_login = $type["BITLY_USER"];
                $bitly_api = $type["BITLY_APIKEY"];
                $format = 'json';
                $version = '2.0.1';
                $bitly = 'http://api.bit.ly/shorten?version='.$version.'&longUrl='.urlencode($url).'&login='.$bitly_login.'&apiKey='.$bitly_api.'&format='.$format;
                $response = FileHelper::getRemoteContent($bitly);
                $json = json_decode($response, true);
                if (isSet($json['results'][$url]['shortUrl'])) {
                    return $json['results'][$url]['shortUrl'];
                }
                break;

            case 2:
                if (!isSet($type["GOOGL_APIKEY"])) {
                    $this->logError("Config", "Goo.gl Shortener : you must set the api key!");
                    break;
                }
                $data = array(
                    'longUrl' => $url,
                    'key' => $type["GOOGL_APIKEY"]
                );

                $options = array(
                    'http' => array(
                        'method'  => 'POST',
                        'content' => json_encode( $data ),
                        'header'=>  "Content-Type: application/json\r\n" .
                            "Accept: application/json\r\n"
                    )
                );

                $goourl = 'https://www.googleapis.com/urlshortener/v1/url?key='.$type["GOOGL_APIKEY"];
                $context  = stream_context_create( $options );
                $result = file_get_contents( $goourl, false, $context );
                $json = (array) json_decode( $result );
                if (isSet($json['id'])) {
                    return $json['id'];
                }
                break;

            case 3:
                if (!isSet($type["POST_APIKEY"])) {
                    $this->logError("Config", "po.st Shortener : you must set the api key!");
                    break;
                }
                $post_api = $type["POST_APIKEY"];
                $post = 'http://po.st/api/shorten?longUrl='.urlencode($url).'&apiKey='.$post_api.'&format=txt';
                $response = FileHelper::getRemoteContent($post);
                if (isSet($response)) {
                    return $response;
                }
                break;

            case 4:
                if (!isSet($type["YOURLS_DOMAIN"])) {
                    $this->logError("Config", "yourls Shortener : you must set the domain name");
                    return null;
                }
                if (!isSet($type["YOURLS_APIKEY"])) {
                    $this->logError("Config", "yourls Shortener : you must set the api key");
                    return null;
                }
                $useidn = false;
                if (isSet($type["YOURLS_USEIDN"])) {
                    $useidn = $type["YOURLS_USEIDN"];
                }
                $yourls_domain = $type["YOURLS_DOMAIN"];
                $yourls_api = $type["YOURLS_APIKEY"];
                $yourls = 'http://'.$yourls_domain.'/yourls-api.php?signature='.$yourls_api.'&action=shorturl&format=simple&url='.urlencode($url);
                $response = FileHelper::getRemoteContent($yourls);
                if (isSet($response)) {
                    $shorturl = $response;
                    if ($useidn) {
                        // WARNING: idn_to_utf8 requires php-idn module.
                        // WARNING: http_build_url requires php-pecl-http module.
                        $purl = parse_url($shorturl);
                        $purl['host'] = idn_to_utf8($purl['host']);
                        $shorturl = http_build_url($purl);
                    }
                    return $shorturl;
                }
                break;
            case 5:
                if (!isSet($type["POLR_DOMAIN"])) {
                    $this->logError("Config", "polr Shortener : you must set the domain name");
                    return null;
                }
                if (!isSet($type["POLR_APIKEY"])) {
                    $this->logError("Config", "polr Shortener : you must set the api key");
                    return null;
                }
                $polr_domain = $type["POLR_DOMAIN"];
                $polr_api = $type["POLR_APIKEY"];
                $polr = 'http://'.$polr_domain.'/api/v2/action/shorten?key='.$polr_api.'&url='.$url;
                $response = FileHelper::getRemoteContent($polr);
                if (isSet($response)) {
                    $shorturl = $response;
                    if ($useidn) {
                        // WARNING: idn_to_utf8 requires php-idn module.
                        // WARNING: http_build_url requires php-pecl-http module.
                        $purl = parse_url($shorturl);
                        $purl['host'] = idn_to_utf8($purl['host']);
                        $shorturl = http_build_url($purl);
                    }
                    return $shorturl;
                }
                break;

            default:
                break;
        }

        return null;

    }
    
}
