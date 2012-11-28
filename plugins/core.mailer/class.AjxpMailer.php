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

    var $mailCache;

    public function init($options){
        parent::init($options);
        if(AJXP_SERVER_DEBUG){
            $this->mailCache = $this->getPluginWorkDir(true)."/mailbox";
        }
    }

    public function sendMail($recipients, $subject, $body, $from = null){
        if(AJXP_SERVER_DEBUG){
            $rec = implode(",", $recipients);
            $line = "------------------------------------------------------------------------\n";
            file_put_contents($this->mailCache, "Sending mail from $from to $rec\n\n$subject\n\n$body\n".$line, FILE_APPEND);
        }
        $this->sendMailImpl($recipients, $subject, $body, $from = null);
    }

    protected function sendMailImpl($recipients, $subject, $body, $from = null){

    }

    public function sendMailAction($actionName, $httpVars, $fileVars){
        AJXP_Logger::debug("Send email", $httpVars);
        $mess = ConfService::getMessages();
        $mailers = AJXP_PluginsService::getInstance()->getActivePluginsForType("mailer");
        if(!count($mailers)){
            throw new Exception($mess["core.mailer.3"]);
        }

        $mailer = array_pop($mailers);

        $toUsers = array_merge(explode(",", $httpVars["users_ids"]), explode(",", $httpVars["to"]));
        $toGroups =  explode(",", $httpVars["groups_ids"]);

        $emails = $this->resolveAdresses($toUsers);
        $from = $this->resolveFrom($httpVars["from"]);

        $subject = $httpVars["subject"];
        $body = $httpVars["message"];

        if(count($emails)){
            $mailer->sendMail($emails, $subject, $body, $from);
            AJXP_XMLWriter::header();
            AJXP_XMLWriter::sendMessage(str_replace("%s", count($emails), $mess["core.mailer.1"]), null);
            AJXP_XMLWriter::close();
        }else{
            AJXP_XMLWriter::header();
            AJXP_XMLWriter::sendMessage(null, $mess["core.mailer.2"]);
            AJXP_XMLWriter::close();
        }
    }

    function resolveFrom($fromAdress = null){
        $fromResult = array();
        if($fromAdress != null){
            $arr = $this->resolveAdresses(array($fromAdress));
            if(count($arr)) $fromResult = $arr[0];
        }else if(AuthService::getLoggedUser() != null){
            $arr = $this->resolveAdresses(array(AuthService::getLoggedUser()));
            if(count($arr)) $fromResult = $arr[0];
        }else{
            $f = ConfService::getCoreConf("FROM", "mailer");
            $fName = ConfService::getCoreConf("FROM_NAME", "mailer");
            $fromResult = array("adress" => $f, "name" => $fName );
        }
        return $fromResult;
    }

    /**
     * @param array $recipients
     * @return array
     *
     */
    function resolveAdresses($recipients){
        $realRecipients = array();
        // Recipients can be either AbstractAjxpUser objects, either array(adress, name), either "adress".
        foreach($recipients as $recipient){
            if(is_object($recipient) && is_a($recipient, "AbstractAjxpUser")){
                $resolved = $this->abstractUserToAdress($recipient);
                if($resolved !== false){
                    $realRecipients[] = $resolved;
                }
            }else if(is_array($recipient)){
                if(array_key_exists("adress", $recipient)){
                    if(!array_key_exists("name", $recipient)){
                        $recipient["name"] = $recipient["name"];
                    }
                    $realRecipients[] = $recipient;
                }
            }else if(is_string($recipient)){
                if(strpos($recipient, ":") !== false){
                    $parts = explode(":", $recipient, 2);
                    $realRecipients[] = array("name" => $parts[0], "adress" => $parts[2]);
                }else{
                    if($this->validateEmail($recipient)){
                        $realRecipients[] = array("name" => $recipient, "adress" => $recipient);
                    }else if(AuthService::userExists($recipient)){
                        $user = ConfService::getConfStorageImpl()->createUserObject($recipient);
                        $res = $this->abstractUserToAdress($user);
                        if($res !== false) $realRecipients[] = $res;
                    }
                }
            }
        }

        return $realRecipients;
    }

    function abstractUserToAdress(AbstractAjxpUser $user){
        // TODO
        // SHOULD CHECK THAT THIS USER IS "AUTHORIZED" TO AVOID SPAM
        $userEmail = $user->personalRole->filterParameterValue("core.conf", "email", AJXP_REPO_SCOPE_ALL, "");
        if(empty($userEmail)) {
            return false;
        }
        $displayName = $user->personalRole->filterParameterValue("core.conf", "USER_DISPLAY_NAME", AJXP_REPO_SCOPE_ALL, "");
        if(empty($displayName)) $displayName = $user->getId();
        return array("name" => $displayName, "adress" => $userEmail);
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
