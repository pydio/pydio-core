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

    public function sendMail($recipients, $subject, $body){
        require("lib/class.phpmailer-lite.php");
        $realRecipients = array();
        // Recipients can be either AbstractAjxpUser objects, either array(adress, name), either "adress".
        foreach($recipients as $recipient){
            if(is_object($recipient) && is_a($recipient, "AbstractAjxpUser")){
                $userEmail = $recipient->personalRole->filterParameterValue("core.conf", "email", AJXP_REPO_SCOPE_ALL, "");
                if(empty($userEmail)) {
                    continue;
                }
                $displayName = $recipient->personalRole->filterParameterValue("core.conf", "USER_DISPLAY_NAME", AJXP_REPO_SCOPE_ALL, "");
                if(empty($displayName)) $displayName = $recipient->getId();
                $realRecipients[] = array("name" => $displayName, "adress" => $userEmail);
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
                    $realRecipients[] = array("name" => $recipient, "adress" => $recipient);
                }
            }
        }

        // NOW IF THERE ARE RECIPIENTS FOR ANY REASON, GO
		$mail = new PHPMailerLite(true);
		$mail->Mailer = $this->pluginConf["MAILER"];
		$mail->SetFrom(trim($this->pluginConf["FROM"]), trim($this->pluginConf["FROM_NAME"]));
		foreach ($realRecipients as $address){
			$mail->AddAddress(trim($address["adress"]), trim($address["name"]));
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