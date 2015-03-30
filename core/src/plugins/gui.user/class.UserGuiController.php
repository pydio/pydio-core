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
 * @package AjaXplorer_Plugins
 * @subpackage Gui
 * @class UserGuiController
 * Handle the specific /user access point
 */
class UserGuiController extends AJXP_Plugin
{

    /**
     * Parse
     * @param DOMNode $contribNode
     */
    protected function parseSpecificContributions(&$contribNode)
    {
        parent::parseSpecificContributions($contribNode);
        if (substr($_SERVER["REQUEST_URI"], 0, strlen('/user')) != '/user') {
            if ($contribNode->nodeName == "client_configs") {
                $children = $contribNode->childNodes;
                foreach ($children as $child) {
                    if($child->nodeType == XML_ELEMENT_NODE) $contribNode->removeChild($child);
                }
            } else if ($contribNode->nodeName == "actions") {
                $children = $contribNode->childNodes;
                foreach ($children as $child) {
                    if ($child->nodeType == XML_ELEMENT_NODE && $child->nodeName == "action" && $child->getAttribute("name") == "login") {
                        $contribNode->removeChild($child);
                    }
                }

            }
        }

    }


    public function processUserAccessPoint($action, $httpVars, $fileVars)
    {
        switch ($action) {
            case "user_access_point":
                $setUrl = ConfService::getCoreConf("SERVER_URL");
                $realUri = "/";
                if(!empty($setUrl)){
                    $realUri = parse_url(ConfService::getCoreConf("SERVER_URL"), PHP_URL_PATH);
                }
                $requestURI = str_replace("//", "/", $_SERVER["REQUEST_URI"]);
                $uri = trim(str_replace(rtrim($realUri, "/")."/user", "", $requestURI), "/");
                $uriParts = explode("/", $uri);
                $action = array_shift($uriParts);
                try{
                    $this->processSubAction($action, $uriParts);
                    $_SESSION['OVERRIDE_GUI_START_PARAMETERS'] = array(
                        "REBASE"=>"../../",
                        "USER_GUI_ACTION" => $action
                    );
                }catch(Exception $e){
                    $_SESSION['OVERRIDE_GUI_START_PARAMETERS'] = array(
                        "ALERT" => $e->getMessage()
                    );
                }
                AJXP_Controller::findActionAndApply("get_boot_gui", array(), array());
                unset($_SESSION['OVERRIDE_GUI_START_PARAMETERS']);

                break;
            case "reset-password-ask":

                // This is a reset password request, generate a token and store it.
                // Find user by id
                if (AuthService::userExists($httpVars["email"])) {
                    // Send email
                    $userObject = ConfService::getConfStorageImpl()->createUserObject($httpVars["email"]);
                    $email = $userObject->personalRole->filterParameterValue("core.conf", "email", AJXP_REPO_SCOPE_ALL, "");
                    if (!empty($email)) {
                        $uuid = AJXP_Utils::generateRandomString(48);
                        ConfService::getConfStorageImpl()->saveTemporaryKey("password-reset", $uuid, AJXP_Utils::decodeSecureMagic($httpVars["email"]), array());
                        $mailer = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("mailer");
                        if ($mailer !== false) {
                            $mess = ConfService::getMessages();
                            $link = AJXP_Utils::detectServerURL()."/user/reset-password/".$uuid;
                            $mailer->sendMail(array($email), $mess["gui.user.1"], $mess["gui.user.7"]."<a href=\"$link\">$link</a>");
                        } else {
                            echo 'ERROR: There is no mailer configured, please contact your administrator';
                        }
                    }

                }
                // Prune existing expired tokens
                ConfService::getConfStorageImpl()->pruneTemporaryKeys("password-reset", 20);
                echo "SUCCESS";

                break;
            case "reset-password":

                ConfService::getConfStorageImpl()->pruneTemporaryKeys("password-reset", 20);
                // This is a reset password
                if (isSet($httpVars["key"]) && isSet($httpVars["user_id"])) {
                    $key = ConfService::getConfStorageImpl()->loadTemporaryKey("password-reset", $httpVars["key"]);
                    ConfService::getConfStorageImpl()->deleteTemporaryKey("password-reset", $httpVars["key"]);
                    $uId  = $httpVars["user_id"];
                    if(AuthService::ignoreUserCase()){
                        $uId = strtolower($uId);
                    }
                    if ($key != null && strtolower($key["user_id"]) == $uId && AuthService::userExists($uId)) {
                        AuthService::updatePassword($key["user_id"], $httpVars["new_pass"]);
                    }else{
                        echo 'PASS_ERROR';
                        break;
                    }
                }
                AuthService::disconnect();
                echo 'SUCCESS';

                break;
            default:
                break;
        }
    }

    protected function processSubAction($actionName, $args)
    {
        switch ($actionName) {
            case "reset-password-ask":
                break;
            case "reset-password":
                if (count($args)) {
                    $token = $args[0];
                    $key = ConfService::getConfStorageImpl()->loadTemporaryKey("password-reset", $token);
                    if ($key == null || $key["user_id"] === false) {
                        throw new Exception("Invalid password reset key! Did you make sure to copy the correct link?");
                    }
                }
                break;
            default:

                break;
        }
    }

}
