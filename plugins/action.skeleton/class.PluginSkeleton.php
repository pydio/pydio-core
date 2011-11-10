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
 * Simple non-fonctionnal plugin for demoing pre/post processes hooks
 */
class PluginSkeleton extends AJXP_Plugin {

    /**
     * @param DOMNode $contribNode
     * @return void
     */
    public function parseSpecificContributions(&$contribNode){
        if($contribNode->nodeName != "client_configs") return;
        // This demonstrate how the tight integration of XML, PHP and JS Client make plugins programming
        // very flexible. Here if the plugin configuration SHOW_CUSTOM_FOOTER is set to false, we
        // dynamically remove some XML from the manifest before it's sent to the client, thus disabling
        // the custom footer. In the other case, we update the XML Node content with the CUSTOM_FOOTER_CONTENT
        $actionXpath=new DOMXPath($contribNode->ownerDocument);
        $footerTplNodeList = $actionXpath->query('template[@name="bottom"]', $contribNode);
        $footerTplNode = $footerTplNodeList->item(0);
        if(!$this->pluginConf["SHOW_CUSTOM_FOOTER"]){
            $contribNode->removeChild($footerTplNode);
        }else{
            $content = $this->pluginConf["CUSTOM_FOOTER_CONTENT"];
            $content = str_replace("\\n", "<br>", $content);
            $cdata = '<div id="optional_bottom_div" style="font-family:arial;padding:10px;">'.$content.'</div>';
            $cdataSection = $contribNode->ownerDocument->createCDATASection($cdata);
            foreach($footerTplNode->childNodes as $child) $footerTplNode->removeChild($child);
            $footerTplNode->appendChild($cdataSection);
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
	public static function filterVars(&$value){
		if(AuthService::getLoggedUser() != null){
			$value = str_replace("CUSTOM_VARIABLE_USER", AuthService::getLoggedUser()->getId(), $value);
		}
	}
}

?>