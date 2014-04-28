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
 *
 * This functions are necessary to implement the bridge between Pydio
 * and other CMS's.
 */

/**
 * @package AjaXplorer_Plugins
 * @subpackage Auth
 *
 * @param HttpClient $client
 * @return array
 */
function extractResponseCookies($client)
{
    //AJXP_Logger::debug(print_r($client, true));
    $cooks = $client->getHeader("set-cookie");
    if(empty($cooks)) return array();
    if (is_string($cooks)) {
        $cooks = array($cooks);
    }
    $cookies = array();
    foreach ($cooks as $cookieString) {
        list($name,$value) = explode("=", $cookieString);
        $ar = explode(";", $value);
        $value = array_shift($ar);
        $cookies[$name] = $value;
    }
    return $cookies;
}

function wordpress_remote_auth($host, $uri, $login, $pass, $formId = "")
{
    $client = new HttpClient($host);
    $client->setHandleRedirects(false);
    $client->setHeadersOnly(true);
    $client->setCookies(array("wordpress_test_cookie"=>"WP+Cookie+check"));
    $res = $client->post($uri, array(
        "log" => $login,
        "pwd" => $pass,
        "wp-submit" => "Log In",
        "testcookie" => 1)
    );
    $newCookies = extractResponseCookies($client);
    if (isSet($newCookies["AjaXplorer"])) {
        return $newCookies;
    }
    return "";
}

function joomla_remote_auth($host, $uri, $login, $pass, $formId = "")
{
    $client = new HttpClient($host);
    $client->setHandleRedirects(false);
    $res = $client->get($uri);
    $content = $client->getContent();
    $postData = array(
           "username" => $login,
           "password" => $pass,
           "Submit"   => "Log in",
           "remember" => "yes"
       );
    $xmlDoc = @DOMDocument::loadHTML($content);
    if ($xmlDoc === false) {
        $pos1 = strpos($content, "<form ");
        $pos2 = strpos($content, "</form>", $pos1);
        $content = substr($content, $pos1, $pos2 + "7" - $pos1);
        $xmlDoc = @DOMDocument::loadHTML($content);
    }
    if ($xmlDoc !== false) {
           $xPath = new DOMXPath($xmlDoc);
           if($formId == "") $formId = "login-form";
           $nodes = $xPath->query('//form[@id="'.$formId.'"]');
           if (!$nodes->length) {
               return "";
           }
           $form = $nodes->item(0);
           $postUri = $form->getAttribute("action");
           $hiddens = $xPath->query('.//input[@type="hidden"]', $form);
        foreach ($hiddens as $hiddenNode) {
               $postData[$hiddenNode->getAttribute("name")] = $hiddenNode->getAttribute("value");
           }
    } else {
        // Grab all inputs and hardcode $postUri.
        if (preg_match_all("<input type=\"hidden\" name=\"(.*)\" value=\"(.*)\">", $content, $matches)) {
            foreach ($matches[0] as $key => $match) {
                $postData[$matches[1][$key]] = $matches[2][$key];
            }
            $postUri = "/login-form";
        }
    }
    //AJXP_Logger::debug("Carry on ". $hiddens->length);
    $client->setHandleRedirects(false);
    $client->setHeadersOnly(true);
    $client->setCookies(extractResponseCookies($client));
    $res2 = $client->post($postUri, $postData);
    $newCookies = extractResponseCookies($client);
    if (isSet($newCookies["AjaXplorer"])) {
        return $newCookies;
    }
    return "";
}

function drupal_remote_auth($host, $uri, $login, $pass, $formId = "")
{
    $client = new HttpClient($host);
    $client->setHandleRedirects(false);
    $res = $client->get($uri);
    $content = $client->getContent();
    $xmlDoc = DOMDocument::loadHTML($content);
    $xPath = new DOMXPath($xmlDoc);
    if($formId == "") $formId = "user-login-form";
    $nodes = $xPath->query('//form[@id="'.$formId.'"]');
    if (!$nodes->length) {
        return "";
    }
    $form = $nodes->item(0);
    $postUri = $form->getAttribute("action");
    $hiddens = $xPath->query('.//input[@type="hidden"]', $form);
    AJXP_Logger::debug(__CLASS__,__FUNCTION__,"Carry on Drupal hiddens ". $hiddens->length);
    $postData = array(
        "name" => $login,
        "pass" => $pass,
        "Submit"   => "Log in"
    );
    foreach ($hiddens as $hiddenNode) {
        $postData[$hiddenNode->getAttribute("name")] = $hiddenNode->getAttribute("value");
    }
    $client->setHandleRedirects(false);
    $client->setHeadersOnly(true);
    $client->setCookies(extractResponseCookies($client));
    $res2 = $client->post($postUri, $postData);
    $newCookies = extractResponseCookies($client);
    if (isSet($newCookies["AjaXplorer"])) {
        return $newCookies;
    }
    return "";
}
