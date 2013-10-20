<?php
/*
* Gravatar plugin for AjaXplorer
*/

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Simple implementation of Gravatar
 * @package AjaXplorer_Plugins
 * @subpackage Avatar
 */
class Gravatar extends AJXP_Plugin
{
    public function receiveAction($action, $httpVars, $filesVars)
    {
        $type = $this->getFilteredOption("GRAVATAR_TYPE");
        if ($action == "get_avatar") {
            $url = "";
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                $url = "https://secure.gravatar.com";
            } else {
                $url = "http://www.gravatar.com";
            }
            $url .= "/avatar/";
            if (isSet($httpVars["userid"])) {
                $userid = $httpVars["userid"];
                if (AuthService::usersEnabled() && AuthService::userExists($userid)) {
                    $confDriver = ConfService::getConfStorageImpl();
                    $user = $confDriver->createUserObject($userid);
                    $userEmail = $user->personalRole->filterParameterValue("core.conf", "email", AJXP_REPO_SCOPE_ALL, "");
                    if (!empty($userEmail)) {
                        $url .= md5(strtolower(trim($userEmail)));
                    }
                }
            }
            $url .= "?s=80&r=g";
            $url .= "&d=" . $type;
            print($url);
        }
    }
}
