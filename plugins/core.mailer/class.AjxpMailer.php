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

defined('AJXP_EXEC') or die('Access not allowed');

class AjxpMailer extends AJXP_Plugin
{
    public function sendMail($recipients, $subject, $body, $from = null){
        // TO BE IMPLEMENTED BY CHILD CLASS
    }

    public function sendMailAction($actionName, $httpVars, $fileVars){
        AJXP_Logger::debug("Send email", $httpVars);
        $mailers = AJXP_PluginsService::getInstance()->getActivePluginsForType("mailer");
        if(!count($mailers)){
            throw new Exception("No mailer found");
        }
        // Fake to type
        $mailer = new AjxpMailer("id", "basedir");

        $mailer = array_pop($mailers);

        $toUsers = array_merge(explode(",", $httpVars["users_ids"]), explode(",", $httpVars["to"]));
        $toGroups =  explode(",", $httpVars["groups_ids"]);

        $emails = array();

        foreach($toUsers as $userId){
            $userId = trim($userId);
            if(AuthService::userExists($userId)){
                // ADD USER
                // VERIFY IT'S AN AUTHORIZED USER

                $u = ConfService::getConfStorageImpl()->createUserObject($userId);
                $email = $u->personalRole->filterParameterValue("core.conf", "email", AJXP_REPO_SCOPE_ALL, "");
                if($this->validateEmail($email)){
                    array_push($emails, $email);
                }
            }else if($this->validateEmail($userId)){
                array_push($emails, $userId);
            }
        }

        if($this->validateEmail($httpVars["from"])){
            $from = $httpVars["from"];
        }else{
            $loggedUser = AuthService::getLoggedUser();
            $loggedEmail = $loggedUser->personalRole->filterParameterValue("core.conf", "email", AJXP_REPO_SCOPE_ALL, "");
            if(!empty($loggedEmail)){
                $from = $loggedEmail;
            }
        }
        if(!isSet($from)){
            $from = ConfService::getCoreConf("WEBMASTER_EMAIL");
        }

        $emails = array_unique($emails);
        $subject = $httpVars["subject"];
        $body = $httpVars["message"];

        $mailer->sendMail($emails, $subject, $body, $from);

    }


    function validateEmail($email){
        if(function_exists("filter_var")){
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        }

        $atom   = '[-a-z0-9!#$%&\'*+\\/=?^_`{|}~]';
        $domain = '([a-z0-9]([-a-z0-9]*[a-z0-9]+)?)';

        $regex = '/^' . $atom . '+' .
            '(\.' . $atom . '+)*' .
            '@' .
            '(' . $domain . '{1,63}\.)+' .
            $domain . '{2,63}$/i';

        if (preg_match($regex, $email)) {
            return true;
        } else {
            return false;
        }
    }

}
