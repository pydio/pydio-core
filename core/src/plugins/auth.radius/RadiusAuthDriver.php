<?php
namespace Pydio\Auth\Driver;

use Pydio\Auth\Core\AbstractAuthDriver;
use Pydio\Auth\Core\MemorySafe;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\AuthService;
use Pydio\Log\Core\Logger;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Authenticates user against an RADIUS server
 * @package AjaXplorer_Plugins
 * @subpackage Auth
 */
class RadiusAuthDriver extends AbstractAuthDriver
{
    public $driverName = "radius";
    public $radiusServer;
    public $radiusPort = 1812;
    public $radiusSecret;
    public $radiusAuthType = 'chap';

    /**
     * @param ContextInterface $ctx
     * @param array $options
     */
    public function init(ContextInterface $ctx, $options = [])
    {
        parent::init($ctx, $options);
        $this->radiusServer = $options["RADIUS Server"];
        if ($options["RADIUS Port"])
            $this->radiusPort = $options["RADIUS Port"];
        if ($options["RADIUS Shared Secret"])
            $this->radiusSecret = $options["RADIUS Shared Secret"];
        if ($options["RADIUS Auth Type"])
            $this->radiusAuthType = $options["RADIUS Auth Type"];
    }

    /**
     * @param $options
     * @return string
     */
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
        $this->prepareRequest($res, $options["RADIUS Test-User"], $options["RADIUS Test-Password"]);
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

    /**
     * @param string $baseGroup
     * @param bool $recursive
     * @return array
     */
    public function listUsers($baseGroup = "/", $recursive = true)
    {
        $adminUser = $this->options["AJXP_ADMIN_LOGIN"];
        return array(
            $adminUser => $adminUser
        );
    }

    /**
     * @param $login
     * @return bool
     */
    public function userExists($login)
    {
        return true;
    }


