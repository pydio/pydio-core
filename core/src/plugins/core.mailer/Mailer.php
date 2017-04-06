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
 * The latest code can be found at <https://pydio.com>.
 */

namespace Pydio\Mailer\Core;

use DateTime;
use dibi;
use DibiException;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Http\Message\UserMessage;
use Pydio\Core\Http\Response\SerializableResponseStream;
use Pydio\Core\Model\ContextInterface;
use Pydio\Conf\Core\AbstractUser;
use Pydio\Core\Model\UserInterface;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\DBHelper;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\OptionsHelper;

use Pydio\Core\Controller\HTMLWriter;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Core\PluginFramework\SqlTableProvider;
use Pydio\Notification\Core\Notification;
use Pydio\Tasks\Task;
use Pydio\Tasks\TaskService;
use Zend\Diactoros\Response\JsonResponse;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Core
 */
class Mailer extends Plugin implements SqlTableProvider
{

    public static $TEMPLATES = [
        "html" => [
            "body"        => '<div id="digest">%s</div>',   // Argument section
            "section"     => '%s%s',                        // Argument title, listWrapper
            "title"       => '<h1>%s</h1>',                 // Argument %var%
            "listWrapper" => '<ul>%s</ul>',                 // Argument list
            "list"        => '<li>%s</li>'                  // Argument %var%
        ],
        "plain" => [
            "body"        => '%s',                          // Argument section
            "section"     => '%s%s',                        // Argument title, listWrapper
            "title"       => '%s\n\n',                      // Argument %var%
            "listWrapper" => '%s',                          // Argument list
            "list"        => '%s\n'                         // Argument %var%
        ]
    ];

    public $mailCache;
    protected $_dibiDriver;

