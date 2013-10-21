<?php
/*
* Libravatar plugin for AjaXplorer
*/

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Simple implementation of Libravatar
 * @package AjaXplorer_Plugins
 * @subpackage Avatar
 */
class Libravatar extends AJXP_Plugin
{
    public function receiveAction($action, $httpVars, $filesVars)
    {
        $type = $this->getFilteredOption("LIBRAVATAR_TYPE");
        if ($action == "get_avatar") {
            $url = "";
            // Federated Servers are not supported here without libravatar.org. Should query DNS server first.
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                $url = "https://seccdn.libravatar.org";
            } else {
                $url = "http://cdn.libravatar.org";
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
            $url .= "?s=80";
            $url .= "&d=" . $type;
            print($url);
        }
    }
}
