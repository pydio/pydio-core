<?php
defined('AJXP_EXEC') or die('Access not allowed');
/**
 * Authenticates user against an RADIUS server
 * @package AjaXplorer_Plugins
 * @subpackage Auth
 */
class radiusAuthDriver extends AbstractAuthDriver
{
    public $driverName = "radius";
    public $radiusServer;
    public $radiusPort = 1812;
    public $radiusSecret;
    public $radiusAuthType = 'chap';
    public function init($options)
    {
        parent::init($options);
        $this->radiusServer = $options["RADIUS Server"];
        if ($options["RADIUS Port"])
            $this->radiusPort = $options["RADIUS Port"];
        if ($options["RADIUS Shared Secret"])
            $this->radiusSecret = $options["RADIUS Shared Secret"];
        if ($options["RADIUS Auth Type"])
            $this->radiusAuthType = $options["RADIUS Auth Type"];
    }
    public function testRADIUSConnection($options)
    {
        $this->radiusServer = $options["RADIUS Server"];
        if ($options["RADIUS Port"])
            $this->radiusPort = $options["RADIUS Port"];
        if ($options["RADIUS Shared Secret"])
            $this->radiusSecret = $options["RADIUS Shared Secret"];
        if ($options["RADIUS Auth Type"])
            $this->radiusAuthType = $options["RADIUS Auth Type"];
        if (!extension_loaded('radius')) {
            return "ERROR: PHP radius extension is missing. Do NOT enable RADIUS authentication unless you installed the extension!";
        }
        $res = radius_auth_open();
        $this->prepareRequest($res, $options["RADIUS Test-User"], $options["RADIUS Test-Password"], -1);
        $req = radius_send_request($res);
        if (!$req) {
            return "ERROR: Could not send RADIUS request to server";
        }
        switch ($req) {
            case RADIUS_ACCESS_ACCEPT:
                radius_close($res);
                return "SUCCESS: Sucessfully authenticated.";
            case RADIUS_ACCESS_REJECT:
                radius_close($res);
                return "ERROR: Could connect but user was rejected (Verify correct test user/password).";
            default:
                radius_close($res);
                return "ERROR: Server responded with an unknown code.";
        }
    }
    public function listUsers()
    {
        $adminUser = $this->options["AJXP_ADMIN_LOGIN"];
        return array(
            $adminUser => $adminUser
        );
    }
    public function userExists($login)
    {
        return true;
    }
    public function logoutCallback($actionName, $httpVars, $fileVars)
    {
        AJXP_Safe::clearCredentials();
        $adminUser = $this->options["AJXP_ADMIN_LOGIN"];
        AuthService::disconnect();
        session_write_close();
        AJXP_XMLWriter::header();
        AJXP_XMLWriter::loggingResult(2);
        AJXP_XMLWriter::close();
    }
    public function prepareRequest($res, $login, $pass, $seed)
    {
        if (!radius_add_server($res, $this->radiusServer, $this->radiusPort, $this->radiusSecret, 3, 3)) {
            AJXP_Logger::debug(__CLASS__,__FUNCTION__,"RADIUS: Could not add server (" . radius_strerror($res) . ")");
            return false;
        }
        if (!radius_create_request($res, RADIUS_ACCESS_REQUEST)) {
            AJXP_Logger::debug(__CLASS__,__FUNCTION__,"RADIUS: Could not create request (" . radius_strerror($res) . ")");
            return false;
        }
        if (!radius_put_string($res, RADIUS_NAS_IDENTIFIER, isset($_SERVER["SERVER_NAME"]) ? $_SERVER["SERVER_NAME"] : 'localhost')) {
            AJXP_Logger::debug(__CLASS__,__FUNCTION__,"RADIUS: Could not put string for nas_identifier (" . radius_strerror($res) . ")");
            return false;
        }
        if (!radius_put_int($res, RADIUS_SERVICE_TYPE, RADIUS_FRAMED)) {
            AJXP_Logger::debug(__CLASS__,__FUNCTION__,"RADIUS: Could not put int for service_type (" . radius_strerror($res) . ")");
            return false;
        }
        if (!radius_put_int($res, RADIUS_FRAMED_PROTOCOL, RADIUS_PPP)) {
            AJXP_Logger::debug(__CLASS__,__FUNCTION__,"RADIUS: Could not put int for framed_protocol (" . radius_strerror($res) . ")");
            return false;
        }
        if (!radius_put_string($res, RADIUS_CALLING_STATION_ID, isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1') == -1) {
            AJXP_Logger::debug(__CLASS__,__FUNCTION__,"RADIUS: Could not put string for calling_station_id (" . radius_strerror($res) . ")");
            return false;
        }
        if (!radius_put_string($res, RADIUS_USER_NAME, $login)) {
            AJXP_Logger::debug(__CLASS__,__FUNCTION__,"RADIUS: Could not put string for user name (" . radius_strerror($res) . ")");
            return false;
        }
        if ($this->radiusAuthType == 'chap') {
            AJXP_Logger::debug(__CLASS__,__FUNCTION__,"RADIUS: Using CHAP.");
            mt_srand(time());
            $chall   = mt_rand();
            $chapval = pack('H*', md5(pack('Ca*', 1, $pass . $chall)));
            $pass    = pack('C', 1) . $chapval;
            if (!radius_put_attr($res, RADIUS_CHAP_PASSWORD, $pass)) {
                AJXP_Logger::debug(__CLASS__,__FUNCTION__,"RADIUS: Could not put attribute for chap password (" . radius_strerror($res) . ")");
                return false;
            }
            if (!radius_put_attr($res, RADIUS_CHAP_CHALLENGE, $chall)) {
                AJXP_Logger::debug(__CLASS__,__FUNCTION__,"RADIUS: Could not put attribute for chap callenge (" . radius_strerror($res) . ")");
                return false;
            }
        } else {
            AJXP_Logger::debug(__CLASS__,__FUNCTION__,"RADIUS: Using PAP.");
            if (!radius_put_string($res, RADIUS_USER_PASSWORD, $pass)) {
                AJXP_Logger::debug(__CLASS__,__FUNCTION__,"RADIUS: Could not put string for pap password (" . radius_strerror($res) . ")");
                return false;
            }
        }
        if (!radius_put_int($res, RADIUS_SERVICE_TYPE, RADIUS_FRAMED)) {
            AJXP_Logger::debug(__CLASS__,__FUNCTION__,"RADIUS: Could not put int for second service type (" . radius_strerror($res) . ")");
            return false;
        }
        if (!radius_put_int($res, RADIUS_FRAMED_PROTOCOL, RADIUS_PPP)) {
            AJXP_Logger::debug(__CLASS__,__FUNCTION__,"RADIUS: Could not put int for second framed protocol (" . radius_strerror($res) . ")");
            return false;
        }
    }
    public function checkPassword($login, $pass, $seed)
    {
        if (!extension_loaded('radius')) {
            AJXP_Logger::logAction("RADIUS: php radius extension is missing, please install it.");
            return false;
        }
        $res = radius_auth_open();
        $this->prepareRequest($res, $login, $pass, $seed);
        $req = radius_send_request($res);
        if (!$req) {
            AJXP_Logger::debug(__CLASS__,__FUNCTION__,"RADIUS: Could not send request (" . radius_strerror($res) . ")");
            return false;
        }
        switch ($req) {
            case RADIUS_ACCESS_ACCEPT:
                AJXP_Logger::debug(__CLASS__,__FUNCTION__,"RADIUS: authentication for user \"" . $login . "\" successful");
                radius_close($res);
                return true;
            case RADIUS_ACCESS_REJECT:
                AJXP_Logger::logAction("RADIUS: authentication for user \"" . $login . "\" failed");
                break;
            default:
                AJXP_Logger::debug(__CLASS__,__FUNCTION__,"RADIUS: unknwon return value " . $req);
                break;
        }
        radius_close($res);
        return false;
    }
    public function usersEditable()
    {
        return false;
    }
    public function passwordsEditable()
    {
        return false;
    }
    public function createUser($login, $passwd)
    {
    }
    public function changePassword($login, $newPass)
    {
    }
    public function deleteUser($login)
    {
    }
    public function getUserPass($login)
    {
        return "";
    }
}
