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
 * Standard auth implementation, stores the data in serialized files
 * @package AjaXplorer_Plugins
 * @subpackage Auth
 */
class serial_otpAuthDriver extends AbstractAuthDriver
{
    public $usersSerFile;
    public $driverName = "serial_otp";
    public $yubico_secret_key;
    public $yubico_client_id;

    public function init($options)
    {
        parent::init($options);
        require_once 'Auth/Yubico.php';
        $this->usersSerFile = AJXP_VarsFilter::filter($this->getOption("USERS_FILEPATH"));
        $this->yubico_secret_key = AJXP_VarsFilter::filter($this->getOption("YUBICO_SECRET_KEY"));
        $this->yubico_client_id = AJXP_VarsFilter::filter($this->getOption("YUBICO_CLIENT_ID"));
    }

    public function performChecks()
    {
        if(!isset($this->options)) return;
        if (isset($this->options["FAST_CHECKS"]) && $this->options["FAST_CHECKS"] === true) {
            return;
        }
        $usersDir = dirname($this->usersSerFile);
        if (!is_dir($usersDir) || !is_writable($usersDir)) {
            throw new Exception("Parent folder for users file is either inexistent or not writeable.");
        }
        if (is_file($this->usersSerFile) && !is_writable($this->usersSerFile)) {
            throw new Exception("Users file exists but is not writeable!");
        }
    }

    protected function _listAllUsers()
    {
        $users = AJXP_Utils::loadSerialFile($this->usersSerFile);
        if (AuthService::ignoreUserCase()) {
            $users = array_combine(array_map("strtolower", array_keys($users)), array_values($users));
        }
        ConfService::getConfStorageImpl()->filterUsersByGroup($users, "/", true);
        return $users;
    }

    public function listUsers($baseGroup = "/")
    {
        $users = AJXP_Utils::loadSerialFile($this->usersSerFile);
        if (AuthService::ignoreUserCase()) {
            $users = array_combine(array_map("strtolower", array_keys($users)), array_values($users));
        }
        ConfService::getConfStorageImpl()->filterUsersByGroup($users, $baseGroup, false);
        return $users;
    }

    public function supportsUsersPagination()
    {
        return true;
    }

    // $baseGroup = "/"
    public function listUsersPaginated($baseGroup, $regexp, $offset = -1 , $limit = -1)
    {
        $users = $this->listUsers($baseGroup);
        $result = array();
        $index = 0;
        foreach ($users as $usr => $pass) {
            if (!empty($regexp) && !preg_match("/$regexp/i", $usr)) {
                continue;
            }
            if ($offset != -1 && $index < $offset) {
                $index ++;
                continue;
            }
            $result[$usr] = $pass;
            $index ++;
            if($limit != -1 && count($result) >= $limit) break;
        }
        return $result;
    }
    public function getUsersCount($baseGroup = "/", $regexp = "")
    {
        return count($this->listUsersPaginated($baseGroup, $regexp));
    }


    public function userExists($login)
    {
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
        $users = $this->_listAllUsers();
        if(!is_array($users) || !array_key_exists($login, $users)) return false;
        return true;
    }

