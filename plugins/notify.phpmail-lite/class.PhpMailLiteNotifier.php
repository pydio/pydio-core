<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
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
class PhpMailLiteNotifier extends AJXP_Plugin {
		
	public function preProcess($action, $httpVars, $fileVars){
		if(!is_array($this->pluginConf) || !isSet($this->pluginConf["TO"])){
			throw new Exception("Cannot find configuration for plugin notify.phpmail-lite! Make sur the .inc file was dropped inside the /conf/ folder!");
		}
		require("lib/class.phpmailer-lite.php");
		
		$mail = new PHPMailerLite(true);
		$mail->Mailer = $this->pluginConf["MAILER"];		
		$mail->SetFrom($this->pluginConf["FROM"]["address"], $this->pluginConf["FROM"]["name"]);
		
		foreach ($this->pluginConf["TO"] as $address){
			$mail->AddAddress($address["address"], $address["name"]);
		}		
		$mail->WordWrap = 50;                                 // set word wrap to 50 characters
		$mail->IsHTML(true);                                  // set email format to HTML
		
		$mail->Subject = $this->pluginConf["SUBJECT"];
		$mail->Body = str_replace("%user", AuthService::getLoggedUser()->getId(), $this->pluginConf["BODY"]);
		$mail->AltBody = strip_tags($mail->Body);
		
		if(!$mail->Send())
		{
			$message = "Message could not be sent. <p>";
			$message .= "Mailer Error: " . $mail->ErrorInfo;
			throw new Exception($message);
		}
		
	}

}

?>