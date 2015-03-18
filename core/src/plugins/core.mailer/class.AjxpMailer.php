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

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Core
 */
class AjxpMailer extends AJXP_Plugin
{

    public $mailCache;

    public function init($options)
    {
        parent::init($options);
        if (AJXP_SERVER_DEBUG) {
            $this->mailCache = $this->getPluginWorkDir(true)."/mailbox";
        }
        $pConf = $this->pluginConf["UNIQUE_MAILER_INSTANCE"];
        if (!empty($pConf)) {
            $p = ConfService::instanciatePluginFromGlobalParams($pConf, "AjxpMailer");
            AJXP_PluginsService::getInstance()->setPluginUniqueActiveForType($p->getType(), $p->getName(), $p);
        }
    }

    public function processNotification(AJXP_Notification &$notification)
    {
        $mailers = AJXP_PluginsService::getInstance()->getPluginsByType("mailer");
        if (count($mailers)) {
            $mailer = array_pop($mailers);
            try {
                $mailer->sendMail(
                    array($notification->getTarget()),
                    $notification->getDescriptionShort(),
                    $notification->getDescriptionLong(),
                    $notification->getAuthor(),
                    $notification->getMainLink()
                );
            } catch (Exception $e) {
                $this->logError("Exception", $e->getMessage(), $e->getTrace());
            }
        }
    }

    public function sendMail($recipients, $subject, $body, $from = null, $imageLink = null)
    {
        $prepend = ConfService::getCoreConf("SUBJECT_PREPEND", "mailer");
        $append = ConfService::getCoreConf("SUBJECT_APPEND", "mailer");
        $layoutFolder = ConfService::getCoreConf("LAYOUT_FOLDER", "mailer");
        $layout = ConfService::getCoreConf("BODY_LAYOUT", "mailer");
        $forceFrom = ConfService::getCoreConf("FORCE_UNIQUE_FROM", "mailer");
        $coreFrom = ConfService::getCoreConf("FROM", "mailer");
        if($forceFrom && $coreFrom != null){
            $coreFromName = ConfService::getCoreConf("FROM_NAME", "mailer");
            $from = array("adress" => $coreFrom, "name" => $coreFromName);
        }
        $images = array();
        if(!empty($prepend)) $subject = $prepend ." ". $subject;
        if(!empty($append)) $subject .= " ".$append;
        if (!empty($layoutFolder)) {
            $layoutFolder .= "/";
            $lang = ConfService::getLanguage();
            if (is_file(AJXP_INSTALL_PATH."/".$layoutFolder.$lang.".html")) {
                $layout = implode("", file(AJXP_INSTALL_PATH."/".$layoutFolder.$lang.".html"));
            } else if (is_file(AJXP_INSTALL_PATH."/".$layoutFolder."en.html")) {
                $layout = implode("", file(AJXP_INSTALL_PATH."/".$layoutFolder."en.html"));
            }
        }
        if (strpos($layout, "AJXP_MAIL_BODY") !== false) {
            $body = str_replace("AJXP_MAIL_BODY", nl2br($body), $layout);
        }
        if ($imageLink != null) {
            $body = str_replace(array("AJXP_IMAGE_LINK"), "<a href='".$imageLink."'>".'<img alt="Download" width="100" style="width: 100px;" src="cid:download_id">'."</a>", $body);
            $images[] = array("path" => AJXP_INSTALL_PATH."/".$layoutFolder."/download.png", "cid" => "download_id");
        } else {
            $body = str_replace(array("AJXP_IMAGE_LINK", "AJXP_IMAGE_END"), "", $body);
        }
        $body = str_replace("AJXP_MAIL_SUBJECT", $subject, $body);
        $this->sendMailImpl($recipients, $subject, $body, $from, $images);
        if (AJXP_SERVER_DEBUG) {
            $line = "------------------------------------------------------------------------\n";
            file_put_contents($this->mailCache, "Sending mail from ".print_r($from, true)." to ".print_r($recipients, true)."\n\n$subject\n\n$body\n".$line, FILE_APPEND);
        }
    }

    protected function sendMailImpl($recipients, $subject, $body, $from = null, $images = array())
    {
    }