    public function checkPassword($login, $pass, $seed)
    {
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
        $userStoredPass = $this->getUserPass($login);
        if(!$userStoredPass) return false;
        if ($seed != "-1") { // Seed = -1 means that password is not encoded.
            throw new Exception("TRANSMIT_CLEAR_PASS=false (need true)");
        }

        $confStorage = ConfService::getConfStorageImpl();
        $userObject = $confStorage->createUserObject($login);
        $role = $userObject->personalRole;
        if ($role === false) {
            throw new Exception("Cant find role! ");
        }
        $roleData = $role->getDataArray();

        $g = isset($roleData["PARAMETERS"]["AJXP_REPO_SCOPE_ALL"]["auth.serial_otp"]["google"]) ?
            $roleData["PARAMETERS"]["AJXP_REPO_SCOPE_ALL"]["auth.serial_otp"]["google"] : '';
        $g_last = isset($roleData["PARAMETERS"]["AJXP_REPO_SCOPE_ALL"]["auth.serial_otp"]["google_last"]) ?
            $roleData["PARAMETERS"]["AJXP_REPO_SCOPE_ALL"]["auth.serial_otp"]["google_last"] : '';
        $y1 = isset($roleData["PARAMETERS"]["AJXP_REPO_SCOPE_ALL"]["auth.serial_otp"]["yubikey1"]) ?
            $roleData["PARAMETERS"]["AJXP_REPO_SCOPE_ALL"]["auth.serial_otp"]["yubikey1"] : '';
        $y2 = isset($roleData["PARAMETERS"]["AJXP_REPO_SCOPE_ALL"]["auth.serial_otp"]["yubikey2"]) ?
            $roleData["PARAMETERS"]["AJXP_REPO_SCOPE_ALL"]["auth.serial_otp"]["yubikey2"] : '';

        //No OTP token is set
        if ($g == '' and $y1 == '' and $y2 == '') {
            return AJXP_Utils::pbkdf2_validate_password($pass, $userStoredPass); //($userStoredPass == md5($pass));
        }

        //Just the Google Authenticator set
        if ($g != '' and $y1 == '' and $y2 == '') {
            return $this->checkGooglePass($login, $pass, $userStoredPass, $g, $$g_last);
        }

        // YubiKey1 or YubiKey2 set
        if ($g == '' and ($y1 != '' or $y2 != '')) {
            return $this->checkYubiPass($pass, $userStoredPass, $y1, $y2);
        }

        // Both Yubikey and Google Authenticator set

        // If the last character of the password is digit, it is Google Authenticator
        if (ctype_digit(substr($pass, -1))) {
            return $this->checkGooglePass($login, $pass, $userStoredPass, $g, $g_last);
        }

        // it is YubiKey
        return $this->checkYubiPass($pass, $userStoredPass, $y1, $y2);

    }

    public function usersEditable()
    {
        return true;
    }
    public function passwordsEditable()
    {
        return true;
    }

    public function createUser($login, $passwd)
    {
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
        $users = $this->_listAllUsers();
        if(!is_array($users)) $users = array();
        if(array_key_exists($login, $users)) return "exists";
        if ($this->getOption("TRANSMIT_CLEAR_PASS") === true) {
            $users[$login] = AJXP_Utils::pbkdf2_create_hash($passwd);
        } else {
            $users[$login] = $passwd;
        }
        AJXP_Utils::saveSerialFile($this->usersSerFile, $users);
    }

    public function changePassword($login, $newPass)
    {
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
        $users = $this->_listAllUsers();
        if(!is_array($users) || !array_key_exists($login, $users)) return ;
        if ($this->getOption("TRANSMIT_CLEAR_PASS") === true) {
            $users[$login] = AJXP_Utils::pbkdf2_create_hash($newPass);
        } else {
            $users[$login] = $newPass;
        }
        AJXP_Utils::saveSerialFile($this->usersSerFile, $users);
    }

    public function deleteUser($login)
    {
        if(AuthService::ignoreUserCase()) $login = strtolower($login);
        $users = $this->_listAllUsers();
        if (is_array($users) && array_key_exists($login, $users)) {
            unset($users[$login]);
            AJXP_Utils::saveSerialFile($this->usersSerFile, $users);
        }
    }

    public function getUserPass($login)
    {
        if(!$this->userExists($login)) return false;
        $users = $this->_listAllUsers();
        return $users[$login];
    }


    // Google Authenticator

    public function oath_hotp($key, $counter)
    {
        $key = pack("H*", $key);
        $cur_counter = array(0,0,0,0,0,0,0,0);
        for ($i=7;$i>=0;$i--) {
            $cur_counter[$i] = pack ('C*', $counter);
            $counter = $counter >> 8;
        }
        $bin_counter = implode($cur_counter);
        // Pad to 8 chars
        if (strlen ($bin_counter) < 8) {
            $bin_counter = str_repeat (chr(0), 8 - strlen ($bin_counter)) . $bin_counter;
        }

        // HMAC
        $hash = hash_hmac ('sha1', $bin_counter, $key);
        return str_pad($this->oath_truncate($hash), 6, "0", STR_PAD_LEFT);
    }

