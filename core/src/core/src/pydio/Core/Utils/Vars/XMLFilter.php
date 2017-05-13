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
namespace Pydio\Core\Utils\Vars;

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\IAjxpWrapperProvider;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Serializer\NodeXML;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\StatHelper;
use Pydio\Core\Utils\Vars\StringHelper;
use Pydio\Core\Services;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Utils\XMLHelper;


defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Filters XML data with pydio specific keywords
 * @package Pydio
 * @subpackage Core
 */
class XMLFilter
{
    
    /**
     * Dynamically replace XML keywords with their live values.
     * AJXP_SERVER_ACCESS, AJXP_MIMES_*,AJXP_ALL_MESSAGES, etc.
     * @static
     * @param string $xml
     * @param bool $stripSpaces
     * @return mixed
     */
    public static function resolveKeywords($xml, $stripSpaces = false)
    {
        $messages = LocaleService::getMessages();
        $confMessages = LocaleService::getConfigMessages();
        $matches = array();
        $xml = str_replace("AJXP_APPLICATION_TITLE", ConfService::getGlobalConf("APPLICATION_TITLE"), $xml);
        $xml = str_replace("AJXP_MIMES_EDITABLE", StatHelper::getAjxpMimes("editable"), $xml);
        $xml = str_replace("AJXP_MIMES_IMAGE", StatHelper::getAjxpMimes("image"), $xml);
        $xml = str_replace("AJXP_MIMES_AUDIO", StatHelper::getAjxpMimes("audio"), $xml);
        $xml = str_replace("AJXP_MIMES_ZIP", StatHelper::getAjxpMimes("zip"), $xml);
        $authDriver = ConfService::getAuthDriverImpl();
        if ($authDriver != NULL) {
            $loginRedirect = $authDriver->getLoginRedirect();
            $xml = str_replace("AJXP_LOGIN_REDIRECT", ($loginRedirect!==false?"'".$loginRedirect."'":"false"), $xml);
        }
        $xml = str_replace("AJXP_REMOTE_AUTH", "false", $xml);
        $xml = str_replace("AJXP_NOT_REMOTE_AUTH", "true", $xml);
        $xml = str_replace("AJXP_ALL_MESSAGES", "MessageHash=".json_encode(LocaleService::getMessages()).";", $xml);

        if (preg_match_all("/AJXP_MESSAGE(\[.*?\])/", $xml, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $messId = str_replace("]", "", str_replace("[", "", $match[1]));
                $xml = str_replace("AJXP_MESSAGE[$messId]", $messages[$messId], $xml);
            }
        }
        if (preg_match_all("/CONF_MESSAGE(\[.*?\])/", $xml, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $messId = str_replace(array("[", "]"), "", $match[1]);
                $message = $messId;
                if (array_key_exists($messId, $confMessages)) {
                    $message = $confMessages[$messId];
                }
                $xml = str_replace("CONF_MESSAGE[$messId]", StringHelper::xmlEntities($message), $xml);
            }
        }
        if (preg_match_all("/MIXIN_MESSAGE(\[.*?\])/", $xml, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $messId = str_replace(array("[", "]"), "", $match[1]);
                $message = $messId;
                if (array_key_exists($messId, $confMessages)) {
                    $message = $confMessages[$messId];
                }
                $xml = str_replace("MIXIN_MESSAGE[$messId]", StringHelper::xmlEntities($message), $xml);
            }
        }
        if ($stripSpaces) {
            $xml = preg_replace("/[\n\r]?/", "", $xml);
            $xml = preg_replace("/\t/", " ", $xml);
        }
        $xml = str_replace(array('xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"','xsi:noNamespaceSchemaLocation="file:../core.ajaxplorer/ajxp_registry.xsd"'), "", $xml);
        $tab = array(&$xml);
        Controller::applyIncludeHook("xml.filter", $tab);
        return $xml;
    }
    
}
