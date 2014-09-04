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
 * Store authentication data in an SQL database
 * @package AjaXplorer_Plugins
 * @subpackage Auth
 */
class sql_otpAuthDriver extends AbstractAuthDriver
{
    public $sqlDriver;
    public $driverName = "sql_otp";
	var $yubico_secret_key;
	var $yubico_client_id;

    public function init($options)
    {
        parent::init($options);
        require_once(AJXP_BIN_FOLDER."/dibi.compact.php");
		require_once 'Auth/Yubico.php';
		$this->yubico_secret_key = AJXP_VarsFilter::filter($this->getOption("YUBICO_SECRET_KEY"));
		$this->yubico_client_id = AJXP_VarsFilter::filter($this->getOption("YUBICO_CLIENT_ID"));
        $this->sqlDriver = AJXP_Utils::cleanDibiDriverParameters($options["SQL_DRIVER"]);
        try {
            dibi::connect($this->sqlDriver);
        } catch (DibiException $e) {
            echo get_class($e), ': ', $e->getMessage(), "\n";
            exit(1);
        }
    }

    public function performChecks()
    {
        if(!isSet($this->options)) return;
        $test = AJXP_Utils::cleanDibiDriverParameters($this->options["SQL_DRIVER"]);
        if (!count($test)) {
            throw new Exception("You probably did something wrong! To fix this issue you have to remove the file \"bootsrap.json\" and rename the backup file \"bootstrap.json.bak\" into \"bootsrap.json\" in data/plugins/boot.conf/");
        }
    }

    public function supportsUsersPagination()
    {
        return true;
    }

    // $baseGroup = "/"
    public function listUsersPaginated($baseGroup, $regexp, $offset, $limit)
    {
        if ($regexp != null) {
            $like = self::regexpToLike($regexp);
            $res = dibi::query("SELECT * FROM [ajxp_users] WHERE [login] ".$like." AND [groupPath] LIKE %like~ ORDER BY [login] ASC", $regexp, $baseGroup) ;
        } else if ($offset != -1 || $limit != -1) {
            $res = dibi::query("SELECT * FROM [ajxp_users] WHERE [groupPath] LIKE %like~ ORDER BY [login] ASC %lmt %ofs", $baseGroup, $limit, $offset);
        } else {
            $res = dibi::query("SELECT * FROM [ajxp_users] WHERE [groupPath] LIKE %like~ ORDER BY [login] ASC", $baseGroup);
        }
        $pairs = $res->fetchPairs('login', 'password');
           return $pairs;
    }

    public function getUsersCount($baseGroup = "/", $regexp = "", $filterProperty = null, $filterValue = null)
    {
        // WITH PARENT
        // SELECT * FROM ajxp_users INNER JOIN ajxp_user_rights ON ajxp_user_rights.login=ajxp_users.login WHERE ajxp_users.groupPath LIKE '/%' AND ajxp_user_rights.repo_uuid = 'ajxp.parent_user'
        // WITH SPECIFIC PARENT 'username'
        // SELECT * FROM ajxp_users INNER JOIN ajxp_user_rights ON ajxp_user_rights.login=ajxp_users.login WHERE ajxp_users.groupPath LIKE '/%' AND ajxp_user_rights.repo_uuid = 'ajxp.parent_user' AND  ajxp_user_rights.rights = 'username'
        // WITHOUT PARENT
        // SELECT * FROM ajxp_users WHERE NOT EXISTS (SELECT rid FROM ajxp_user_rights WHERE ajxp_user_rights.login=ajxp_users.login AND ajxp_user_rights.repo_uuid='ajxp.parent_user')
        $select = "SELECT COUNT(*) FROM [ajxp_users]";
        $inner = "INNER JOIN [ajxp_user_rights] ON [ajxp_user_rights].[login]=[ajxp_users].[login]";
        $wheres = array();
        if(!empty($regexp)){
            $wheres[] = "[ajxp_users].[login] ". self::regexpToLike($regexp);
        }
        $wheres[] = "[ajxp_users].[groupPath] LIKE %like~";
        if($filterProperty !== null && $filterValue !== null){
            if($filterProperty == "parent"){
                $filterProperty = "ajxp.parent_user";
            }else if($filterProperty == "admin"){
                $filterProperty = "ajxp.admin";
            }
            if($filterValue == AJXP_FILTER_EMPTY){
                $wheres[] = "NOT EXISTS (SELECT rid FROM [ajxp_user_rights] WHERE [ajxp_user_rights].[login]=[ajxp_users].login AND [ajxp_user_rights].[repo_uuid]='$filterProperty')";
                $userCond = implode(" AND ", $wheres);
                $q = $select." WHERE ".$userCond;
            }else if($filterValue == AJXP_FILTER_NOT_EMPTY){
                $wheres[] = "[ajxp_user_rights].[repo_uuid] = '$filterProperty'";
                $userCond = implode(" AND ", $wheres);
                $q = $select." ".$inner." WHERE ".$userCond;
            }else{
                $wheres[] = "[ajxp_user_rights].[repo_uuid] = '$filterProperty'";
                if(strpos($filterValue, "%")!= false){
                    $wheres[] = "[ajxp_user_rights].[rights] LIKE '$filterValue'";
                }else{
                    $wheres[] = "[ajxp_user_rights].[rights] = '$filterValue'";
                }
                $userCond = implode(" AND ", $wheres);
                $q = $select." ".$inner." WHERE ".$userCond;
            }
        }else{
            $userCond = implode(" AND ", $wheres);
            $q = $select." WHERE ".$userCond;
        }


        if (!empty($regexp)) {
            $res = dibi::query($q, $regexp, $baseGroup);
            //$like = self::regexpToLike($regexp);
            //$res = dibi::query("SELECT COUNT(*) FROM [ajxp_users] WHERE [login] ".$like." AND [groupPath] LIKE %like~", $regexp, $baseGroup) ;
        } else {
            $res = dibi::query($q, $baseGroup);
            //$res = dibi::query("SELECT COUNT(*) FROM [ajxp_users] WHERE [groupPath] LIKE %like~", $baseGroup);
        }
        return $res->fetchSingle();
    }