    public function oath_truncate($hash, $length = 6)
    {
        // Convert to dec
        foreach (str_split($hash,2) as $hex) {
            $hmac_result[]=hexdec($hex);
        }

        // Find offset
        $offset = $hmac_result[19] & 0xf;

        // Algorithm from RFC
        return
            (
             (($hmac_result[$offset+0] & 0x7f) << 24 ) |
             (($hmac_result[$offset+1] & 0xff) << 16 ) |
             (($hmac_result[$offset+2] & 0xff) << 8 ) |
             ($hmac_result[$offset+3] & 0xff)
            ) % pow(10,$length);
    }


    public function base32ToHex($b32)
    {
        $alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";

        $out = "";
        $dous = "";

        for ($i = 0; $i < strlen($b32); $i++) {
            $in = strrpos($alphabet, $b32[$i]);
            $b = str_pad(base_convert($in, 10, 2), 5, "0", STR_PAD_LEFT);
            $out .= $b;
            $dous .= $b.".";
        }

        $ar = str_split($out,20);

        $out2 = "";
        foreach ($ar as $val) {
            $rv = str_pad(base_convert($val, 2, 16), 5, "0", STR_PAD_LEFT);
            $out2 .= $rv;

        }
        return $out2;
    }

    public function checkGooglePass($login, $pass, $userStoredPass, $userToken, $userInvalid)
    {
        // last six character belongs to token code, not the password

        $userToken = $this->base32ToHex($userToken);

        $code = substr($pass, -6);
        $pass = substr($pass, 0, strlen($pass) - 6);

        $now = time();
        $totpSkew = 2; // how many tokens in either side we should check. 2 means +-1 min
        $tokenTimer = 30; // google authenticator support just 30s

        $earliest = $now - ($totpSkew * $tokenTimer);
        $latest = $now + ($totpSkew * $tokenTimer);

        $st = ((int) ($earliest / $tokenTimer));
        $en = ((int) ($latest / $tokenTimer));

        $valid = 0;
        for ($i=$st; ($i<=$en && $valid == 0); $i++) {
            if ($i > $userInvalid) {
                $stest = $this->oath_hotp($userToken, $i);
                if ($code == $stest) {
                    $valid = 1;
                    // save google_last
                    $confStorage = ConfService::getConfStorageImpl();
                    $userObject = $confStorage->createUserObject($login);
                    $role = $userObject->personalRole;
                    if ($role === false) {
                        throw new Exception("Cant find role! ");
                    }
                    $role->setParameterValue("auth.serial_otp", "google_last", $i);
                    AuthService::updateRole($role, $userObject);
                }
            }
        }

        return ( AJXP_Utils::pbkdf2_validate_password($pass, $userStoredPass) && $valid == 1);
    }


    // YubiKey

    public function checkYubiPass($pass, $userStoredPass, $yubikey1, $yubikey2)
    {
        // yubikey generates 44 character, identity is the first 12 character
        $yubi1_identity = substr($yubikey1, 0, 12);
        $yubi2_identity = substr($yubikey2, 0, 12);
        $pass_identity = substr($pass, -44, 12);
        if (($pass_identity != $yubi1_identity) and ($pass_identity != $yubi2_identity)) {
            // YubiKey not listed in account
            return false;
        }

        $yotp = substr($pass, -44);
        $pass = substr($pass, 0, strlen($pass) - 44);

        $yubi = new Auth_Yubico($this->yubico_client_id, $this->yubico_secret_key);
        $auth = $yubi->verify($yotp);

        return ((!PEAR::isError($auth)) &&  AJXP_Utils::pbkdf2_validate_password($pass, $userStoredPass));
    }
}
