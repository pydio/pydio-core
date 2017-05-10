<?php
/*
 * Copyright 2007-2016 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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

use Pydio\Core\Controller\Controller;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services;


use Pydio\Core\Services\ConfService;
use Pydio\Core\Services\UsersService;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Standard values filtering used in the core.
 * @static
 * @package Pydio
 * @subpackage Core
 */
class VarsFilter
{
    /**
     * Filter the very basic keywords from the XML  : AJXP_USER, AJXP_INSTALL_PATH, AJXP_DATA_PATH
     * Calls the vars.filter hooks.
     * @static
     * @param mixed $value
     * @param ContextInterface $ctx
     * @return mixed|string
     * @throws PydioException
     */
    public static function filter($value, ContextInterface $ctx)
    {
        // If AJXP_PARENT_OPTION, resolve and return directly, do not filter the real value.
        if(is_string($value) && preg_match("/AJXP_PARENT_OPTION:([\w_-]*):?/", $value, $matches)){
            $repoObject = $ctx->getRepository();
            $parentRepository = $repoObject->getParentRepository();
            if(empty($parentRepository)){
                throw new PydioException("Cannot resolve ".$matches[0]." without parent workspace");
            }
            $parentOwner = $ctx->getRepository()->getOwner();
            $parentContext = Context::contextWithObjects(null, $parentRepository);
            $parentContext->setUserId($parentOwner);
            $parentPath = rtrim($parentRepository->getContextOption($parentContext, $matches[1]), "/");
            $value = str_replace($matches[0], $parentPath, $value);
            return $value;
        }

        if (is_string($value) && strpos($value, "AJXP_USER")!==false) {
            if (UsersService::usersEnabled()) {
                if(!$ctx->hasUser()){
                    throw new PydioException("Cannot resolve AJXP_USER without user passed in context");
                }
                $value = str_replace("AJXP_USER", $ctx->getUser()->getId(), $value);
            } else {
                $value = str_replace("AJXP_USER", "shared", $value);
            }
        }
        if (is_string($value) && strpos($value, "AJXP_GROUP_PATH")!==false) {
            if (UsersService::usersEnabled()) {
                if(!$ctx->hasUser()){
                    throw new PydioException("Cannot resolve path AJXP_GROUP_PATH without user passed in context");
                }
                $gPath = $ctx->getUser()->getGroupPath();
                $value = str_replace("AJXP_GROUP_PATH_FLAT", str_replace("/", "_", trim($gPath, "/")), $value);
                $value = str_replace("AJXP_GROUP_PATH", $gPath, $value);
            } else {
                $value = str_replace(array("AJXP_GROUP_PATH", "AJXP_GROUP_PATH_FLAT"), "shared", $value);
            }
        }
        if (is_string($value) && strpos($value, "AJXP_INSTALL_PATH") !== false) {
            $value = str_replace("AJXP_INSTALL_PATH", AJXP_INSTALL_PATH, $value);
        }
        if (is_string($value) && strpos($value, "AJXP_DATA_PATH") !== false) {
            $value = str_replace("AJXP_DATA_PATH", AJXP_DATA_PATH, $value);
        }
        if (is_string($value) && strstr($value, "AJXP_WORKSPACE_UUID") !== false) {
            $value = rtrim(str_replace("AJXP_WORKSPACE_UUID", $ctx->getRepository()->getUniqueId(), $value), "/");
        }
        if (is_string($value) && strstr($value, "AJXP_WORKSPACE_SLUG") !== false) {
            $value = rtrim(str_replace("AJXP_WORKSPACE_SLUG", $ctx->getRepository()->getSlug(), $value), "/");
        }

        $tab = array(&$value, $ctx);
        Controller::applyIncludeHook("vars.filter", $tab);
        return $value;
    }

    /**
     * @param $array
     */
    public static function filterI18nStrings(&$array){
        if(!is_array($array)) return;
        $appTitle = ConfService::getGlobalConf("APPLICATION_TITLE");
        foreach($array as &$value){
            $value = str_replace("APPLICATION_TITLE", $appTitle, $value);
        }
    }
}
