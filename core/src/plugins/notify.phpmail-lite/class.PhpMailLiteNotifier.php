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
class PhpMailLiteNotifier extends AJXP_Plugin {
		
	public function preProcess($action, $httpVars, $fileVars){
		if(!is_array($this->pluginConf) || !isSet($this->pluginConf["TO"])){
			throw new Exception("Cannot find configuration for plugin notify.phpmail-lite! Make sur that you have filled the options in the GUI, or that the .inc file was dropped inside the /conf/ folder!");
		}
		require("lib/class.phpmailer-lite.php");

        // Parse options
        if(is_string($this->pluginConf["FROM"])) {
            $this->pluginConf["FROM"] = $this->parseStringOption($this->pluginConf["FROM"]);
        }
        if(is_string($this->pluginConf["TO"])){
            $froms = explode(",", $this->pluginConf["TO"]);
            $this->pluginConf["TO"] = array_map(array($this, "parseStringOption"), $froms);
        }

		$mail = new PHPMailerLite(true);
		$mail->Mailer = $this->pluginConf["MAILER"];		
		$mail->SetFrom($this->pluginConf["FROM"]["address"], $this->pluginConf["FROM"]["name"]);
		
		foreach ($this->pluginConf["TO"] as $address){
			$mail->AddAddress($address["address"], $address["name"]);
		}		
		$mail->WordWrap = 50;                                 // set word wrap to 50 characters
		$mail->IsHTML(true);                                  // set email format to HTML
		
		$mail->Subject = $this->pluginConf["SUBJECT"];
        $userSelection = new UserSelection();
        $userSelection->initFromHttpVars($httpVars);
        $folder = $httpVars["dir"];
        $file = "";
        if(!$userSelection->isEmpty()){
            $file = implode(",", array_map("basename", $userSelection->getFiles()));
        }
        if($action == "upload" && isset($fileVars["userfile_0"])){
            $file = $fileVars["userfile_0"]["name"];
        }
        $subject = array("%user", "AJXP_USER", "AJXP_FILE", "AJXP_FOLDER", "AJXP_ACTION");
        $replace = array(AuthService::getLoggedUser()->getId(),
                         AuthService::getLoggedUser()->getId(),
                         $file,
                         $folder,
                         $action);
		$mail->Body = str_replace($subject, $replace, $this->pluginConf["BODY"]);
		$mail->AltBody = strip_tags($mail->Body);
		
		if(!$mail->Send())
		{
			$message = "Message could not be sent. <p>";
			$message .= "Mailer Error: " . $mail->ErrorInfo;
			throw new Exception($message);
		}
		
	}

    /**
     * @param $option String
     * @return array
     */
    function parseStringOption($option){
        if(strstr($option, ":")){
            list($name, $ad) = explode(":", $option);
            $option = array("address" => $ad, "name" => $name);
        }else{
            $option = array("address" => $option, "name" => $option);
        }
        return $option;
    }

}

?>