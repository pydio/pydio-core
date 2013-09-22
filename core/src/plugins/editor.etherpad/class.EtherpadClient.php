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

class EtherpadClient extends AJXP_Plugin
{
    public $baseURL = "http://localhost:9001";
    public $apiKey = "";

    public function switchAction($actionName, $httpVars, $fileVars)
    {
        $this->baseURL = rtrim($this->getFilteredOption("ETHERPAD_SERVER"), "/");
        $this->apiKey =  $this->getFilteredOption("ETHERPAD_APIKEY");

        if (isSet($httpVars["file"])) {

            $repository = ConfService::getRepository();
            if (!$repository->detectStreamWrapper(false)) {
                return false;
            }
            $plugin = AJXP_PluginsService::findPlugin("access", $repository->getAccessType());
            $streamData = $plugin->detectStreamWrapper(true);
            $destStreamURL = $streamData["protocol"]."://".$repository->getId()."/";
            $filename = $destStreamURL. AJXP_Utils::securePath($httpVars["file"]);

            if (!is_file($filename)) {
                throw new Exception("Cannot find file!");
            }
        }

        require_once("etherpad-client/etherpad-lite-client.php");
        $client = new EtherpadLiteClient($this->apiKey,$this->baseURL."/api");
        $userName = AuthService::getLoggedUser()->getId();
        $res = $client->createAuthorIfNotExistsFor($userName, $userName);
        $authorID = $res->authorID;
        $res2 = $client->createGroupIfNotExistsFor("ajaxplorer");
        $groupID = $res2->groupID;

        if ($actionName == "etherpad_create") {

            if (isSet($httpVars["pad_name"])) {

                $padID = $httpVars["pad_name"];
                $startContent = "";
                if ($httpVars["pad_type"] && $httpVars["pad_type"] == 'free') {
                    $padID = "FREEPAD__".$padID;
                }

            } else if (isSet($httpVars["file"])) {

                $startContent = file_get_contents($filename);
                if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) == "html") {
                    $startContentHTML = $startContent;
                }
                $padID = AJXP_Utils::slugify($httpVars["file"]);

            }


            $resP = $client->listPads($res2->groupID);

            $pads = $resP->padIDs;
            if (!in_array($groupID.'$'.$padID, $pads)) {
                $res3 = $client->createGroupPad($groupID, $padID, null);
                if (isSet($startContentHTML)) {
                    $client->setHTML($groupID.'$'.$padID, $startContentHTML);
                } else if (!empty($startContent)) {
                    $client->setText($groupID.'$'.$padID, $startContent);
                }
            } else {
                // Check if content needs relaunch!
                $test = $client->getText($groupID.'$'.$padID);
                if (!empty($startContent) && $test->text != $startContent) {
                    if (isSet($startContentHTML)) {
                        $client->setHTML($groupID.'$'.$padID, $startContentHTML);
                    } else {
                        $client->setText($groupID.'$'.$padID, $startContent);
                    }
                }
            }

            $res4 = $client->createSession($groupID, $authorID, time()+14400);
            $sessionID = $res4->sessionID;

            setcookie('sessionID', $sessionID, null, "/");

            $padID = $groupID.'$'.$padID;

            $data = array(
                "url" => $this->baseURL."/p/".$padID,
                "padID" => $padID,
                "sessionID" => $sessionID,
            );

            HTMLWriter::charsetHeader('application/json');
            echo(json_encode($data));

        } else if ($actionName == "etherpad_save") {

            $node = new AJXP_Node($filename);

            $padID = $httpVars["pad_id"];
            if (isSet($startContentHTML)) {
                $res = $client->getHTML($padID);
            } else {
                $res = $client->getText($padID);
            }

            AJXP_Controller::applyHook("node.before_change", array($node, strlen($res->text)));
            file_put_contents($filename, $res->text);
            AJXP_Controller::applyHook("node.change", array($node, $node));

        } else if ($actionName == "etherpad_close") {

            // WE SHOULD DETECT IF THERE IS NOBODY CONNECTED ANYMORE, AND DELETE THE PAD.
            $sessionID = $httpVars["session_id"];
            $client->deleteSession($sessionID);

        } else if ($actionName == "etherpad_proxy_api") {

            if ($httpVars["api_action"] == "list_pads") {
                $res = $client->listPads($groupID);
            } else if ($httpVars["api_action"] == "list_authors_for_pad") {
                $res = $client->listAuthorsOfPad($httpVars["pad_id"]);
            }
            HTMLWriter::charsetHeader("application/json");
            echo(json_encode($res));

        }

    }


}
