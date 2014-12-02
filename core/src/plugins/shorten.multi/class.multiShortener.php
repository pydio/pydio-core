<?php
/*
* Multi shortener plugin for ajaXplorer by FrenandoAloso
*         based in bit.ly plugin
*/

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Multiple implementation to choose from various webservices
 * @package AjaXplorer_Plugins
 * @subpackage Shorten
 */
class multiShortener extends AJXP_Plugin
{
    public function postProcess($action, $httpVars, $params)
    {
        $type = $this->getFilteredOption("SHORTEN_TYPE");
        if(empty($type)) return;
        $jsonData = json_decode($params["ob_output"], true);
        $elementId = -1;
        if ($jsonData != false) {
            $url = $jsonData["publiclet_link"] ;
            $elementId = $jsonData["element_id"];
        } else {
            $url = $params["ob_output"];
        }

        switch (intval($type["shorten_type"])) {
            case 0:
                if (!isSet($type["ADFLY_TYPE"]) || !isSet($type["ADFLY_APIKEY"]) || !isSet($type["ADFLY_UID"]) || !isSet($type["ADFLY_DOMAIN"])) {
                    print($url);
                    $this->logError("Config", "adFly Shortener : you must set the api key!");
                    return;
                }
                $adfly_type = $type["ADFLY_TYPE"];
                $adfly_api = $type["ADFLY_APIKEY"];
                $adfly_uid = $type["ADFLY_UID"];
                $adfly_dom = $type["ADFLY_DOMAIN"];
                $adfly = 'http://api.adf.ly/api.php?key='.$adfly_api.'&uid='.$adfly_uid.'&advert_type='.$adfly_type.'&domain='.$adfly_dom.'&url='.urlencode($url);
                $response = AJXP_Utils::getRemoteContent($adfly);
                $response = strip_tags($response, '<body>');
                $response = strip_tags($response);
                if (isSet($response)) {
                    print($response);
                    $this->updateMetaShort($httpVars["file"], $elementId, $response);
                } else {
                    print($url);
                }
                break;

            case 1:
                if (!isSet($type["BITLY_USER"]) || !isSet($type["BITLY_APIKEY"])) {
                    print($url);
                    $this->logError("Config", "Bitly Shortener : you must drop the conf.shorten.bitly.inc file inside conf.php and set the login/api key!");
                    return;
                }
                $bitly_login = $type["BITLY_USER"];
                $bitly_api = $type["BITLY_APIKEY"];
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
                break;

            case 2:
                if (!isSet($type["GOOGL_APIKEY"])) {
                    print($url);
                    $this->logError("Config", "Goo.gl Shortener : you must set the api key!");
                    return;
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

                $goourl = 'https://www.googleapis.com/urlshortener/v1/url';
                $context  = stream_context_create( $options );
                $result = file_get_contents( $goourl, false, $context );
                $json = (array) json_decode( $result );
                if (isSet($json['id'])) {
                    print($json['id']);
                    $this->updateMetaShort($httpVars["file"], $elementId, $json['id']);
                } else {
                    print($url);
                }
                break;

            case 3:
                if (!isSet($type["POST_APIKEY"])) {
                    print($url);
                    $this->logError("Config", "po.st Shortener : you must set the api key!");
                    return;
                }
                $post_api = $type["POST_APIKEY"];
                $post = 'http://po.st/api/shorten?longUrl='.urlencode($url).'&apiKey='.$post_api.'&format=txt';
                $response = AJXP_Utils::getRemoteContent($post);
                if (isSet($response)) {
                    print($response);
                    $this->updateMetaShort($httpVars["file"], $elementId, $response);
                } else {
                    print($url);
                }
                break;

            case 4:
                if (!isSet($type["YOURLS_DOMAIN"])) {
                    print($url);
                    $this->logError("Config", "yourls Shortener : you must set the domain name");
                    return;
                }
                if (!isSet($type["YOURLS_APIKEY"])) {
                    print($url);
                    $this->logError("Config", "yourls Shortener : you must set the api key");
                    return;
                }
                $useidn = false;
                if (isSet($type["YOURLS_USEIDN"])) {
                    $useidn = $type["YOURLS_USEIDN"];
                }
                $yourls_domain = $type["YOURLS_DOMAIN"];
                $yourls_api = $type["YOURLS_APIKEY"];
                $yourls = 'http://'.$yourls_domain.'/yourls-api.php?signature='.$yourls_api.'&action=shorturl&format=simple&url='.urlencode($url);
                $response = AJXP_Utils::getRemoteContent($yourls);
                if (isSet($response)) {
                    $shorturl = $response;
                    if ($useidn) {
                        // WARNING: idn_to_utf8 requires php-idn module.
                        // WARNING: http_build_url requires php-pecl-http module.
                        $purl = parse_url($shorturl);
                        $purl['host'] = idn_to_utf8($purl['host']);
                        $shorturl = http_build_url($purl);
                    }
                    print($shorturl);
                    $this->updateMetaShort($httpVars["file"], $elementId, $shorturl);
                } else {
                    print($url);
                }
                break;
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
