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

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Services\AuthService;
use Pydio\Conf\Core\AbstractAjxpUser;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Utils\Utils;
use Pydio\Core\Controller\XMLWriter;
use Pydio\Core\Controller\HTMLWriter;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\PluginFramework\SqlTableProvider;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Core
 */
class AjxpMailer extends Plugin implements SqlTableProvider
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
            PluginsService::getInstance()->setPluginUniqueActiveForType($p->getType(), $p->getName(), $p);
        }
    }

    protected function getDibiDriver () {
        if (!isset($this->_dibiDriver)) {
            $this->_dibiDriver = Utils::cleanDibiDriverParameters(array("group_switch_value"=>"core"));
        }
        return $this->_dibiDriver;
    }
    public function mailConsumeQueue ($action, $httpVars, $fileVars) {

        if ($action === "consume_mail_queue") {
            $mailer = PluginsService::getInstance()->getActivePluginsForType("mailer", true);
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
                throw new PydioException($e->getMessage());
            }
            $resultsSQL = $querySQL->fetchAll();
            $arrayResultsSQL = array();
            $output = array("success" => [], "error" => []);
            $recipientFormats = array();
            if (count($resultsSQL) > 0) {
                foreach ($resultsSQL as $value) {
                    $ajxpNotification = unserialize($value["notification_object"]);
                    $ajxpAction = $ajxpNotification->getAction();
                    $ajxpAuthor = $ajxpNotification->getAuthor();
                    $ajxpNode = new AJXP_Node($value['url']);
                    try{
                        @$ajxpNode->loadNodeInfo();
                    }catch(Exception $e){}
                    if ($ajxpNode->isLeaf() && !$ajxpNode->isRoot()) {
                        $ajxpContent = $ajxpNode->getParent()->getPath();
                    } else {
                        $ajxpContent = $ajxpNode->getPath();
                        if ($ajxpContent === null) {
                            $ajxpContent = '/';
                        }
                    }
                    if($ajxpNode->getRepository() != null){
                        $ajxpNodeWorkspace = $ajxpNode->getRepository()->getDisplay();
                    }else{
                        $ajxpNodeWorkspace = "Deleted Workspace";
                    }
                    $recipientFormats[$value['recipient']] = ($value["html"] == 1);
                    $ajxpKey = $ajxpAction."|".$ajxpAuthor."|".$ajxpContent;
                    $arrayResultsSQL[$value['recipient']][$ajxpNodeWorkspace][$ajxpKey][] = $ajxpNotification;
                }
                //this $body must be here because we need this css
                $digestTitle = ConfService::getMessages()["core.mailer.9"];
                foreach ($arrayResultsSQL as $recipient => $arrayWorkspace) {
                    $useHtml = $recipientFormats[$recipient];
                    $body = $useHtml ? "<div id='digest'>" : "";
                    foreach ($arrayWorkspace as $workspace => $arrayAjxpKey) {
                        $key = key($arrayAjxpKey);
                        $body = $body . '<h1>' . $arrayAjxpKey[$key][0]->getDescriptionLocation() . ', </h1><ul>';
                        foreach ($arrayAjxpKey as $ajxpKey => $arrayNotif) {
                            $descs = array();
                            foreach($arrayNotif as $notif){
                                $desc = $notif->getDescriptionLong(true);
                                if(array_key_exists($desc, $descs)){
                                    $descs[$desc] ++;
                                }else{
                                    $descs[$desc] = 1;
                                }
                            }
                            foreach($descs as $sentence => $occurences){
                                $body = $body . '<li>' . $sentence . ($occurences > 1 ? ' ('.count($arrayNotif).')' :'').'</li>';
                            }
                        }
                        $body = $body . '</ul>';
                    }
                    $body .= $useHtml ? "</div>" : "";
                    try {
                        $mailer->sendMail(array($recipient),
                            $digestTitle,
                            $body,
                            null,
                            null,
                            $useHtml
                        );
                        $output["success"][] = "Email sent to ".$recipient;
                    } catch (PydioException $e) {
                        $output["error"][] = "Sending email to ".$recipient.": ".$e->getMessage();
                    }
                }
                try {
                    dibi::query('DELETE FROM [ajxp_mail_queue] WHERE [date_event] <= %s', $time);
                } catch (DibiException $e) {
                    throw new PydioException($e->getMessage());
                }
            }
            HTMLWriter::charsetHeader("text/json");
            $output = array("report" => "Sent ".count($output["success"])." emails", "detail" => $output);
            echo json_encode($output);
        }
    }

    protected function stringify($int){
        return ($int < 10 ? "0".$int : "".$int);
    }

    protected function computeEmailSendDate($frequencyType, $frequencyDetail){

        $date = new DateTime("now");
        $year = $date->format("Y");
        $day = $date->format("d");
        $month = $date->format("m");
        $hour = intval($date->format('H'));
        $minute = intval($date->format('i'));
        $frequency = $frequencyDetail;
        $allMinute = ($hour * 60) + $minute;
        $nextFrequency = null;
        switch ($frequencyType) {
            case "M":
                //FOR EVERY X MIN
                $lastInterval = $allMinute - ($allMinute % $frequency);
                $newMinutes = $this->stringify($lastInterval % 60);
                $newHour = $this->stringify(($lastInterval - $newMinutes) / 60);
                $lastFrequency = DateTime::createFromFormat("Y-m-d H:i", "$year-$month-$day $newHour:$newMinutes");
                $nextFrequency = $lastFrequency->modify("+ $frequency minutes")->getTimestamp();
                break;
            case "H":
                //FOR EVERY X HOUR
                $frequency = $frequency * 60;
                $lastInterval = $allMinute - ($allMinute % $frequency);
                $newHour = $this->stringify($lastInterval / 60);
                $lastFrequency = DateTime::createFromFormat("Y-m-d H:i", "$year-$month-$day $newHour:00");
                $nextFrequency = $lastFrequency->modify("+ $frequency minutes")->getTimestamp();
                break;
            case "D1":
                $compareDate = new DateTime($date->format('d-m-Y') . ' ' . $frequency . ':00');
                if ($date > $compareDate) {
                    //FREQUENCY ALREADY GONE, NEXT INTERVAL => NEXT DAY
                    $compareDate = $compareDate->modify('+1 day');
                }
                $nextFrequency = new DateTime($compareDate->format('Y-m-d ' . $frequency . ':00'));
                $nextFrequency = $nextFrequency->getTimestamp();
                break;
            case "D2":
                //FOR EVERY DAY AT X and Y
                $arrayFrequency = explode(",", $frequencyDetail);
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
                    $nextFrequency = new DateTime($nextFrequency);
                    $nextFrequency = $nextFrequency->getTimestamp();
                }
                break;
            case "W1":
                //FOR EVERY WEEK AT THE DAY
                $nextFrequency = $date->modify('next ' . $frequency)->getTimestamp();
                break;
        }
        return $nextFrequency;
    }

    public function processNotification(AJXP_Notification &$notification)
    {
        $userExist = AuthService::userExists($notification->getTarget());
        if ($userExist === true) {
            $userObject = ConfService::getConfStorageImpl()->createUserObject($notification->getTarget());
        } else {
            $messages = ConfService::getMessages();
            throw new PydioException($messages['core.mailer.2']);
        }
        if($userObject->mergedRole->filterParameterValue("core.mailer","NOTIFICATIONS_EMAIL_GET", AJXP_REPO_SCOPE_ALL,"true") !== "true"){
            // User does not want to receive any emails.
            return;
        }

        $notification_email = $userObject->mergedRole->filterParameterValue("core.mailer","NOTIFICATIONS_EMAIL", AJXP_REPO_SCOPE_ALL,"");
        $arrayRecipients = array();
        $mainRecipient = $userObject->mergedRole->filterParameterValue("core.conf", "email", AJXP_REPO_SCOPE_ALL, "");
        $useHtml = $userObject->mergedRole->filterParameterValue("core.mailer","NOTIFICATIONS_EMAIL_SEND_HTML", AJXP_REPO_SCOPE_ALL,"true") === "true" ? 1 : 0;

        if(!empty($mainRecipient)) $arrayRecipients[] = $mainRecipient;
        $additionalRecipients = array_map("trim", explode(',', $notification_email));
        foreach($additionalRecipients as $addR){
            if(!empty($addR)) $arrayRecipients[] = $addR;
        }

        if ($this->pluginConf["MAILER_ACTIVATE_QUEUE"] && count($arrayRecipients)) {

            $frequencyType = $userObject->mergedRole->filterParameterValue("core.mailer","NOTIFICATIONS_EMAIL_FREQUENCY", AJXP_REPO_SCOPE_ALL,"M");
            $frequencyDetail = $userObject->mergedRole->filterParameterValue("core.mailer","NOTIFICATIONS_EMAIL_FREQUENCY_USER", AJXP_REPO_SCOPE_ALL,"5");
            $nextFrequency = $this->computeEmailSendDate(
                $frequencyType,
                $frequencyDetail
            );

            if(!empty($nextFrequency)){
                if (!dibi::isConnected()) {
                    dibi::connect($this->getDibiDriver());
                }
                foreach ($arrayRecipients as $recipient) {
                    try {
                        dibi::query("INSERT INTO [ajxp_mail_queue] ([recipient],[url],[date_event],[notification_object],[html]) VALUES (%s,%s,%s,%bin,%i) ",
                            $recipient,
                            $notification->getNode()->getUrl(),
                            $nextFrequency,
                            serialize($notification),
                            $useHtml);
                    } catch (Exception $e) {
                        $this->logError("[mailer]", $e->getMessage());
                    }
                }
            }else{
                $this->logError("[mailer]", "Could not determine email frequency from $frequencyType / $frequencyDetail for send email to user ".$userObject->getId());
            }
        } else {
            $mailer = PluginsService::getInstance()->getActivePluginsForType("mailer", true);
            if ($mailer !== false) {
                try {
                    $mailer->sendMail(
                        array($notification->getTarget()),
                        $notification->getDescriptionShort(),
                        $notification->getDescriptionLong(),
                        $notification->getAuthor(),
                        $notification->getMainLink(),
                        $useHtml
                    );
                } catch (Exception $e) {
                    throw new PydioException($e->getMessage());
                }
            }
        }
    }

    public function sendMail($recipients, $subject, $body, $from = null, $imageLink = null, $useHtml = true)
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
            if(!$useHtml){
                if (is_file(AJXP_INSTALL_PATH."/".$layoutFolder.$lang.".txt")) {
                    $layout = implode("", file(AJXP_INSTALL_PATH."/".$layoutFolder.$lang.".txt"));
                } else if (is_file(AJXP_INSTALL_PATH."/".$layoutFolder."en.txt")) {
                    $layout = implode("", file(AJXP_INSTALL_PATH."/".$layoutFolder."en.txt"));
                } else{
                    $layout = "AJXP_MAIL_BODY";
                }
            }else{
                if (is_file(AJXP_INSTALL_PATH."/".$layoutFolder.$lang.".html")) {
                    $layout = implode("", file(AJXP_INSTALL_PATH."/".$layoutFolder.$lang.".html"));
                } else if (is_file(AJXP_INSTALL_PATH."/".$layoutFolder."en.html")) {
                    $layout = implode("", file(AJXP_INSTALL_PATH."/".$layoutFolder."en.html"));
                }
            }
        }
        if (strpos($layout, "AJXP_MAIL_BODY") !== false) {
            $body = str_replace("AJXP_MAIL_BODY", $useHtml?nl2br($body):$body, $layout);
        }
        if ($imageLink != null && $useHtml) {
            $body = str_replace(array("AJXP_IMAGE_LINK"), "<a href='".$imageLink."'>".'<img alt="Download" width="100" style="width: 100px;" src="cid:download_id">'."</a>", $body);
            $images[] = array("path" => AJXP_INSTALL_PATH."/".$layoutFolder."/download.png", "cid" => "download_id");
        } else {
            $body = str_replace(array("AJXP_IMAGE_LINK", "AJXP_IMAGE_END"), "", $body);
        }
        $body = str_replace("AJXP_MAIL_SUBJECT", $subject, $body);
        $this->sendMailImpl($recipients, $subject, $body, $from, $images, $useHtml);
        if (AJXP_SERVER_DEBUG) {
            if(!$useHtml) {
                $rowBody = '[TEXT ONLY] '.AjxpMailer::simpleHtml2Text($rowBody);
            }
            $line = "------------------------------------------------------------------------\n";
            file_put_contents($this->mailCache, $line."Sending mail from ".print_r($from, true)." to ".print_r($recipients, true)."\nSubject: $subject\nBody:\n$rowBody\n", FILE_APPEND);
        }
    }

    protected function sendMailImpl($recipients, $subject, $body, $from = null, $images = array(), $useHtml = true){
    }

    public function sendMailAction(\Psr\Http\Message\ServerRequestInterface &$requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {
        $mess = ConfService::getMessages();
        $mailers = PluginsService::getInstance()->getActivePluginsForType("mailer");
        if (!count($mailers)) {
            throw new Exception($mess["core.mailer.3"]);
        }

        $httpVars = $requestInterface->getParsedBody();
        $mailer = array_pop($mailers);

        //$toUsers = array_merge(explode(",", $httpVars["users_ids"]), explode(",", $httpVars["to"]));
        //$toGroups =  explode(",", $httpVars["groups_ids"]);
        $toUsers = $httpVars["emails"];

        $emails = $this->resolveAdresses($toUsers);
        $from = $this->resolveFrom($httpVars["from"]);
        $imageLink = isSet($httpVars["link"]) ? $httpVars["link"] : null;

        $subject = $httpVars["subject"];
        $body = $httpVars["message"];
        $x = new \Pydio\Core\Http\Response\SerializableResponseStream();
        $responseInterface = $responseInterface->withBody($x);

        if (count($emails)) {
            $mailer->sendMail($emails, $subject, $body, $from, $imageLink);
            $x->addChunk(new \Pydio\Core\Http\Message\UserMessage(str_replace("%s", count($emails), $mess["core.mailer.1"])));
        } else {
            $x->addChunk(new \Pydio\Core\Http\Message\UserMessage($mess["core.mailer.2"], LOG_LEVEL_ERROR));
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
            if (is_object($recipient) && $recipient instanceof AbstractAjxpUser) {
                $resolved = $this->abstractUserToAdress($recipient);
                if ($resolved !== false) {
                    $realRecipients[] = $resolved;
                }
            } else if (is_array($recipient)) {
                if (array_key_exists("adress", $recipient)) {
                    if (!array_key_exists("name", $recipient)) {
                        $recipient["name"] = $recipient["adress"];
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

    public static function simpleHtml2Text($html){

        $html = preg_replace('/<br\>/', "\n", $html);
        $html = preg_replace('/<br>/', "\n", $html);
        $html = preg_replace('/<li>/', "\n * ", $html);
        $html = preg_replace('/<\/li>/', '', $html);
        return strip_tags($html);
    }

    /**
     * Install SQL table using a dibi driver data
     * @param $param array("SQL_DRIVER" => $dibiDriverData)
     * @return mixed
     */
    public function installSQLTables($param)
    {
        $base = basename($this->getBaseDir());
        if($base == "core.mailer"){
            $p = Utils::cleanDibiDriverParameters($param["SQL_DRIVER"]);
            return Utils::runCreateTablesQuery($p, $this->getBaseDir()."/create.sql");
        }
        return true;
    }
}
