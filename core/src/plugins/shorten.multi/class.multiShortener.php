<?php
/*
* Multi shortener plugin for ajaXplorer by FrenandoAloso
*         based in bit.ly plugin
*/

defined('AJXP_EXEC') or die( 'Access not allowed');

class multiShortener extends AJXP_Plugin {		
	public function postProcess($action, $httpVars, $params){
		if(isset($this->pluginConf["SHORTEN_TYPE"]))
			switch($this->pluginConf["SHORTEN_TYPE"]) {
				case 0:
					$url = $params["ob_output"];
					if(!isSet($this->pluginConf["ADFLY_TYPE"]) || !isSet($this->pluginConf["ADFLY_APIKEY"]) || !isSet($this->pluginConf["ADFLY_UID"]) || !isSet($this->pluginConf["ADFLY_DOMAIN"])){
						print($url);
						AJXP_Logger::logAction("error", "adFly Shortener : you must set the api key!");
						return;
					}
					$adfly_type = $this->pluginConf["ADFLY_TYPE"];
					$adfly_api = $this->pluginConf["ADFLY_APIKEY"];
					$adfly_uid = $this->pluginConf["ADFLY_UID"];
					$adfly_dom = $this->pluginConf["ADFLY_DOMAIN"];
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
					if(!isSet($this->pluginConf["GOOGL_APIKEY"])){
						print($url);
						AJXP_Logger::logAction("error", "Goo.gl Shortener : you must set the api key!");
						return;
					}				
					$data = array(
						'longUrl' => $url,
						'key' => $this->pluginConf["GOOGL_APIKEY"]
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
					if(!isSet($this->pluginConf["POST_APIKEY"])){
						print($url);
						AJXP_Logger::logAction("error", "po.st Shortener : you must set the api key!");
						return;
					}
					$post_api = $this->pluginConf["POST_APIKEY"];
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
        $metaStore = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("metastore");
        if($metaStore !== false){
            $driver = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("access");
            $metaStore->initMeta($driver);
            $streamData = $driver->detectStreamWrapper(false);
            $baseUrl = $streamData["protocol"]."://".ConfService::getRepository()->getId();
            $node = new AJXP_Node($baseUrl.$file);
            $metadata = $metaStore->retrieveMetadata(
                $node,
                "ajxp_shared",
                true,
                AJXP_METADATA_SCOPE_REPOSITORY
            );
            $metadata["short_form_url"] = $shortUrl;
            $metaStore->setMetadata(
                $node,
                "ajxp_shared",
                $metadata,
                true,
                AJXP_METADATA_SCOPE_REPOSITORY
            );
        }

    }

}

?>