    /**
     * @param ContextInterface $ctx
     * @param array $options
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        parent::init($ctx, $options);
        if (AJXP_SERVER_DEBUG) {
            $this->mailCache = $this->getPluginWorkDir(true) . "/mailbox";
        }
        $pConf = $this->pluginConf["UNIQUE_MAILER_INSTANCE"];
        if (!empty($pConf)) {
            $pService = PluginsService::getInstance($ctx);
            $p = ConfService::instanciatePluginFromGlobalParams($pConf, "Pydio\\Mailer\\Core\\Mailer", $pService);
            if($p !== false){
                $pService->setPluginUniqueActiveForType($p->getType(), $p->getName(), $p);
            }
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function getDibiDriver()
    {
        if (!isset($this->_dibiDriver)) {
            $this->_dibiDriver = OptionsHelper::cleanDibiDriverParameters(array("group_switch_value" => "core"));
        }
        return $this->_dibiDriver;
    }

    /**
     * @return int
     * @throws Exception
     */
    public function getConsumerLock() {

        $workDir = $this->getPluginWorkDir(true);
        $consumerLockFile = $workDir . DIRECTORY_SEPARATOR . "/consume_mail_queue.lock";

        // Opening the file
        $consumerLock = fopen($consumerLockFile, "w");

        // Making sure the file gets closed and the lock gets released at the end of the script
        register_shutdown_function(function () use ($consumerLock) {
            fflush($consumerLock);
            flock($consumerLock, LOCK_UN);

            fclose($consumerLock);
        });

        // Retrieving the lock
        while (!flock($consumerLock, LOCK_EX)) {
            sleep(60);
        }

        // Writing the time the lock was granted
        $time = time();
        ftruncate($consumerLock, 0);
        fwrite($consumerLock, $time);

        return $time;
    }


    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @throws PydioException
     */
    public function mailConsumeQueue(ServerRequestInterface $requestInterface, ResponseInterface &$responseInterface)
    {
        /** @var ContextInterface $ctx */
        $ctx        = $requestInterface->getAttribute("ctx");
        $httpVars   = $requestInterface->getParsedBody();
        $verbose    = $httpVars["log-output"];
        $taskUid    = $requestInterface->getAttribute("pydio-task-id");

        $logInfo = function () {};
        if ($verbose) {
            $logInfo = function ($str) {
                fwrite(STDOUT, $str . "\n");
            };
        }else if(!empty($taskUid)){
            $logInfo = function ($str) use ($taskUid) {
                TaskService::getInstance()->updateTaskStatus($taskUid, Task::STATUS_RUNNING, $str);
            };
        }


        $mailer = PluginsService::getInstance($ctx)->getActivePluginsForType("mailer", true);
        if (!$mailer instanceof Mailer) {
            throw new PydioException("Cannot find active mailer!");
        }
        if (!dibi::isConnected()) {
            dibi::connect($this->getDibiDriver());
        }
        if ($this->_dibiDriver["driver"] == "postgre") {
            dibi::query("SET bytea_output=escape");
        }

        // Get the queue consumer lock and the time it was given
        $time = $this->getConsumerLock();

        try {
            $querySQL = dibi::query("SELECT * FROM [ajxp_mail_queue] WHERE [date_event] <= %s", $time);
        } catch (DibiException $e) {
            throw new PydioException($e->getMessage());
        }
        //$querySQL->fetch();
        //$resultsSQL = $querySQL->fetchAll();
        $numRows = $querySQL->count();

        $results = [];

        HTMLWriter::charsetHeader("text/json");

        if ($numRows == 0) {
            $logInfo("Nothing to process");
            $output = array("report" => "Sent 0 emails", "detail" => "");
            $responseInterface = new JsonResponse($output);
            if(!empty($taskUid)){
                TaskService::getInstance()->updateTaskStatus($taskUid, Task::STATUS_COMPLETE, "Sent 0 emails");
            }
            return;
        }

        $logInfo("Processing " . $numRows . " rows.");

        $i = 0;
        // We need to send one email :
        // - per user
        // - per email type (HTML or PLAIN)
        while($value = $querySQL->fetch()) {

            // Retrieving user information
            $recipient = $value['recipient'];
            $logInfo("Processing notification ".($i+1)."/$numRows");

            // Retrieving Email type information
            $emailType = ($value["html"] == 1) ? "html" : "plain";

            // Retrieving notification information
            /** @var Notification $notification */
            $notification = unserialize($value["notification_object"]);
            if(!$notification instanceof Notification){
                continue;
            }

            $action = $notification->getAction();
            $author = $notification->getAuthor();
            $node   = $notification->getNode();
            if(!$node instanceof AJXP_Node){
                continue;
            }
            try {
                @$node->loadNodeInfo();
            } catch(Exception $e){
                continue;
            }

            if ($node->isLeaf() && !$node->isRoot()) {
                $dirName = $node->getParent()->getPath();
            } else {
                $dirName = $node->getPath();
                if ($dirName === null) {
                    $dirName = '/';
                }
            }
            $key = sprintf("%s|%s|%s", $action, $author, $dirName);

            // Retrieving workspace information
            if($node->getRepository() != null) {
                $workspace = $node->getRepository()->getDisplay();
            } else {
                $workspace = "Deleted Workspace";
            }

            $results[$emailType][$recipient][$workspace][$key][] = $notification;

            $logInfo("Processed recipient ".($i+1)."/$numRows (" . $recipient.")");

            $i ++;

        }

        $logInfo("Created digest array.");

        $subject = LocaleService::getMessages()["core.mailer.9"];

        $success = 0;
        $errors = [];
        foreach ($results as $emailType => $recipients) {

            $isHTML = $emailType == "html";

            $i = 0;
            foreach ($recipients as $recipient => $workspaces) {
                $logInfo("Processing " . ++$i . " out of " . count($recipients) . " " . $emailType . " emails " . $recipient);
                $body = $this->_buildDigest($workspaces, $emailType);
                $success++;
                try {
                    $mailer->sendMail(
                        $ctx,
                        [$recipient],
                        $subject,
                        $body,
                        null,
                        null,
                        $isHTML
                    );

                    $success++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to send email to " . $recipient . ": " . $e->getMessage();
                }
            }
        }

        // Clearing memory
        unset($results);

        try {
            dibi::query('DELETE FROM [ajxp_mail_queue] WHERE [date_event] <= %s', $time);
        } catch (DibiException $e) {
            throw new PydioException($e->getMessage());
        }

        $output = array("report" => "Sent ".$success." emails", "errors" => $errors);
        $responseInterface = new JsonResponse($output);
        if(!empty($taskUid)){
            TaskService::getInstance()->updateTaskStatus($taskUid, Task::STATUS_COMPLETE, "Sent $success emails");
        }

    }

