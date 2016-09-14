<?php
/*
 * Copyright 2007-2015 Abstrium <contact (at) pydio.com>
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
namespace Pydio\Core\Services;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Core\Model\RepositoryInterface;
use Pydio\Core\Model\UserInterface;

defined('AJXP_EXEC') or die('Access not allowed');

define('PYDIO_SESSION_NAME', 'AjaXplorer');
define('PYDIO_SESSION_QUERY_PARAM', 'ajxp_sessid');

/**
 * Class SessionService
 * @package Pydio\Core\Services
 */
class SessionService implements RepositoriesCache
{
    private static $sessionName = PYDIO_SESSION_NAME;

    /**
     * @param $sessionName
     */
    public static function setSessionName($sessionName){
        self::$sessionName = $sessionName;
    }

    /**
     * @return string
     */
    public static function getSessionName(){
        return self::$sessionName;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param callable|null $next
     * @return mixed|ResponseInterface
     */
    public static function handleRequest(ServerRequestInterface $request, ResponseInterface $response, callable $next = null){

        $getParams = $request->getQueryParams();
        if (isSet($getParams[PYDIO_SESSION_QUERY_PARAM])) {
            $cookies = $request->getCookieParams();
            if (!isSet($cookies[self::$sessionName])) {
                $cookies[self::$sessionName] = $getParams[PYDIO_SESSION_QUERY_PARAM];
                $request = $request->withCookieParams($cookies);
            }
        }

        if(defined("AJXP_SESSION_HANDLER_PATH") && defined("AJXP_SESSION_HANDLER_CLASSNAME") && file_exists(AJXP_SESSION_HANDLER_PATH)){
            require_once(AJXP_SESSION_HANDLER_PATH);
            if(class_exists(AJXP_SESSION_HANDLER_CLASSNAME, false)){
                $sessionHandlerClass = AJXP_SESSION_HANDLER_CLASSNAME;
                $sessionHandler = new $sessionHandlerClass();
                session_set_save_handler($sessionHandler, false);
            }
        }
        session_name(self::$sessionName);
        session_start();

        if($next !== null){
            $response = call_user_func_array($next, array(&$request, &$response));
        }

        register_shutdown_function(function(){
            SessionService::close();
        });

        return $response;

    }

    public static function close(){
        session_write_close();
    }

    /**
     * @param UserInterface $ctxUser
     */
    public static function checkPendingRepository($ctxUser){
        if (isSet($_SESSION["PENDING_REPOSITORY_ID"]) && isSet($_SESSION["PENDING_FOLDER"])) {
            $ctxUser->setArrayPref("history", "last_repository", $_SESSION["PENDING_REPOSITORY_ID"]);
            $ctxUser->setPref("pending_folder", $_SESSION["PENDING_FOLDER"]);
            unset($_SESSION["PENDING_REPOSITORY_ID"]);
            unset($_SESSION["PENDING_FOLDER"]);
        }
    }

    /**
     * @return null
     */
    public static function getSessionRepositoryId(){

        return isSet($_SESSION["REPO_ID"]) ? $_SESSION["REPO_ID"] : null;
    }

    /**
     * @param $repoId
     */
    public static function saveRepositoryId($repoId){
        $_SESSION["REPO_ID"] = $repoId;
    }

    /**
     * @param $repoId
     */
    public static function switchSessionRepositoriId($repoId){
        if(isSet($_SESSION["REPO_ID"])){
            $_SESSION["PREVIOUS_REPO_ID"] = $_SESSION["REPO_ID"];
        }
        $_SESSION["REPO_ID"] = $repoId;
    }

    /**
     * @return null
     */
    public static function getPreviousRepositoryId(){
        return isSet($_SESSION["PREVIOUS_REPO_ID"]) ? $_SESSION["PREVIOUS_REPO_ID"] : null;
    }

    /**
     * @return RepositoryInterface[]|null
     */
    public static function getLoadedRepositories()
    {
        if (isSet($_SESSION["REPOSITORIES"]) && is_array($_SESSION["REPOSITORIES"])) {
            $sessionNotCorrupted = array_reduce($_SESSION["REPOSITORIES"], function($carry, $item){ return $carry && is_object($item) && ($item instanceof RepositoryInterface); }, true);
            if($sessionNotCorrupted){
                return $_SESSION["REPOSITORIES"];
            } else {
                unset($_SESSION["REPOSITORIES"]);
            }
        }
        return null;
    }

    /**
     * @param RepositoryInterface[] $repositories
     * @return mixed
     */
    public static function updateLoadedRepositories($repositories)
    {
        $_SESSION["REPOSITORIES"] = $repositories;
    }

    /**
     * @return boolean
     */
    public static function invalidateLoadedRepositories()
    {
        if(isSet($_SESSION["REPOSITORIES"])){
            unset($_SESSION["REPOSITORIES"]);
        }
    }

    /**
     * @param $repositoryId
     * @return null
     */
    public static function getContextCharset($repositoryId)
    {
        if (isSet($_SESSION["AJXP_CHARSET"])) return $_SESSION["AJXP_CHARSET"];
        return null;
    }

    /**
     * @param $repositoryId
     * @param $value
     */
    public static function setContextCharset($repositoryId, $value)
    {
        if (ConfService::$useSession) {
            $_SESSION["AJXP_CHARSET"] = $value;
        }
    }

    /**
     * @param string $lang
     */
    public static function setLanguage($lang){
        if(ConfService::$useSession){
            $_SESSION["AJXP_LANG"] = $lang;
        }
    }

    /**
     * @return string|null
     */
    public static function getLanguage(){
        if(isSet($_SESSION["AJXP_LANG"])){
            return $_SESSION["AJXP_LANG"];
        }
        return null;
    }
    
}