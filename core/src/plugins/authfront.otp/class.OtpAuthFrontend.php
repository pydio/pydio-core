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
defined('AJXP_EXEC') or die('Access not allowed');

class OtpAuthFrontend extends AbstractAuthFrontend
{

    private $enable_Create_User;
    private $modifyLoginScreen;
    private $userFilePath;
    private $yubicoSecretKey;
    private $yubicoClientId;
    private $google;
    private $googleLast;
    private $yubikey1;
    private $yubikey2;
    private $additionalRole;

    function tryToLogUser($httpVars, $isLast = false)
    {
        require_once 'Auth/Yubico.php';
        $this->loadConfig();

        return false;
    }


    private function loadConfig()
    {
        if (!empty($this->pluginConf["CREATE_USER"])) {
            $this->enable_Create_User = trim($this->pluginConf["CREATE_USER"]);
        }
        if (!empty($this->pluginConf["MODIFY_LOGIN_SCREEN"])) {
            $this->modifyLoginScreen = trim($this->pluginConf["MODIFY_LOGIN_SCREEN"]);
        }
        if (!empty($this->pluginConf["USERS_FILEPATH"])) {
            $this->userFilePath = trim($this->pluginConf["USERS_FILEPATH"]);
        }
        if (!empty($this->pluginConf["YUBICO_SECRET_KEY"])) {
            $this->yubicoSecretKey = trim($this->pluginConf["YUBICO_SECRET_KEY"]);
        }
        if (!empty($this->pluginConf["YUBICO_CLIENT_ID"])) {
            $this->yubicoClientId = trim($this->pluginConf["YUBICO_CLIENT_ID"]);
        }
        if (!empty($this->pluginConf["GOOGLE"])) {
            $this->google = trim($this->pluginConf["GOOGLE"]);
        }
        if (!empty($this->pluginConf["GOOGLE_LAST"])) {
            $this->googleLast = trim($this->pluginConf["GOOGLE_LAST"]);
        }
        if (!empty($this->pluginConf["YUBIKEY1"])) {
            $this->yubikey1 = trim($this->pluginConf["YUBIKEY1"]);
        }
        if (!empty($this->pluginConf["YUBIKEY2"])) {
            $this->yubikey2 = trim($this->pluginConf["YUBIKEY2"]);
        }
        if (!empty($this->pluginConf["ADDITIONAL_ROLE"])) {
            $this->additionalRole = trim($this->pluginConf["ADDITIONAL_ROLE"]);
        }
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