    /**
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     */
    public function logoutCallback(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {
        MemorySafe::clearCredentials();
        AuthService::disconnect();
        session_write_close();

        $x = new \Pydio\Core\Http\Response\SerializableResponseStream();
        $x->addChunk(new \Pydio\Core\Http\Message\LoggingResult(2));
        $responseInterface = $responseInterface->withBody($x);
    }

    /**
     * @param $res
     * @param $login
     * @param $pass
     * @return bool
     */
    public function prepareRequest($res, $login, $pass)
    {
        if (!radius_add_server($res, $this->radiusServer, $this->radiusPort, $this->radiusSecret, 3, 3)) {
            Logger::debug(__CLASS__, __FUNCTION__, "RADIUS: Could not add server (" . radius_strerror($res) . ")");
            return false;
        }
        if (!radius_create_request($res, RADIUS_ACCESS_REQUEST)) {
            Logger::debug(__CLASS__, __FUNCTION__, "RADIUS: Could not create request (" . radius_strerror($res) . ")");
            return false;
        }
        if (!radius_put_string($res, RADIUS_NAS_IDENTIFIER, isset($_SERVER["SERVER_NAME"]) ? $_SERVER["SERVER_NAME"] : 'localhost')) {
            Logger::debug(__CLASS__, __FUNCTION__, "RADIUS: Could not put string for nas_identifier (" . radius_strerror($res) . ")");
            return false;
        }
        if (!radius_put_int($res, RADIUS_SERVICE_TYPE, RADIUS_FRAMED)) {
            Logger::debug(__CLASS__, __FUNCTION__, "RADIUS: Could not put int for service_type (" . radius_strerror($res) . ")");
            return false;
        }
        if (!radius_put_int($res, RADIUS_FRAMED_PROTOCOL, RADIUS_PPP)) {
            Logger::debug(__CLASS__, __FUNCTION__, "RADIUS: Could not put int for framed_protocol (" . radius_strerror($res) . ")");
            return false;
        }
        if (!radius_put_string($res, RADIUS_CALLING_STATION_ID, isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1') == -1) {
            Logger::debug(__CLASS__, __FUNCTION__, "RADIUS: Could not put string for calling_station_id (" . radius_strerror($res) . ")");
            return false;
        }
        if (!radius_put_string($res, RADIUS_USER_NAME, $login)) {
            Logger::debug(__CLASS__, __FUNCTION__, "RADIUS: Could not put string for user name (" . radius_strerror($res) . ")");
            return false;
        }
        if ($this->radiusAuthType == 'chap') {
            Logger::debug(__CLASS__, __FUNCTION__, "RADIUS: Using CHAP.");
            mt_srand(time());
            $chall = mt_rand();
            $chapval = pack('H*', md5(pack('Ca*', 1, $pass . $chall)));
            $pass = pack('C', 1) . $chapval;
            if (!radius_put_attr($res, RADIUS_CHAP_PASSWORD, $pass)) {
                Logger::debug(__CLASS__, __FUNCTION__, "RADIUS: Could not put attribute for chap password (" . radius_strerror($res) . ")");
                return false;
            }
            if (!radius_put_attr($res, RADIUS_CHAP_CHALLENGE, $chall)) {
                Logger::debug(__CLASS__, __FUNCTION__, "RADIUS: Could not put attribute for chap callenge (" . radius_strerror($res) . ")");
                return false;
            }
        } else {
            Logger::debug(__CLASS__, __FUNCTION__, "RADIUS: Using PAP.");
            if (!radius_put_string($res, RADIUS_USER_PASSWORD, $pass)) {
                Logger::debug(__CLASS__, __FUNCTION__, "RADIUS: Could not put string for pap password (" . radius_strerror($res) . ")");
                return false;
            }
        }
        if (!radius_put_int($res, RADIUS_SERVICE_TYPE, RADIUS_FRAMED)) {
            Logger::debug(__CLASS__, __FUNCTION__, "RADIUS: Could not put int for second service type (" . radius_strerror($res) . ")");
            return false;
        }
        if (!radius_put_int($res, RADIUS_FRAMED_PROTOCOL, RADIUS_PPP)) {
            Logger::debug(__CLASS__, __FUNCTION__, "RADIUS: Could not put int for second framed protocol (" . radius_strerror($res) . ")");
            return false;
        }
    }

    /**
     * @param string $login
     * @param string $pass
     * @return bool
     */
    public function checkPassword($login, $pass)
    {
        if (!extension_loaded('radius')) {
            Logger::logAction("RADIUS: php radius extension is missing, please install it.");
            return false;
        }
        $res = radius_auth_open();
        $this->prepareRequest($res, $login, $pass);
        $req = radius_send_request($res);
        if (!$req) {
            Logger::debug(__CLASS__, __FUNCTION__, "RADIUS: Could not send request (" . radius_strerror($res) . ")");
            return false;
        }
        switch ($req) {
            case RADIUS_ACCESS_ACCEPT:
                Logger::debug(__CLASS__, __FUNCTION__, "RADIUS: authentication for user \"" . $login . "\" successful");
                radius_close($res);
                return true;
            case RADIUS_ACCESS_REJECT:
                Logger::logAction("RADIUS: authentication for user \"" . $login . "\" failed");
                break;
            default:
                Logger::debug(__CLASS__, __FUNCTION__, "RADIUS: unknwon return value " . $req);
                break;
        }
        radius_close($res);
        return false;
    }

    /**
     * @return bool
     */
    public function usersEditable()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function passwordsEditable()
    {
        return false;
    }

    /**
     * @param $login
     * @param $passwd
     */
    public function createUser($login, $passwd)
    {
    }

    /**
     * @param $login
     * @param $newPass
     */
    public function changePassword($login, $newPass)
    {
    }

    /**
     * @param $login
     */
    public function deleteUser($login)
    {
    }

    /**
     * @param $login
     * @return string
     */
    public function getUserPass($login)
    {
        return "";
    }
}