    /**
     * @param $workspaces
     * @param $emailType
     * @return string
     */
    private function _buildDigest($workspaces, $emailType) {

        $template = self::$TEMPLATES[$emailType];

        $sections = [];
        foreach ($workspaces as $workspace => $keys) {

            $title = "";
            $li = [];

            foreach ($keys as $key => $notifications) {

                $descriptions = [];

                /** @var Notification $notification */
                foreach ($notifications as $notification) {
                    if (empty($current) && $notification->getNode()->getRepository() !== null) {
                        $title = sprintf($template["title"], $notification->getDescriptionLocation());
                    }

                    $description = $notification->getDescriptionLong(true);

                    if(array_key_exists($description, $descriptions)) {
                        $descriptions[$description]++;
                    } else {
                        $descriptions[$description] = 1;
                    }
                }

                foreach ($descriptions as $description => $count){
                    $li[] = sprintf($template["list"], $description . ($count > 1 ? ' ('.$count.')' : ''));
                }
            }

            $listWrapper = sprintf($template["listWrapper"], join("", $li));
            $sections[] = sprintf($template["section"], $title, $listWrapper);
        }

        return sprintf($template["body"], join("", $sections));
    }



    /**
     * @param $int
     * @return string
     */
    protected function stringify($int)
    {
        return ($int < 10 ? "0" . $int : "" . $int);
    }

