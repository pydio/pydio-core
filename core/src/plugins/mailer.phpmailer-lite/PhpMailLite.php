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

namespace Pydio\Mailer\Implementation;

use Exception;
use PHPMailer;
use phpmailerException;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Mailer\Core\Mailer;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Send notifications to user on some predefined actions
 * @package AjaXplorer_Plugins
 * @subpackage Mailer
 */
class PhpMailLite extends Mailer
{
    /**
     * @param ContextInterface $ctx
     * @param $recipients
     * @param $subject
     * @param $body
     * @param null $from
     * @param array $images
     * @param bool $useHtml
     * @throws Exception
     * @throws phpmailerException
     */
    protected function sendMailImpl(ContextInterface $ctx, $recipients, $subject, $body, $from = null, $images = array(), $useHtml = true)
    {
        require_once("core/vendor/autoload.php");
        $realRecipients = $this->resolveAdresses($ctx, $recipients);
        if(!count($realRecipients)){
            return;
        }
        // NOW IF THERE ARE RECIPIENTS FOR ANY REASON, GO
        $mail = new PHPMailer(true);

        //All option are set in the PHPMailer class
        $mail->Mailer = $this->getContextualOption(Context::emptyContext(), "MAILER");
        $mail->Sendmail = $this->getContextualOption(Context::emptyContext(), "SENDMAIL_PATH");
        $from = $this->resolveFrom($ctx, $from);
        if (!is_array($from) || empty($from["adress"])) {
            throw new Exception("Cannot send email without a FROM address. Please check your core.mailer configuration.");
        }
        if (!empty($from)) {
            if ($from["adress"] != $from["name"]) {
                $mail->setFrom($from["adress"], $from["name"]);
            } else {
                $mail->setFrom($from["adress"]);
            }
        }
        foreach ($realRecipients as $address) {
            if ($address["adress"] == $address["name"]) {
                $mail->addAddress(trim($address["adress"]));
            } else {
                $mail->addAddress(trim($address["adress"]), trim($address["name"]));
            }
        }
        $mail->WordWrap = 50;                                 // set word wrap to 50 characters
        $mail->isHTML($useHtml);                                  // set email format to HTML
        $mail->CharSet = "utf-8";
        $mail->Encoding = $this->getContextualOption(Context::emptyContext(), "MAIL_ENCODING");
        foreach ($images as $image) {
            $mail->addEmbeddedImage($image["path"], $image["cid"], '', 'base64', 'image/png');
        }

        $mail->Subject = $subject;
        if ($useHtml) {
            if (strpos($body, "<html") !== false) {
                $mail->Body = $body;
            } else {
                $mail->Body = "<html><body>" . nl2br($body) . "</body></html>";
            }
            $mail->AltBody = Mailer::simpleHtml2Text($mail->Body);
        } else {
            $mail->Body = Mailer::simpleHtml2Text($body);
        }

        if (!$mail->send()) {
            $message = "Message could not be sent\n";
            $message .= "Mailer Error: " . $mail->ErrorInfo;
            throw new Exception($message);
        }

    }

}
