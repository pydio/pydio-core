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
define('PYDIO_SESSION_NAME_SETTINGS', 'Pydio_Settings');
define('PYDIO_SESSION_QUERY_PARAM', 'ajxp_sessid');

/**
 * Class SessionService
 * @package Pydio\Core\Services
 */
class SessionService implements RepositoriesCache
{
    const USER_KEY = "PYDIO_USER";
    const LANGUAGES_KEY = "PYDIO_LANGUAGES";
    const CTX_LANGUAGE_KEY = "PYDIO_CTX_LANGUAGE";
    const CTX_CHARSET_KEY = "PYDIO_CTX_CHARSET";
    const CTX_MINISITE_HASH = "PYDIO_CTX_MINISITE";

    const LOADED_REPOSITORIES = "PYDIO_REPOSITORIES";
    const PREVIOUS_REPOSITORY = "PYDIO_PREVIOUS_REPO_ID";
    const CTX_REPOSITORY_ID = "PYDIO_REPO_ID";
    const PENDING_REPOSITORY_ID = "PYDIO_PENDING_REPO_ID";
    const PENDING_FOLDER = "PYDIO_PENDING_FOLDER";

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
     * @param $id
     * @return null
     */
    public static function fetch($id){
        if(!is_array($_SESSION) || !ApplicationState::sapiUsesSession()) return null;
        if(isSet($_SESSION[$id]) && !$_SESSION[$id] instanceof \__PHP_Incomplete_Class){
            return $_SESSION[$id];
        }
        return null;
    }

    /**
     * @param $id
     * @param $data
     * @return bool
     */
    public static function save($id, $data){
        if(!is_array($_SESSION) || !ApplicationState::sapiUsesSession()) return false;
        $_SESSION[$id] = $data;
        return true;
    }

    /**
     * @param $id string
     */
    public static function delete($id){
        if(!is_array($_SESSION) || !ApplicationState::sapiUsesSession()) return;
        if(array_key_exists($id, $_SESSION)){
            unset($_SESSION[$id]);
        }
    }

    /**
     * @param $id
     * @return bool
     */
    public static function has($id){
        return(is_array($_SESSION) && array_key_exists($id, $_SESSION));
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
        if (self::has(self::PENDING_REPOSITORY_ID) && self::has(self::PENDING_FOLDER)) {
            $ctxUser->setArrayPref("history", "last_repository", self::fetch(self::PENDING_REPOSITORY_ID));
            $ctxUser->setPref("pending_folder", self::fetch(self::PENDING_FOLDER));
            self::delete(self::PENDING_REPOSITORY_ID);
            self::delete(self::PENDING_FOLDER);
        }
    }

    /**
     * @return null
     */
    public static function getSessionRepositoryId(){
        return self::fetch(self::CTX_REPOSITORY_ID);
    }

    /**
     * @param $repoId
     */
    public static function saveRepositoryId($repoId){
        self::save(self::CTX_REPOSITORY_ID, $repoId);
    }

    /**
     * @param $repoId
     */
    public static function switchSessionRepositoryId($repoId){
        if(self::has(self::CTX_REPOSITORY_ID)) {
            self::save(self::PREVIOUS_REPOSITORY, self::fetch(self::CTX_REPOSITORY_ID));
        }
        self::save(self::CTX_REPOSITORY_ID, $repoId);
    }

    /**
     * @return null
     */
    public static function getPreviousRepositoryId(){
        return self::fetch(self::PREVIOUS_REPOSITORY);
    }

    /**
     * @return RepositoryInterface[]|null
     */
    public static function getLoadedRepositories()
    {
        $arr = self::fetch(self::LOADED_REPOSITORIES);
        if (is_array($arr)) {
            $sessionNotCorrupted = array_reduce($arr, function($carry, $item){ return $carry && is_object($item) && ($item instanceof RepositoryInterface); }, true);
            if($sessionNotCorrupted){
                return $arr;
            } else {
                self::delete(self::LOADED_REPOSITORIES);
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
        self::save(self::LOADED_REPOSITORIES, $repositories);
    }

    /**
     * @return boolean
     */
    public static function invalidateLoadedRepositories()
    {
        self::delete(self::LOADED_REPOSITORIES);
    }

    /**
     * @param $repositoryId
     * @return null
     */
    public static function getContextCharset($repositoryId)
    {
        $arr = self::fetch(self::CTX_CHARSET_KEY);
        if(!empty($arr) && isSet($arr[$repositoryId])){
            return $arr[$repositoryId];
        }
        return null;
    }

    /**
     * @param $repositoryId
     * @param $value
     */
    public static function setContextCharset($repositoryId, $value)
    {
        $arr = self::fetch(self::CTX_CHARSET_KEY) OR [];
        $arr[$repositoryId] = $value;
        self::save(self::CTX_CHARSET_KEY, $arr);
    }

    /**
     * @param string $lang
     */
    public static function setLanguage($lang){
        self::save(self::CTX_LANGUAGE_KEY, $lang);
    }

    /**
     * @return string|null
     */
    public static function getLanguage(){
        return self::fetch(self::CTX_LANGUAGE_KEY);
    }
    
}