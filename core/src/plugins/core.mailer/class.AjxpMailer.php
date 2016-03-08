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
    protected $_dibiDriver;

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

    protected function getDibiDriver () {
        if (!isset($this->_dibiDriver)) {
            $this->_dibiDriver = AJXP_Utils::cleanDibiDriverParameters(array("group_switch_value"=>"core"));
        }
        return $this->_dibiDriver;
    }
    public function mailConsumeQueue ($action, $httpVars, $fileVars) {
        if ($action === "consume_mail_queue") {
            $mailer = AJXP_PluginsService::getInstance()->getActivePluginsForType("mailer", true);
            if (!dibi::isConnected()) {
                dibi::connect($this->getDibiDriver());
            }
            if($this->_dibiDriver["driver"] == "postgre"){
                dibi::query("SET bytea_output=escape");
            }
            $time = time();
            try {
                $querySQL = dibi::query("SELECT * FROM [ajxp_mail_queue] WHERE [date_event] <= %s", $time);
            } catch (DibiException $e) {
                throw new AJXP_Exception($e->getMessage());
            }
            $resultsSQL = $querySQL->fetchAll();
            $arrayResultsSQL = array();
            if (count($resultsSQL) > 0) {
                foreach ($resultsSQL as $value) {
                    $ajxpNotification = unserialize($value["notification_object"]);
                    $ajxpNode = new AJXP_Node($value['url']);
                    $ajxpNode->loadNodeInfo();
                    if ($ajxpNode->isLeaf()) {
                        $ajxpContent = $ajxpNode->getParent()->getPath();
                    } else {
                        $ajxpContent = $ajxpNode->getPath();
                        if ($ajxpContent === null) {
                            $ajxpContent = '/';
                        }
                    }
                    $ajxpAction = $ajxpNotification->getAction();
                    $ajxpAuthor = $ajxpNotification->getAuthor();
                    $ajxpNodeWorkspace = $ajxpNode->getRepository()->getDisplay();
                    $ajxpKey = $value["html"]."|".$ajxpAction."|".$ajxpAuthor."|".$ajxpContent;
                    $arrayResultsSQL[$value['recipent']][$ajxpNodeWorkspace][$ajxpKey][] = $ajxpNotification;
                }
                //this $body must be here because we need this css
                //$body = 'h1{font-size: 1.3em;border-bottom: 1px solid #555555;padding-bottom: 4px;font-weight: normal;color: #555555;}ul{list-style-type: none;padding: 0;margin-bottom: 50px;}*{font-family: Arial;font-size: 14px;color: #555;}li{margin: 20px 0px;}h1 em{font-size: 1em;color: #E35D52;font-weight: normal;}em{font-style: normal;font-weight: bold;}';
                //make condition if the user want html mail or not
                $body = '';
                foreach ($arrayResultsSQL as $recipent => $arrayWorkspace) {
                    foreach ($arrayWorkspace as $workspace => $arrayAjxpKey) {
                        $key = key($arrayAjxpKey);
                        $body = $body . '<h1>' . $arrayAjxpKey[$key][0]->getDescriptionLocation() . ', </h1><ul>';
                        foreach ($arrayAjxpKey as $ajxpKey => $arrayNotif) {
                            $body = $body . '<li>' . $arrayNotif[0]->getDescriptionLong(true) . ' (' . count($arrayNotif) . ')</li>';
                        }
                        $body = $body . '</ul>';
                        if (substr($key,0,1) === "0") {
                            $body = preg_replace('/<\/h1>/', ' \n ', $body);
                            $body = preg_replace('/<\/li>/', ' \n ', $body);
                            $body = preg_replace('/<\/ul>/', ' \n ', $body);
                            $body = preg_replace('/<[^>]*>/', '', $body);
                        }
                    }
                    try {
                        $mailer->sendMail(array($recipent),
                            "Compte rendu Pydio",
                            $body);
                    } catch (AJXP_Exception $e) {
                        throw new AJXP_Exception($e->getMessage());
                    }
                }
                try {
                    dibi::query('DELETE FROM [ajxp_mail_queue] WHERE [date_event] <= %s', $time);
                } catch (DibiException $e) {
                    throw new AJXP_Exception($e->getMessage());
                }
            }
        }
    }

    public function processNotification(AJXP_Notification &$notification)
    {
        $userExist = AuthService::userExists($notification->getTarget());
        if ($userExist === true) {
            $userObject = ConfService::getConfStorageImpl()->createUserObject($notification->getTarget());
        } else {
            $messages = ConfService::getMessages();
            throw new AJXP_Exception($messages['core.mailer.2']);
        }
        $notification_email_get =  $userObject->mergedRole->filterParameterValue("core.mailer","NOTIFICATIONS_EMAIL_GET", AJXP_REPO_SCOPE_ALL,"true");
        $notification_email_frequency =  $userObject->mergedRole->filterParameterValue("core.mailer","NOTIFICATIONS_EMAIL_FREQUENCY", AJXP_REPO_SCOPE_ALL,"M");
        $notification_email_frequency_user =  $userObject->mergedRole->filterParameterValue("core.mailer","NOTIFICATIONS_EMAIL_FREQUENCY_USER", AJXP_REPO_SCOPE_ALL,"5");
        $notification_email =  $userObject->mergedRole->filterParameterValue("core.mailer","NOTIFICATIONS_EMAIL", AJXP_REPO_SCOPE_ALL,"");
        $notification_email_send_html =  $userObject->mergedRole->filterParameterValue("core.mailer","NOTIFICATIONS_EMAIL_SEND_HTML", AJXP_REPO_SCOPE_ALL,"true");
        if ($notification_email_send_html === "true") {
            $html = 1;
        } else {
            $html = 0;
        }
        $arrayRecipent = explode(',', $notification_email);
        if ($notification_email_get === "true" && count($arrayRecipent) > 0 && !empty($notification_email_frequency_user)) {
            $date = new DateTime("now");
            $hour = $date->format('H');
            $minute = $date->format('i');
            $frequency = $notification_email_frequency_user;
            switch ($notification_email_frequency) {
                case "M":
                    //FOR EVERY X MIN
                    $allMinute = ($hour * 60) + $minute;
                    $nextInterval = $allMinute - ($allMinute % $frequency) + $frequency;
                    $nextInterval = $nextInterval - $frequency;
                    $nextInterval = $nextInterval / 60;
                    $nextIntervalDecimal = $nextInterval - (int)$nextInterval;
                    $nextIntervalMinute = $nextIntervalDecimal * 60;
                    $nextIntervalHour = (int)$nextInterval;
                    $nextIntervalDate = new DateTime($nextIntervalHour . ':' . $nextIntervalMinute);
                    $nextFrequency = $nextIntervalDate->modify('+' . $frequency . ' minutes')->format('Y-m-d H:i:s');
                    break;
                case "H":
                    //FOR EVERY X HOUR
                    $frequency = $frequency * 60;
                    $allMinute = ($hour * 60) + $minute;
                    $nextInterval = $allMinute - ($allMinute % $frequency) + $frequency;
                    $nextInterval = $nextInterval - $frequency;
                    $nextInterval = $nextInterval / 60;
                    $nextIntervalDecimal = $nextInterval - (int)$nextInterval;
                    $nextIntervalMinute = $nextIntervalDecimal * 60;
                    $nextIntervalHour = (int)$nextInterval;
                    $nextIntervalDate = new DateTime($nextIntervalHour . ':' . $nextIntervalMinute);
                    $nextFrequency = $nextIntervalDate->modify('+' . $frequency . ' minutes')->format('Y-m-d H:i:s');
                    break;
                case "D1":
                    $compareDate = new DateTime($date->format('d-m-Y') . ' ' . $frequency . ':00');
                    if ($date > $compareDate) {
                        //FREQUENCY ALREADY GONE, NEXT INTERVAL => NEXT DAY
                        $compareDate = $compareDate->modify('+1 day');
                    }
                    $nextFrequency = $compareDate->format('Y-m-d ' . $frequency . ':00');
                    break;
                case "D2":
                    //FOR EVERY DAY AT X and Y
                    $arrayFrequency = explode(",", $notification_email_frequency_user);
                    if (count($arrayFrequency) === 2) {
                        $compareDate1 = new DateTime($date->format('d-m-Y') . ' ' . $arrayFrequency[0] . ':00');
                        $compareDate2 = new DateTime($date->format('d-m-Y') . ' ' . $arrayFrequency[1] . ':00');
                        if ($date < $compareDate1 && $date < $compareDate2) {
                            $nextFrequency = $date->format('Y-m-d ' . $arrayFrequency[0] . ':00');
                        } elseif ($date > $compareDate1 && $date < $compareDate2) {
                            $nextFrequency = $date->format('Y-m-d ' . $arrayFrequency[1] . ':00');
                        } else {
                            $nextFrequency = $date->modify('+1 day')->format('Y-m-d ' . $arrayFrequency[0] . ':00');
                        }
                    }
                    break;
                case "W1":
                    //FOR EVERY WEEK AT THE DAY
                    $nextFrequency = $date->modify('next ' . $frequency)->format('Y-m-d 00:00:00');
                    break;
            }
            if (!dibi::isConnected()) {
                dibi::connect($this->getDibiDriver());
            }
            foreach ($arrayRecipent as $recipent) {
                try {
                    dibi::query("INSERT INTO [ajxp_mail_queue] ([recipent],[url],[date_event],[notification_object],[html]) VALUES (%s,%s,%s,%bin,%b) ",
                        trim($recipent),
                        $notification->getNode()->getUrl(),
                        $nextFrequency,
                        serialize($notification),
                        $html);
                } catch (Exception $e) {
                    new AJXP_Exception($e->getMessage());
                }
            }
        } else {
            $mailer = AJXP_PluginsService::getInstance()->getActivePluginsForType("mailer", true);
            if ($mailer !== false) {
                try {
                    $mailer->sendMail(
                        array($notification->getTarget()),
                        $notification->getDescriptionShort(),
                        $notification->getDescriptionLong(),
                        $notification->getAuthor(),
                        $notification->getMainLink()
                    );
                } catch (Exception $e) {
                    throw new AJXP_Exception($e->getMessage());
                }
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
        $rowBody = $body;
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
            file_put_contents($this->mailCache, $line."Sending mail from ".print_r($from, true)." to ".print_r($recipients, true)."\nSubject: $subject\nBody:\n$rowBody\n", FILE_APPEND);
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
