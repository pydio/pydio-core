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


class DuoSecurityFrontend extends AbstractAuthFrontend {

    function tryToLogUser(&$httpVars, $isLast = false){

        // CATCH THE STANDARD LOGIN OPERATION
        if(!isSet($httpVars["get_action"]) || $httpVars["get_action"] != "login"){
            return false;
        }
        if(AJXP_Utils::userAgentIsNativePydioApp()){
            return false;
        }

        $userId = (isSet($httpVars["userid"])?trim($httpVars["userid"]):null);
        $duoActive = false;
        if(!empty($userId)){
            $uObject = ConfService::getConfStorageImpl()->createUserObject($userId);
            if($uObject != null){
                $duoActive = $uObject->mergedRole->filterParameterValue("authfront.duosecurity","DUO_AUTH_ACTIVE", AJXP_REPO_SCOPE_ALL, false);
            }
        }
        if(!$duoActive) {
            return false;
        }

        $rememberLogin = "";
        $rememberPass = "";
        $secureToken = "";
        $loggedUser = null;
        include_once(AJXP_BIN_FOLDER."/class.CaptchaProvider.php");
        if (AuthService::suspectBruteForceLogin() && (!isSet($httpVars["captcha_code"]) || !CaptchaProvider::checkCaptchaResult($httpVars["captcha_code"]))) {
            $loggingResult = -4;
        } else {
            $userId = (isSet($httpVars["userid"])?trim($httpVars["userid"]):null);
            $userPass = (isSet($httpVars["password"])?trim($httpVars["password"]):null);
            $rememberMe = ((isSet($httpVars["remember_me"]) && $httpVars["remember_me"] == "true")?true:false);
            $cookieLogin = (isSet($httpVars["cookie_login"])?true:false);
            $loggingResult = AuthService::logUser($userId, $userPass, false, $cookieLogin, $httpVars["login_seed"]);
            if ($rememberMe && $loggingResult == 1) {
                $rememberLogin = "notify";
                $rememberPass = "notify";
            }
            if ($loggingResult == 1) {
                session_regenerate_id(true);
                $secureToken = AuthService::generateSecureToken();
            }
            if ($loggingResult < 1 && AuthService::suspectBruteForceLogin()) {
                $loggingResult = -4; // Force captcha reload
            }
        }
        $loggedUser = AuthService::getLoggedUser();
        if ($loggedUser != null) {
            $force = $loggedUser->mergedRole->filterParameterValue("core.conf", "DEFAULT_START_REPOSITORY", AJXP_REPO_SCOPE_ALL, -1);
            $passId = -1;
            if (isSet($httpVars["tmp_repository_id"])) {
                $passId = $httpVars["tmp_repository_id"];
            } else if ($force != "" && $loggedUser->canSwitchTo($force) && !isSet($httpVars["tmp_repository_id"]) && !isSet($_SESSION["PENDING_REPOSITORY_ID"])) {
                $passId = $force;
            }
            $res = ConfService::switchUserToActiveRepository($loggedUser, $passId);
            if (!$res) {
                AuthService::disconnect();
                $loggingResult = -3;
            }
        }

        if ($loggedUser != null && (AuthService::hasRememberCookie() || (isSet($rememberMe) && $rememberMe ==true))) {
            AuthService::refreshRememberCookie($loggedUser);
        }
        AJXP_XMLWriter::header();
        AJXP_XMLWriter::loggingResult($loggingResult, $rememberLogin, $rememberPass, $secureToken);
        AJXP_XMLWriter::close();

        if($loggingResult > 0 && $loggedUser != null){

            require_once($this->getBaseDir()."/duo_php/duo_web.php");
            $appUnique = $this->getFilteredOption("DUO_AUTH_AKEY");
            $iKey = $this->getFilteredOption("DUO_AUTH_IKEY");
            $sKey = $this->getFilteredOption("DUO_AUTH_SKEY");

            $res = Duo::signRequest($iKey, $sKey, $appUnique, $loggedUser->getId());

            $loggedUser->personalRole->setParameterValue("authfront.duosecurity", "DUO_AUTH_LAST_SIGNATURE", $res);
            $loggedUser->setLock("duo_show_iframe");
            $loggedUser->save("superuser");
        }

//        if($loggingResult > 0 || $isLast){
            exit();
//       }

    }

    public function postVerificationCode($action, $httpVars, $fileVars)
    {
        if($action!="duo_post_verification_code") return;

        $u = AuthService::getLoggedUser();
        if($u == null) return;
        $sigResponse = $httpVars["sig_response"];
        require_once($this->getBaseDir()."/duo_php/duo_web.php");
        $appUnique = $this->getFilteredOption("DUO_AUTH_AKEY");
        $iKey = $this->getFilteredOption("DUO_AUTH_IKEY");
        $sKey = $this->getFilteredOption("DUO_AUTH_SKEY");

        $verif = Duo::verifyResponse($iKey, $sKey, $appUnique, $sigResponse);

        if($verif != null && $verif == $u->getId()){
            $u->removeLock();
            $u->save("superuser");
            $u->recomputeMergedRole();
            AuthService::updateUser($u);
            ConfService::switchUserToActiveRepository($u);
            $force = $u->mergedRole->filterParameterValue("core.conf", "DEFAULT_START_REPOSITORY", AJXP_REPO_SCOPE_ALL, -1);
            $passId = -1;
            if ($force != "" && $u->canSwitchTo($force) && !isSet($httpVars["tmp_repository_id"]) && !isSet($_SESSION["PENDING_REPOSITORY_ID"])) {
                $passId = $force;
            }
            $res = ConfService::switchUserToActiveRepository($u, $passId);
            if (!$res) {
                AuthService::disconnect();
                AJXP_XMLWriter::header();
                AJXP_XMLWriter::requireAuth(true);
                AJXP_XMLWriter::close();
            }
        }else{
            AuthService::disconnect();
            AJXP_XMLWriter::header();
            AJXP_XMLWriter::requireAuth(true);
            AJXP_XMLWriter::close();
        }

    }



} 