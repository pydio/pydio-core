<?php
/**
 * 
 * @param HttpClient $client
 * @return array
 */
function extractResponseCookies($client){
	AJXP_Logger::debug(print_r($client, true));
	$cooks = $client->getHeader("set-cookie");
	if(empty($cooks)) return array();
	if(is_string($cooks)){
		$cooks = array($cooks);
	}
	$cookies = array();
	foreach ($cooks as $cookieString){
		list($name,$value) = explode("=", $cookieString);
		$value = array_shift(explode(";", $value));
		$cookies[$name] = $value;
	}
	return $cookies;
}

function wordpress_remote_auth($host, $uri, $login, $pass, $formId = ""){
	$client = new HttpClient($host);
	$client->setHandleRedirects(false);
	$client->setHeadersOnly(true);		
	$res = $client->post($uri."/wp-login.php", array(
		"log" => $login, 
		"pwd" => $pass, 
		"wp-submit" => "Log In", 
		"testcookie" => 1)
	);
	$newCookies = extractResponseCookies($client);
	if(isSet($newCookies["AjaXplorer"])){
		return $newCookies["AjaXplorer"];
	}
	return "";
}

function joomla_remote_auth($host, $uri, $login, $pass, $formId = ""){
	
	$client = new HttpClient($host);
	$client->setHandleRedirects(false);
	$res = $client->get($uri);
	$content = $client->getContent();
	$xmlDoc = DOMDocument::loadHTML($content);
	$xPath = new DOMXPath($xmlDoc);
	if($formId == "") $formId = "login-form";
	$nodes = $xPath->query('//form[@id="'.$formId.'"]');
	if(!$nodes->length) {
		return "";
	}
	$form = $nodes->item(0);
	$postUri = $form->getAttribute("action");
	$hiddens = $xPath->query('//input[@type="hidden"]', $form);
	AJXP_Logger::debug("Carry on ". $hiddens->length);
	$postData = array(
		"username" => $login, 
		"password" => $pass,
		"Submit"   => "Log in",
		"remember" => "yes"
	);
	foreach($hiddens as $hiddenNode){
		$postData[$hiddenNode->getAttribute("name")] = $hiddenNode->getAttribute("value");
	}
	$client->setHandleRedirects(false);
	$client->setHeadersOnly(true);
	$client->setCookies(extractResponseCookies($client));
	$res2 = $client->post($postUri, $postData);
	$newCookies = extractResponseCookies($client);
	if(isSet($newCookies["AjaXplorer"])){
		return $newCookies["AjaXplorer"];
	}
	return "";
}

function drupal_remote_auth($host, $uri, $login, $pass, $formId = ""){
	
	$client = new HttpClient($host);
	$client->setHandleRedirects(false);
	$res = $client->get($uri);
	$content = $client->getContent();
	$xmlDoc = DOMDocument::loadHTML($content);
	$xPath = new DOMXPath($xmlDoc);
	if($formId == "") $formId = "user-login-form";
	$nodes = $xPath->query('//form[@id="'.$formId.'"]');
	if(!$nodes->length) {
		return "";
	}
	$form = $nodes->item(0);
	$postUri = $form->getAttribute("action");
	$hiddens = $xPath->query('//input[@type="hidden"]', $form);
	AJXP_Logger::debug("Carry on Drupal hiddens ". $hiddens->length);
	$postData = array(
		"name" => $login, 
		"pass" => $pass,
		"Submit"   => "Log in"
	);
	foreach($hiddens as $hiddenNode){
		$postData[$hiddenNode->getAttribute("name")] = $hiddenNode->getAttribute("value");
	}
	$client->setHandleRedirects(false);
	$client->setHeadersOnly(true);
	$client->setCookies(extractResponseCookies($client));
	$res2 = $client->post($postUri, $postData);
	$newCookies = extractResponseCookies($client);
	if(isSet($newCookies["AjaXplorer"])){
		return $newCookies["AjaXplorer"];
	}
	return "";
}
?>