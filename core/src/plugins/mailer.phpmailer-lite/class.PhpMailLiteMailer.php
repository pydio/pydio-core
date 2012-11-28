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

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package info.ajaxplorer.plugins
 * Send notifications to user on some predefined actions
 */
class PhpMailLiteMailer extends AjxpMailer {

    protected function sendMailImpl($recipients, $subject, $body, $from = null){

        require_once("lib/class.phpmailer-lite.php");
        $realRecipients = $this->resolveAdresses($recipients);

        // NOW IF THERE ARE RECIPIENTS FOR ANY REASON, GO
		$mail = new PHPMailerLite(true);
		$mail->Mailer = $this->pluginConf["MAILER"];
        $from = $this->resolveFrom($from);
        if(!empty($from)){
            if($from["adress"] != $from["name"]){
                $mail->SetFrom($from["adress"], $from["name"]);
            }else{
                $mail->setFrom($from["adress"]);
            }
        }
		foreach ($realRecipients as $address){
            if($address["adress"] == $address["name"]){
                $mail->AddAddress(trim($address["adress"]));
            }else{
                $mail->AddAddress(trim($address["adress"]), trim($address["name"]));
            }
		}
		$mail->WordWrap = 50;                                 // set word wrap to 50 characters
		$mail->IsHTML(true);                                  // set email format to HTML

        $mail->Subject = $subject;
		$mail->Body = nl2br($body);
		$mail->AltBody = strip_tags($mail->Body);

		if(!$mail->Send())
		{
			$message = "Message could not be sent\n";
			$message .= "Mailer Error: " . $mail->ErrorInfo;
			throw new Exception($message);
		}

    }

}