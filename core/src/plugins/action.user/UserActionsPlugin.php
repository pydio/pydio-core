<?php
/*
 * Copyright 2007-2017 Abstrium <contact (at) pydio.com>
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
 * The latest code can be found at <https://pydio.com/>.
 */


namespace Pydio\Action;

use Pydio\Core\Model\ContextInterface;
use Pydio\Core\PluginFramework\Plugin;

defined('AJXP_EXEC') or die('Access not allowed');
/**
 * Class UserActionsPlugin
 * For backward compatibility, check "DASH_DISABLE_ADDRESS_BOOK" parameter and disable action accordingly.
 */
class UserActionsPlugin extends Plugin {

    /**
     * @param ContextInterface $ctx
     * @param \DOMNode $contribNode
     */
    public function parseSpecificContributions(ContextInterface $ctx, \DOMNode &$contribNode){
        $disableAddressBook = $this->getContextualOption($ctx, "DASH_DISABLE_ADDRESS_BOOK") === true;
        if($contribNode->nodeName == "actions" && $disableAddressBook){
            // remove template_part for orbit_content
            $xPath=new \DOMXPath($contribNode->ownerDocument);
            $tplNodeList = $xPath->query('action[@name="open_address_book"]', $contribNode);
            if(!$tplNodeList->length) return ;
            $contribNode->removeChild($tplNodeList->item(0));
        }
        parent::parseSpecificContributions($ctx, $contribNode);
    }


}