    public function sendMailAction($actionName, $httpVars, $fileVars)
    {
        $mess = ConfService::getMessages();
        $mailers = AJXP_PluginsService::getInstance()->getActivePluginsForType("mailer");
        if (!count($mailers)) {
            throw new Exception($mess["core.mailer.3"]);
        }

        $mailer = array_pop($mailers);

        //$toUsers = array_merge(explode(",", $httpVars["users_ids"]), explode(",", $httpVars["to"]));
        //$toGroups =  explode(",", $httpVars["groups_ids"]);
        $toUsers = $httpVars["emails"];

        $emails = $this->resolveAdresses($toUsers);
        $from = $this->resolveFrom($httpVars["from"]);
        $imageLink = isSet($httpVars["link"]) ? $httpVars["link"] : null;

        $subject = $httpVars["subject"];
        $body = $httpVars["message"];

        if (count($emails)) {
            $mailer->sendMail($emails, $subject, $body, $from, $imageLink);
            AJXP_XMLWriter::header();
            AJXP_XMLWriter::sendMessage(str_replace("%s", count($emails), $mess["core.mailer.1"]), null);
            AJXP_XMLWriter::close();
        } else {
            AJXP_XMLWriter::header();
            AJXP_XMLWriter::sendMessage(null, $mess["core.mailer.2"]);
            AJXP_XMLWriter::close();
        }
    }

    public function resolveFrom($fromAdress = null)
    {
        $fromResult = array();
        if ($fromAdress != null) {
            $arr = $this->resolveAdresses(array($fromAdress));
            if(count($arr)) $fromResult = $arr[0];
        } else if (AuthService::getLoggedUser() != null) {
            $arr = $this->resolveAdresses(array(AuthService::getLoggedUser()));
            if(count($arr)) $fromResult = $arr[0];
        }
        if (!count($fromResult)) {
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
    public function resolveAdresses($recipients)
    {
        $realRecipients = array();
        foreach ($recipients as $recipient) {
            if (is_string($recipient) && strpos($recipient, "/AJXP_TEAM/") === 0) {
                $confDriver = ConfService::getConfStorageImpl();
                if (method_exists($confDriver, "teamIdToUsers")) {
                    $newRecs = $confDriver->teamIdToUsers(str_replace("/AJXP_TEAM/", "", $recipient));
                }
            }
        }
        if (isSet($newRecs)) {
            $recipients = array_merge($recipients, $newRecs);
        }
        // Recipients can be either AbstractAjxpUser objects, either array(adress, name), either "adress".
        foreach ($recipients as $recipient) {
            if (is_object($recipient) && is_a($recipient, "AbstractAjxpUser")) {
                $resolved = $this->abstractUserToAdress($recipient);
                if ($resolved !== false) {
                    $realRecipients[] = $resolved;
                }
            } else if (is_array($recipient)) {
                if (array_key_exists("adress", $recipient)) {
                    if (!array_key_exists("name", $recipient)) {
                        $recipient["name"] = $recipient["name"];
                    }
                    $realRecipients[] = $recipient;
                }
            } else if (is_string($recipient)) {
                if (strpos($recipient, ":") !== false) {
                    $parts = explode(":", $recipient, 2);
                    $realRecipients[] = array("name" => $parts[0], "adress" => $parts[2]);
                } else {
                    if ($this->validateEmail($recipient)) {
                        $realRecipients[] = array("name" => $recipient, "adress" => $recipient);
                    } else if (AuthService::userExists($recipient)) {
                        $user = ConfService::getConfStorageImpl()->createUserObject($recipient);
                        $res = $this->abstractUserToAdress($user);
                        if($res !== false) $realRecipients[] = $res;
                    }
                }
            }
        }

        return $realRecipients;
    }

    public function abstractUserToAdress(AbstractAjxpUser $user)
    {
        // TODO
        // SHOULD CHECK THAT THIS USER IS "AUTHORIZED" TO AVOID SPAM
        $userEmail = $user->personalRole->filterParameterValue("core.conf", "email", AJXP_REPO_SCOPE_ALL, "");
        if (empty($userEmail)) {
            return false;
        }
        $displayName = $user->personalRole->filterParameterValue("core.conf", "USER_DISPLAY_NAME", AJXP_REPO_SCOPE_ALL, "");
        if(empty($displayName)) $displayName = $user->getId();
        return array("name" => $displayName, "adress" => $userEmail);
    }


    public function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

}
