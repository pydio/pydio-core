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
 * Simple non-fonctionnal plugin for demoing pre/post processes hooks
 * @package AjaXplorer_Plugins
 * @subpackage Action
 */
class PluginSkeleton extends AJXP_Plugin
{
    /**
     * @param DOMNode $contribNode
     * @return void
     */
    public function parseSpecificContributions(&$contribNode)
    {
        if($contribNode->nodeName != "client_configs") return;
        // This demonstrate how the tight integration of XML, PHP and JS Client make plugins programming
        // very flexible. Here if the plugin configuration SHOW_CUSTOM_FOOTER is set to false, we
        // dynamically remove some XML from the manifest before it's sent to the client, thus disabling
        // the custom footer. In the other case, we update the XML Node content with the CUSTOM_FOOTER_CONTENT
        $actionXpath=new DOMXPath($contribNode->ownerDocument);
        $footerTplNodeList = $actionXpath->query('template[@name="bottom"]', $contribNode);
        $footerTplNode = $footerTplNodeList->item(0);
        if (!$this->getFilteredOption("SHOW_CUSTOM_FOOTER")) {
            $contribNode->removeChild($footerTplNode);
        } else {
            $content = $this->getFilteredOption("CUSTOM_FOOTER_CONTENT");
            $content = str_replace("\\n", "<br>", $content);
            $cdata = '<div id="optional_bottom_div" style="font-family:arial;padding:10px;">'.$content.'</div>';
            $cdataSection = $contribNode->ownerDocument->createCDATASection($cdata);
            foreach($footerTplNode->childNodes as $child) $footerTplNode->removeChild($child);
            $footerTplNode->appendChild($cdataSection);
        }
    }

    /**
     * @param String $action
     * @param Array $httpVars
     * @param Array $fileVars
     * @return void
     */
    public function receiveAction($action, $httpVars, $fileVars)
    {
        if ($action == "my_skeleton_button_frame") {
            header("Content-type:text/html");
            print("<p>This is a <b>dynamically</b> generated content. It is sent back to the client by the server, thus it can be the result of what you want : a query to a remote API, a constant string (like it is now), or any specific data stored by the application...</p>");
            print("<p>Here the server sends back directly HTML that is displayed by the client, but other formats can be used when it comes to more structured data, allowing the server to stay focus on the data and the client to adapt the display : <ul><li>JSON : use <b>json_encode/json_decode</b> on the PHP side, and <b>transport.reponseJSON</b> on the client side</li><li>XML : print your own XML on the php side, and use <b>transport.responseXML</b> on the client side.</li><li>The advantage of HTML can also be used to send javascript instruction to the client.</li></ul></p>");
        }
    }

    /**
     * This is an example of filter that can be hooked to the AJXP_VarsFilter,
     * for using your own custom variables in the repositories configurations.
     * In this example, this variable does exactly what the current AJXP_USER variable do.
     * Thus, once hooked, you can use CUSTOM_VARIABLE_USER in e.g. a repository PATH, and
     * build this path dynamically depending on the current user logged.
     * Contrary to other standards hooks like node.info, this cannot be added via XML manifest
     * as it happen too early in the application, so it must be declared directly inside the conf.php
     *
     * @param String $value
     */
    public static function filterVars(&$value)
    {
        if (AuthService::getLoggedUser() != null) {
            $value = str_replace("CUSTOM_VARIABLE_USER", AuthService::getLoggedUser()->getId(), $value);
        }
    }
}
