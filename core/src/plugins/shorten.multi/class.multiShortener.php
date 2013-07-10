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
class multiShortener extends AJXP_Plugin {		
	public function postProcess($action, $httpVars, $params){
        $type = $this->getFilteredOption("SHORTEN_TYPE");
        if(empty($type)) return;
        switch(intval($type["shorten_type"])) {
            case 0:
                $url = $params["ob_output"];
                if(!isSet($type["ADFLY_TYPE"]) || !isSet($type["ADFLY_APIKEY"]) || !isSet($type["ADFLY_UID"]) || !isSet($type["ADFLY_DOMAIN"])){
                    print($url);
                    AJXP_Logger::logAction("error", "adFly Shortener : you must set the api key!");
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
                if(isSet($response)){
                    print($response);
                    $this->updateMetaShort($httpVars["file"], $response);
                }else{
                    print($url);
                }
                break;

            case 1:
                $url = $params["ob_output"];
                if(!isSet($type["BITLY_USER"]) || !isSet($type["BITLY_APIKEY"])){
                    print($url);
                    AJXP_Logger::logAction("error", "Bitly Shortener : you must drop the conf.shorten.bitly.inc file inside conf.php and set the login/api key!");
                    return;
                }
                $bitly_login = $type["BITLY_USER"];
                $bitly_api = $type["BITLY_APIKEY"];
                $format = 'json';
                $version = '2.0.1';
                $bitly = 'http://api.bit.ly/shorten?version='.$version.'&longUrl='.urlencode($url).'&login='.$bitly_login.'&apiKey='.$bitly_api.'&format='.$format;
                $response = AJXP_Utils::getRemoteContent($bitly);
                $json = json_decode($response, true);
                if(isSet($json['results'][$url]['shortUrl'])){
                    print($json['results'][$url]['shortUrl']);
                    $this->updateMetaShort($httpVars["file"], $json['results'][$url]['shortUrl']);
                }else{
                    print($url);
                }
                break;

            case 2:
                $url = $params["ob_output"];
                if(!isSet($type["GOOGL_APIKEY"])){
                    print($url);
                    AJXP_Logger::logAction("error", "Goo.gl Shortener : you must set the api key!");
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
                $json = (array)json_decode( $result );
                if(isSet($json['id'])){
                    print($json['id']);
                    $this->updateMetaShort($httpVars["file"], $json['id']);
                }else{
                    print($url);
                }
                break;

            case 3:
                $url = $params["ob_output"];
                if(!isSet($type["POST_APIKEY"])){
                    print($url);
                    AJXP_Logger::logAction("error", "po.st Shortener : you must set the api key!");
                    return;
                }
                $post_api = $type["POST_APIKEY"];
                $post = 'http://po.st/api/shorten?longUrl='.urlencode($url).'&apiKey='.$post_api.'&format=txt';
                $response = AJXP_Utils::getRemoteContent($post);
                if(isSet($response)){
                    print($response);
                    $this->updateMetaShort($httpVars["file"], $response);
                }else{
                    print($url);
                }
                break;
				
		}

		
	}
    protected function updateMetaShort($file, $shortUrl){
        $driver = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("access");
        $streamData = $driver->detectStreamWrapper(false);
        $baseUrl = $streamData["protocol"]."://".ConfService::getRepository()->getId();
        $node = new AJXP_Node($baseUrl.$file);
        if($node->hasMetaStore()){
            $metadata = $node->retrieveMetadata(
                "ajxp_shared",
                true,
                AJXP_METADATA_SCOPE_REPOSITORY
            );
            $metadata["short_form_url"] = $shortUrl;
            $node->setMetadata(
                "ajxp_shared",
                $metadata,
                true,
                AJXP_METADATA_SCOPE_REPOSITORY
            );
        }
    }

}