    /**
     * @param $frequencyType
     * @param $frequencyDetail
     * @return DateTime|int|null|string
     */
    protected function computeEmailSendDate($frequencyType, $frequencyDetail)
    {

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

    /**
     * @param Notification $notification
     * @throws Exception
     * @throws PydioException
     */
    public function processNotification(Notification &$notification)
    {
            // Inserting the node information.
        try {
            $notification->getNode()->loadNodeInfo();
        } catch (Exception $e) {
            // Do nothing
        }

        try {
            $userObject = UsersService::getUserById($notification->getTarget());
        } catch (\Pydio\Core\Exception\UserNotFoundException $e) {
            $messages = LocaleService::getMessages();
            throw new PydioException($messages['core.mailer.2']);
        }
        if ($userObject->getMergedRole()->filterParameterValue("core.mailer", "NOTIFICATIONS_EMAIL_GET", AJXP_REPO_SCOPE_ALL, "true") !== "true") {
            // User does not want to receive any emails.
            return;
        }

        $notification_email = $userObject->getMergedRole()->filterParameterValue("core.mailer", "NOTIFICATIONS_EMAIL", AJXP_REPO_SCOPE_ALL, "");
        $arrayRecipients = array();
        $mainRecipient = $userObject->getMergedRole()->filterParameterValue("core.conf", "email", AJXP_REPO_SCOPE_ALL, "");
        $useHtml = $userObject->getMergedRole()->filterParameterValue("core.mailer", "NOTIFICATIONS_EMAIL_SEND_HTML", AJXP_REPO_SCOPE_ALL, "true") === "true" ? 1 : 0;

        if (!empty($mainRecipient)) $arrayRecipients[] = $mainRecipient;
        $additionalRecipients = array_map("trim", explode(',', $notification_email));
        foreach ($additionalRecipients as $addR) {
            if (!empty($addR)) $arrayRecipients[] = $addR;
        }

        if ($this->pluginConf["MAILER_ACTIVATE_QUEUE"] && count($arrayRecipients)) {

            $frequencyType = $userObject->getMergedRole()->filterParameterValue("core.mailer", "NOTIFICATIONS_EMAIL_FREQUENCY", AJXP_REPO_SCOPE_ALL, "M");
            $frequencyDetail = $userObject->getMergedRole()->filterParameterValue("core.mailer", "NOTIFICATIONS_EMAIL_FREQUENCY_USER", AJXP_REPO_SCOPE_ALL, "5");
            $nextFrequency = $this->computeEmailSendDate(
                $frequencyType,
                $frequencyDetail
            );

            if (!empty($nextFrequency)) {
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
            } else {
                $this->logError("[mailer]", "Could not determine email frequency from $frequencyType / $frequencyDetail for send email to user " . $userObject->getId());
            }
        } else {
            $mailer = PluginsService::getInstance($notification->getNode()->getContext())->getActivePluginsForType("mailer", true);
            if ($mailer !== false && $mailer instanceof Mailer) {
                try {
                    $mailer->sendMail(
                        $notification->getNode()->getContext(),
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

    /**
     * @param ContextInterface $ctx
     * @param $recipients
     * @param $subject
     * @param $body
     * @param null $from
     * @param null $imageLink
     * @param bool $useHtml
     */
    public function sendMail(ContextInterface $ctx, $recipients, $subject, $body, $from = null, $imageLink = null, $useHtml = true)
    {
        $prepend = ConfService::getContextConf($ctx, "SUBJECT_PREPEND", "mailer");
        $append = ConfService::getContextConf($ctx, "SUBJECT_APPEND", "mailer");
        $layoutFolder = ConfService::getContextConf($ctx, "LAYOUT_FOLDER", "mailer");
        $layout = ConfService::getContextConf($ctx, "BODY_LAYOUT", "mailer");
        $forceFrom = ConfService::getContextConf($ctx, "FORCE_UNIQUE_FROM", "mailer");
        $coreFrom = ConfService::getContextConf($ctx, "FROM", "mailer");

        if ($forceFrom && $coreFrom != null) {
            $coreFromName = ConfService::getContextConf($ctx, "FROM_NAME", "mailer");
            $from = array("adress" => $coreFrom, "name" => $coreFromName);
        }
        $rowBody = $body;
        $images = array();
        if (!empty($prepend)) $subject = $prepend . " " . $subject;
        if (!empty($append)) $subject .= " " . $append;
        if (!empty($layoutFolder)) {
            $layoutFolder .= "/";
            $lang = LocaleService::getLanguage();
            if (!$useHtml) {
                if (is_file(AJXP_INSTALL_PATH . "/" . $layoutFolder . $lang . ".txt")) {
                    $layout = implode("", file(AJXP_INSTALL_PATH . "/" . $layoutFolder . $lang . ".txt"));
                } else if (is_file(AJXP_INSTALL_PATH . "/" . $layoutFolder . "en.txt")) {
                    $layout = implode("", file(AJXP_INSTALL_PATH . "/" . $layoutFolder . "en.txt"));
                } else {
                    $layout = "AJXP_MAIL_BODY";
                }
            } else {
                if (is_file(AJXP_INSTALL_PATH . "/" . $layoutFolder . $lang . ".html")) {
                    $layout = implode("", file(AJXP_INSTALL_PATH . "/" . $layoutFolder . $lang . ".html"));
                } else if (is_file(AJXP_INSTALL_PATH . "/" . $layoutFolder . "en.html")) {
                    $layout = implode("", file(AJXP_INSTALL_PATH . "/" . $layoutFolder . "en.html"));
                }
            }
        }
        if (strpos($layout, "AJXP_MAIL_BODY") !== false) {
            $body = str_replace("AJXP_MAIL_BODY", $useHtml ? nl2br($body) : $body, $layout);
        }
        if ($imageLink != null && $useHtml) {
            $body = str_replace(array("AJXP_IMAGE_LINK"), "<a href='" . $imageLink . "'>" . '<img alt="Download" width="100" style="width: 100px;" src="cid:download_id">' . "</a>", $body);
            $images[] = array("path" => AJXP_INSTALL_PATH . "/" . $layoutFolder . "/download.png", "cid" => "download_id");
        } else {
            $body = str_replace(array("AJXP_IMAGE_LINK", "AJXP_IMAGE_END"), "", $body);
        }
        $body = str_replace("AJXP_MAIL_SUBJECT", $subject, $body);
        $this->sendMailImpl($ctx, $recipients, $subject, $body, $from, $images, $useHtml);
        if (AJXP_SERVER_DEBUG) {
            if (!$useHtml) {
                $rowBody = '[TEXT ONLY] ' . Mailer::simpleHtml2Text($rowBody);
            }
            $line = "------------------------------------------------------------------------\n";
            file_put_contents($this->mailCache, $line . "Sending mail from " . print_r($from, true) . " to " . print_r($recipients, true) . "\nSubject: $subject\nBody:\n$rowBody\n", FILE_APPEND);
        }
    }

    /**
     * @param ContextInterface $ctx
     * @param $recipients
     * @param $subject
     * @param $body
     * @param null $from
     * @param array $images
     * @param bool $useHtml
     */
    protected function sendMailImpl(ContextInterface $ctx, $recipients, $subject, $body, $from = null, $images = array(), $useHtml = true)
    {
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     * @throws Exception
     */
    public function sendMailAction(ServerRequestInterface &$requestInterface, ResponseInterface &$responseInterface)
    {
        $mess = LocaleService::getMessages();
        /** @var ContextInterface $ctx */
        $ctx = $requestInterface->getAttribute("ctx");
        $mailers = PluginsService::getInstance($ctx)->getActivePluginsForType("mailer");
        if (!count($mailers)) {
            throw new Exception($mess["core.mailer.3"]);
        }

        $httpVars = $requestInterface->getParsedBody();
        $mailer = array_pop($mailers);

        $toUsers = array_map(function($email){
            return InputFilter::sanitize($email, InputFilter::SANITIZE_EMAILCHARS);
        }, $httpVars["emails"]);

        $from = $this->resolveFrom($ctx, InputFilter::sanitize($httpVars["from"], InputFilter::SANITIZE_EMAILCHARS));
        $imageLink = isSet($httpVars["link"]) ? $httpVars["link"] : null;

        $multipleSubjects = isSet($httpVars["subjects"]) && count($httpVars["subjects"]) === count($toUsers) ? $httpVars["subjects"] : null;
        $multipleMessages = isSet($httpVars["messages"]) && count($httpVars["messages"]) === count($toUsers) ? $httpVars["messages"] : null;

        $sentMails = 0;
        if(isSet($multipleMessages) || isset($multipleSubjects)){
            // Email contents are different per user
            foreach($toUsers as $index => $toUser){
                $email = $this->resolveAdresses($ctx, [$toUser]);
                if(count($email)){
                    $subject = isSet($multipleSubjects) ? $multipleSubjects[$index] : $httpVars["subject"];
                    $body = isSet($multipleMessages) ? $multipleMessages[$index] : $httpVars["message"];
                    $mailer->sendMail($ctx, $email, $subject, $body, $from, $imageLink);
                    $sentMails ++;
                }
            }
        }else{
            $emails = $this->resolveAdresses($ctx, $toUsers);
            if(count($emails)){
                $subject = $httpVars["subject"];
                $body = $httpVars["message"];
                $mailer->sendMail($requestInterface->getAttribute("ctx"), $emails, $subject, $body, $from, $imageLink);
                $sentMails = count($emails);
            }
        }

        $x = new SerializableResponseStream();
        $responseInterface = $responseInterface->withBody($x);
        if($sentMails){
            $x->addChunk(new UserMessage(str_replace("%s", $sentMails, $mess["core.mailer.1"])));
        }else {
            $x->addChunk(new UserMessage($mess["core.mailer.2"], LOG_LEVEL_ERROR));
        }

    }

    /**
     * @param ContextInterface $ctx
     * @param null $fromAdress
     * @return array|mixed
     */
    public function resolveFrom(ContextInterface $ctx, $fromAdress = null)
    {
        $fromResult = array();
        if ($fromAdress != null) {
            $arr = $this->resolveAdresses($ctx, array($fromAdress));
            if (count($arr)) $fromResult = $arr[0];
        } else if ($ctx->hasUser()) {
            $arr = $this->resolveAdresses($ctx, array($ctx->getUser()));
            if (count($arr)) $fromResult = $arr[0];
        }
        if (!count($fromResult)) {
            $f = ConfService::getContextConf($ctx, "FROM", "mailer");
            $fName = ConfService::getContextConf($ctx, "FROM_NAME", "mailer");
            $fromResult = array("adress" => $f, "name" => $fName);
        }
        return $fromResult;
    }

    /**
     * @param array $recipients
     * @return array
     *
     */
    public function resolveAdresses(ContextInterface $ctx, $recipients)
    {
        $realRecipients = array();
        foreach ($recipients as $recipient) {
            if (is_string($recipient) && strpos($recipient, "/AJXP_TEAM/") === 0) {
                $confDriver = ConfService::getConfStorageImpl();
                if (method_exists($confDriver, "teamIdToUsers")) {
                    $newRecs = $confDriver->teamIdToUsers($ctx->getUser(), str_replace("/AJXP_TEAM/", "", $recipient));
                }
            }
        }
        if (isSet($newRecs)) {
            $recipients = array_merge($recipients, $newRecs);
        }
        // Recipients can be either UserInterface objects, either array(adress, name), either "adress".
        foreach ($recipients as $recipient) {
            if (is_object($recipient) && $recipient instanceof AbstractUser) {
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
                    if (UsersService::userExists($recipient)) {
                        $user = UsersService::getUserById($recipient, false);
                        $res = $this->abstractUserToAdress($user);
                        if ($res !== false) $realRecipients[] = $res;
                    } else if ($this->validateEmail($recipient)) {
                        $realRecipients[] = array("name" => $recipient, "adress" => $recipient);
                    }
                }
            }
        }

        return $realRecipients;
    }

    /**
     * @param UserInterface $user
     * @return array|bool
     */
    public function abstractUserToAdress(UserInterface $user)
    {
        // SHOULD CHECK THAT THIS USER IS "AUTHORIZED" TO AVOID SPAM
        $userEmail = $user->getPersonalRole()->filterParameterValue("core.conf", "email", AJXP_REPO_SCOPE_ALL, "");
        if (empty($userEmail)) {
            return false;
        }
        $displayName = $user->getPersonalRole()->filterParameterValue("core.conf", "USER_DISPLAY_NAME", AJXP_REPO_SCOPE_ALL, "");
        if (empty($displayName)) $displayName = $user->getId();
        return array("name" => $displayName, "adress" => $userEmail);
    }


    /**
     * @param $email
     * @return bool
     */
    public function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @param $html
     * @return string
     */
    public static function simpleHtml2Text($html)
    {

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
        if ($base == "core.mailer") {
            $p = OptionsHelper::cleanDibiDriverParameters($param["SQL_DRIVER"]);
            return DBHelper::runCreateTablesQuery($p, $this->getBaseDir() . "/create.sql");
        }
        return true;
    }
}
