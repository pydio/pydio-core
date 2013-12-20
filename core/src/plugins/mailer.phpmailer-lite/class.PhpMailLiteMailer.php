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

/**
 * Send notifications to user on some predefined actions
 * @package AjaXplorer_Plugins
 * @subpackage Mailer
 */
class PhpMailLiteMailer extends AjxpMailer
{
    protected function sendMailImpl($recipients, $subject, $body, $from = null, $images = array())
    {
        require_once("lib/class.phpmailer-lite.php");
        $realRecipients = $this->resolveAdresses($recipients);

        // NOW IF THERE ARE RECIPIENTS FOR ANY REASON, GO
        $mail = new PHPMailerLite(true);
        $mail->Mailer = $this->getFilteredOption("MAILER");
        $mail->Sendmail = $this->getFilteredOption("SENDMAIL_PATH");
        $from = $this->resolveFrom($from);
        if (!is_array($from) || empty($from["adress"])) {
            throw new Exception("Cannot send email without a FROM address. Please check your core.mailer configuration.");
        }
        if (!empty($from)) {
            if ($from["adress"] != $from["name"]) {
                $mail->SetFrom($from["adress"], $from["name"]);
            } else {
                $mail->setFrom($from["adress"]);
            }
        }
        foreach ($realRecipients as $address) {
            if ($address["adress"] == $address["name"]) {
                $mail->AddAddress(trim($address["adress"]));
            } else {
                $mail->AddAddress(trim($address["adress"]), trim($address["name"]));
            }
        }
        $mail->WordWrap = 50;                                 // set word wrap to 50 characters
        $mail->IsHTML(true);                                  // set email format to HTML
        $mail->CharSet = "utf-8";
        $mail->Encoding = "quoted-printable";
        foreach ($images as $image) {
            $mail->AddEmbeddedImage($image["path"], $image["cid"], '', 'base64', 'image/png');
        }

        $mail->Subject = $subject;
        if (strpos($body, "<html")!==false) {
            $mail->Body = $body;
        } else {
            $mail->Body = "<html><body>".nl2br($body)."</body></html>";
        }
        $mail->AltBody = strip_tags($mail->Body);

        if (!$mail->Send()) {
            $message = "Message could not be sent\n";
            $message .= "Mailer Error: " . $mail->ErrorInfo;
            throw new Exception($message);
        }

    }

}
