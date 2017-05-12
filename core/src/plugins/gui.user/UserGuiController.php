<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
namespace Pydio\Gui;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Pydio\Core\Services\AuthService;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Services\ApplicationState;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\StringHelper;

use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\PluginFramework\PluginsService;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Gui
 * @class Pydio\Gui\UserGuiController
 * Handle the specific /user access point
 */
class UserGuiController extends Plugin
{

    /**
     * @param ServerRequestInterface $requestInterface
     * @param ResponseInterface $responseInterface
     * @throws Exception
     * @throws \Pydio\Core\Exception\ActionNotFoundException
     * @throws \Pydio\Core\Exception\AuthRequiredException
     * @throws \Pydio\Core\Exception\UserNotFoundException
     */
    public function processUserAccessPoint(ServerRequestInterface &$requestInterface, ResponseInterface &$responseInterface)
    {
        $action = $requestInterface->getAttribute("action");
        $httpVars = $requestInterface->getParsedBody();
        $context = $requestInterface->getAttribute("ctx");

        switch ($action) {
            
            case "user_access_point":
                
                $action = "reset-password";
                $key = InputFilter::sanitize($httpVars["key"], InputFilter::SANITIZE_ALPHANUM);
                try {

                    $keyData = ConfService::getConfStorageImpl()->loadTemporaryKey("password-reset", $key);
                    if ($keyData === null || $keyData["user_id"] === false) {
                        throw new Exception("Invalid password reset key! Did you make sure to copy the correct link?");
                    }

                    $_SESSION['OVERRIDE_GUI_START_PARAMETERS'] = array(
                        "REBASE" => "../../",
                        "USER_GUI_ACTION" => $action,
                        "USER_ACTION_KEY" => $key
                    );
                } catch (Exception $e) {
                    $_SESSION['OVERRIDE_GUI_START_PARAMETERS'] = array(
                        "ALERT" => $e->getMessage()
                    );
                }
                $req = $requestInterface->withAttribute("action", "get_boot_gui");
                $responseInterface = Controller::run($req);
                unset($_SESSION['OVERRIDE_GUI_START_PARAMETERS']);

                break;
            
            case "reset-password-ask":

                // This is a reset password request, generate a token and store it.
                // Find user by id
                if (UsersService::userExists($httpVars["email"])) {
                    // Send email
                    $mailUId = InputFilter::sanitize($httpVars["email"], InputFilter::SANITIZE_EMAILCHARS);
                    $userObject = UsersService::getUserById($mailUId);
                    $email = $userObject->getPersonalRole()->filterParameterValue("core.conf", "email", AJXP_REPO_SCOPE_ALL, "");
                    if (!empty($email)) {
                        $uuid = StringHelper::generateRandomString(48);
                        ConfService::getConfStorageImpl()->saveTemporaryKey("password-reset", $uuid, InputFilter::decodeSecureMagic($httpVars["email"]), array());
                        $mailer = PluginsService::getInstance($context)->getUniqueActivePluginForType("mailer");
                        if ($mailer !== false) {
                            $mess = LocaleService::getMessages();
                            $link = rtrim(ApplicationState::detectServerURL(true), "/") . "/user/reset-password/" . $uuid;
                            $mailer->sendMail($context, array($email), $mess["gui.user.1"], $mess["gui.user.7"] . "<a href=\"$link\">$link</a>");
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
                    $keyString = InputFilter::sanitize($httpVars["key"], InputFilter::SANITIZE_ALPHANUM);
                    $key = ConfService::getConfStorageImpl()->loadTemporaryKey("password-reset", $keyString);
                    ConfService::getConfStorageImpl()->deleteTemporaryKey("password-reset", $keyString);
                    $uId = $httpVars["user_id"];
                    if (UsersService::ignoreUserCase()) {
                        $uId = strtolower($uId);
                    }
                    if ($key != null && strtolower($key["user_id"]) == $uId && UsersService::userExists($uId)) {
                        UsersService::updatePassword($key["user_id"], $httpVars["new_pass"]);
                    } else {
                        echo 'PASS_ERROR';
                        break;
                    }
                    AuthService::disconnect();
                    echo 'SUCCESS';
                }else{
                    AuthService::disconnect();
                    echo 'ERROR';
                }

                break;
            default:
                break;
        }
    }

    /**
     * @param $actionName
     * @param $args
     * @throws Exception
     */
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