    private static function regexpToLike(&$regexp)
    {
        $left = "~";
        $right = "~";
        if ($regexp[0]=="^") {
            $regexp = ltrim($regexp, "^");
            $left = "";
        }
        if ($regexp[strlen($regexp)-1] == "$") {
            $regexp = rtrim($regexp, "$");
            $right = "";
        }
        $regexp = stripslashes($regexp);
        if ($left == "" && $right == "") {
            return "= %s";
        }
        return "LIKE %".$left."like".$right;
    }

    public function listUsers($baseGroup="/")
    {
        $pairs = array();
        $res = dibi::query("SELECT * FROM [ajxp_users] WHERE [groupPath] LIKE %like~ ORDER BY [login] ASC", $baseGroup);
        $rows = $res->fetchAll();
        foreach ($rows as $row) {
            $grp = $row["groupPath"];
            if(strlen($grp) > strlen($baseGroup)) continue;
            $pairs[$row["login"]] = $row["password"];
        }
        return $pairs;
    }

    public function userExists($login)
    {
        $res = dibi::query("SELECT COUNT(*) FROM [ajxp_users] WHERE [login]=%s", $login);
        return ($res->fetchSingle() > 0);
    }

    public function checkPassword($login, $pass, $seed)
    {
        $userStoredPass = $this->getUserPass($login);
        if(!$userStoredPass) return false;

		if($seed != "-1"){ // Seed = -1 means that password is not encoded.
			throw new Exception("TRANSMIT_CLEAR_PASS=false (need true)"); 
		}
		$confStorage = ConfService::getConfStorageImpl();
		$userObject = $confStorage->createUserObject($login);
		$role = $userObject->personalRole;
		if($role === false) {
			throw new Exception("Cant find role! ");
		}
		$roleData = $role->getDataArray();
		$g = isset($roleData["PARAMETERS"]["AJXP_REPO_SCOPE_ALL"]["auth.sql_otp"]["google"]) ?
			$roleData["PARAMETERS"]["AJXP_REPO_SCOPE_ALL"]["auth.sql_otp"]["google"] : '';
		$g_last = isset($roleData["PARAMETERS"]["AJXP_REPO_SCOPE_ALL"]["auth.sql_otp"]["google_last"]) ?
			$roleData["PARAMETERS"]["AJXP_REPO_SCOPE_ALL"]["auth.sql_otp"]["google_last"] : '';
		$y1 = isset($roleData["PARAMETERS"]["AJXP_REPO_SCOPE_ALL"]["auth.sql_otp"]["yubikey1"]) ?
			$roleData["PARAMETERS"]["AJXP_REPO_SCOPE_ALL"]["auth.sql_otp"]["yubikey1"] : '';
		$y2 = isset($roleData["PARAMETERS"]["AJXP_REPO_SCOPE_ALL"]["auth.sql_otp"]["yubikey2"]) ?
			$roleData["PARAMETERS"]["AJXP_REPO_SCOPE_ALL"]["auth.sql_otp"]["yubikey2"] : '';
		//No OTP token is set
		if ($g == '' and $y1 == '' and $y2 == ''){
			return (AJXP_Utils::pbkdf2_validate_password($pass, $userStoredPass));
		}
		//Just the Google Authenticator set
		if ($g != '' and $y1 == '' and $y2 == ''){
			return $this->checkGooglePass($login, $pass, $userStoredPass, $g, $$g_last);
		}
		// YubiKey1 or YubiKey2 set  
		if ($g == '' and ($y1 != '' or $y2 != '')){
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
        if($this->userExists($login)) return "exists";
        $userData = array("login" => $login);
        if ($this->getOption("TRANSMIT_CLEAR_PASS") === true) {
            $userData["password"] = AJXP_Utils::pbkdf2_create_hash($passwd); //md5($passwd);
        } else {
            $userData["password"] = $passwd;
        }
        $userData['groupPath'] = '/';
        dibi::query('INSERT INTO [ajxp_users]', $userData);
    }

    public function changePassword($login, $newPass)
    {
        if(!$this->userExists($login)) throw new Exception("User does not exists!");
        $userData = array("login" => $login);
        if ($this->getOption("TRANSMIT_CLEAR_PASS") === true) {
            $userData["password"] = AJXP_Utils::pbkdf2_create_hash($newPass); //md5($newPass);
        } else {
            $userData["password"] = $newPass;
        }
        dibi::query("UPDATE [ajxp_users] SET ", $userData, "WHERE [login]=%s", $login);
    }

    public function deleteUser($login)
    {
        dibi::query("DELETE FROM [ajxp_users] WHERE [login]=%s", $login);
    }

    public function getUserPass($login)
    {
        $res = dibi::query("SELECT [password] FROM [ajxp_users] WHERE [login]=%s", $login);
        $pass = $res->fetchSingle();
        return $pass;
    }

	// Google Authenticator

	function oath_hotp($key, $counter)
	{
		$key = pack("H*", $key);
		$cur_counter = array(0,0,0,0,0,0,0,0);
		for($i=7;$i>=0;$i--)
		{
			$cur_counter[$i] = pack ('C*', $counter);
			$counter = $counter >> 8;
		}
		$bin_counter = implode($cur_counter);
		// Pad to 8 chars
		if (strlen ($bin_counter) < 8)
		{
			$bin_counter = str_repeat (chr(0), 8 - strlen ($bin_counter)) . $bin_counter;
		}

		// HMAC
		$hash = hash_hmac ('sha1', $bin_counter, $key);
		return str_pad($this->oath_truncate($hash), 6, "0", STR_PAD_LEFT);
	}

	function oath_truncate($hash, $length = 6)
	{
		// Convert to dec
		foreach(str_split($hash,2) as $hex)
		{
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


	function base32ToHex($b32) {
		$alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";

		$out = "";
		$dous = "";

		for($i = 0; $i < strlen($b32); $i++) {
			$in = strrpos($alphabet, $b32[$i]);
			$b = str_pad(base_convert($in, 10, 2), 5, "0", STR_PAD_LEFT);
			$out .= $b;
			$dous .= $b.".";
		}

		$ar = str_split($out,20);

		$out2 = "";
		foreach($ar as $val) {
			$rv = str_pad(base_convert($val, 2, 16), 5, "0", STR_PAD_LEFT);
			$out2 .= $rv;

		}
		return $out2;
	}

	function checkGooglePass($login, $pass, $userStoredPass, $userToken, $userInvalid){

		// last six character belongs to token code, not the password

		$userToken = $this->base32ToHex(strtoupper($userToken));

		$code = substr($pass, -6);
		$pass = substr($pass, 0, strlen($pass) - 6);

		$now = time();
		$totpSkew = 2; // how many tokens in either side we should check. 2 means +-1 min
		$tokenTimer = 30; // google authenticator support just 30s

		$earliest = $now - ($totpSkew * $tokenTimer);
		$latest = $now + ($totpSkew * $tokenTimer);

		$st = ((int)($earliest / $tokenTimer));
		$en = ((int)($latest / $tokenTimer));

		$valid = 0;
		for($i=$st; ($i<=$en && $valid == 0); $i++) {
			if ($i > $userInvalid) {
				$stest = $this->oath_hotp($userToken, $i);
				if($code == $stest) {
					$valid = 1;
					// save google_last 
					$confStorage = ConfService::getConfStorageImpl();
					$userObject = $confStorage->createUserObject($login);
					$role = $userObject->personalRole;
					if($role === false) {
						throw new Exception("Cant find role! ");
					}
					$role->setParameterValue("auth.sql_otp", "google_last", $i);
					AuthService::updateRole($role, $userObject);
				}
			}
		}

		return (AJXP_Utils::pbkdf2_validate_password($pass, $userStoredPass) && $valid == 1);
	}


	// YubiKey

	function checkYubiPass($pass, $userStoredPass, $yubikey1, $yubikey2) {
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

		return ((!PEAR::isError($auth)) && AJXP_Utils::pbkdf2_validate_password($pass, $userStoredPass));
	}
}
?>