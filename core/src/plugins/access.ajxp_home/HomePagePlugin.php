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
 *
 */
namespace Pydio\Access\Driver\DataProvider;
use DOMXPath;
use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Core\Model\ContextInterface;

defined('AJXP_EXEC') or die( 'Access not allowed');
/**
 * @package AjaXplorer_Plugins
 * @subpackage Access
 * @class userHome
 * Plugin to access the shared elements of the current user
 */
class HomePagePlugin extends AbstractAccessDriver
{

    /**
     * @param ContextInterface $ctx
     * @param \DOMNode $contribNode
     */
    public function parseSpecificContributions(ContextInterface $ctx, \DOMNode &$contribNode){
        parent::parseSpecificContributions($ctx, $contribNode);
        if($contribNode->nodeName == "client_configs"){
            $actionXpath=new DOMXPath($contribNode->ownerDocument);
            $gettingStartedList = $actionXpath->query('template[@name="tutorial_pane"]', $contribNode);
            if(!$gettingStartedList->length) return ;
            if($this->getContextualOption($ctx, "ENABLE_GETTING_STARTED") === false){
                $contribNode->removeChild($gettingStartedList->item(0));
            }else{
                $cdata = $gettingStartedList->item(0)->firstChild;
                $keys = array("URL_APP_IOSAPPSTORE", "URL_APP_ANDROID", "URL_APP_SYNC_WIN", "URL_APP_SYNC_MAC");
                $values = array();
                foreach($keys as $k) $values[] = $this->getContextualOption($ctx, $k);
                $newData = str_replace($keys, $values, $cdata->nodeValue);
                $newCData = $contribNode->ownerDocument->createCDATASection($newData);
                $gettingStartedList->item(0)->appendChild($newCData);
                $gettingStartedList->item(0)->replaceChild($newCData, $cdata);
            }
        }else if($contribNode->nodeName == "actions" && $this->getContextualOption($ctx, "DISPLAY_SERVER_QRCODE_ACTION") === false){
            $actionXpath = new DOMXPath($contribNode->ownerDocument);
            $actionList = $actionXpath->query('action[@name="display-server-qrcode"]', $contribNode);
            $contribNode->removeChild($actionList->item(0));
        }
    }

    /**
     * @param ContextInterface $ctx
     */
    protected function initRepository(ContextInterface $ctx){
    }
}
