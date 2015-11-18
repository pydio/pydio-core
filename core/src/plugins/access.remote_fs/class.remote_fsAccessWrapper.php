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
 * AJXP_Plugin to access a remote server that implements Pydio API
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class remote_fsAccessWrapper implements AjxpWrapper
{
    // Instance vars $this->
    protected $host;
    protected $port;
    protected $secure;
    protected $path;
    protected $user;
    protected $password;
    protected $repositoryId;
    protected $fp;

    protected $crtMode;
    protected $crtParameters;
    protected $postFileData;

    public static function getRealFSReference($path, $persistent = FALSE)
    {
        $tmpFile = AJXP_Utils::getAjxpTmpDir()."/".md5(time());
        $tmpHandle = fopen($tmpFile, "wb");
        self::copyFileInStream($path, $tmpHandle);
        fclose($tmpHandle);
        if (!$persistent) {
            register_shutdown_function(array("AJXP_Utils", "silentUnlink"), $tmpFile);
        }
        return $tmpFile;
    }

    public static function isRemote()
    {
        return true;
    }

    public static function copyFileInStream($path, $stream)
    {
        $fake = new remote_fsAccessWrapper();
        $parts = $fake->parseUrl($path);
        $client = $fake->createHttpClient();
        $client->writeContentToStream($stream);
        $client->get($fake->path."?get_action=get_content&file=".AJXP_Utils::securePath($parts["path"]));
        $client->clearContentDestStream();
    }

    public function stream_open($url, $mode, $options, &$context)
    {
        if ($mode == "w" || $mode == "rw") {
            $this->crtMode = 'write';
            $parts = $this->parseUrl($url);
            $this->crtParameters = array(
                "get_action"=>"put_content",
                "encode"	=> "base64",
                "file" => urldecode(AJXP_Utils::securePath($parts["path"]))
            );
            $tmpFileBuffer = realpath(AJXP_Utils::getAjxpTmpDir()).md5(time());
            $this->postFileData = $tmpFileBuffer;
            $this->fp = fopen($tmpFileBuffer, "w");
        } else {
            $this->crtMode = 'read';
            $this->fp = tmpfile();
            $this->copyFileInStream($url, $this->fp);
            rewind($this->fp);
        }
        return ($this->fp !== false);
    }

    public function stream_stat()
    {
        return fstat($this->fp);
    }

    public function stream_seek($offset , $whence = SEEK_SET)
    {
        fseek($this->fp, $offset, SEEK_SET);
    }

    public function stream_tell()
    {
        return ftell($this->fp);
    }

    public function stream_read($count)
    {
        return fread($this->fp, $count);
    }

    public function stream_write($data)
    {
        fwrite($this->fp, $data, strlen($data));
        return strlen($data);
    }

    public function stream_eof()
    {
        return feof($this->fp);
    }

    public function stream_close()
    {
        if (isSet($this->fp) && $this->fp!=-1 && $this->fp!==false) {
            fclose($this->fp);
        }
    }

    public function stream_flush()
    {
        if (isSet($this->fp) && $this->fp!=-1 && $this->fp!==false) {
            if ($this->crtMode == 'write') {
                rewind($this->fp);
                AJXP_Logger::debug(__CLASS__,__FUNCTION__,"Http_fput", array("target"=>$this->path));
                $link = $this->createHttpClient();
                $this->crtParameters["content"] = base64_encode(implode("", file($this->postFileData)));
                $link->post($this->path, $this->crtParameters);
            } else {
                fflush($this->fp);
            }
        }
    }

    public function url_stat($path, $flags)
    {
        $parts = $this->parseUrl($path);
        $client = $this->createHttpClient();
        $client->get($this->path."?get_action=stat&file=".AJXP_Utils::securePath($parts["path"]));
        $json = $client->getContent();
        $decode = json_decode($json, true);
        return $decode;
    }


    // NOT IMPLEMENTED
    public static function changeMode($path, $chmodValue)
    {
    }

    public function unlink($url)
    {
    }

    public function rmdir($url, $options)
    {
    }

    public function mkdir($url, $mode, $options)
    {
    }

    public function rename($from, $to)
    {
    }


    public function dir_opendir ($url , $options )
    {
    }

    public function dir_closedir  ()
    {
    }

    public function dir_readdir ()
    {
    }

    public function dir_rewinddir ()
    {
    }



    protected function parseUrl($url)
    {
        // URL MAY BE ajxp.ftp://username:password@host/path
        $urlParts = parse_url($url);
        $this->repositoryId = $urlParts["host"];
        $repository = ConfService::getRepositoryById($this->repositoryId);
        // Get USER/PASS
        // 1. Try from URL
        if (isSet($urlParts["user"]) && isset($urlParts["pass"])) {
            $this->user = $urlParts["user"];
            $this->password = $urlParts["pass"];
        }
        // 2. Try from user wallet
        if (!isSet($this->user) || $this->user=="") {
            $loggedUser = AuthService::getLoggedUser();
            if ($loggedUser != null) {
                $wallet = $loggedUser->getPref("AJXP_WALLET");
                if (is_array($wallet) && isSet($wallet[$this->repositoryId]["AUTH_USER"])) {
                    $this->user = $wallet[$this->repositoryId]["AUTH_USER"];
                    $this->password = AJXP_Utils::decypherStandardFormPassword($loggedUser->getId(), $wallet[$this->repositoryId]["AUTH_PASS"]);
                }
            }
        }
        // 3. Try from repository config
        if (!isSet($this->user) || $this->user=="") {
            $this->user = $repository->getOption("AUTH_USER");
            $this->password = $repository->getOption("AUTH_PASS");
        }
        if (!isSet($this->user) || $this->user=="") {
            throw new AJXP_Exception("Cannot find user/pass for Http access!");
        }

        $this->host = $repository->getOption("HOST");
        $this->path = $repository->getOption("URI");
        $this->auth_path = $repository->getOption("AUTH_URI");
        $this->use_auth = $repository->getOption("USE_AUTH");

        $urlParts["path"] = urlencode($urlParts["path"]);

        return $urlParts;
    }

    /**
     * Initialize and return the HttpClient
     *
     * @return HttpClient
     */
    protected function createHttpClient()
    {
        require_once(AJXP_BIN_FOLDER."/class.HttpClient.php");
        $httpClient = new HttpClient($this->host);
        $httpClient->cookie_host = $this->host;
        $httpClient->timeout = 50;
        AJXP_Logger::debug(__CLASS__,__FUNCTION__,"Creating Http client", array());
        //$httpClient->setDebug(true);
        if (!$this->use_auth) {
            return $httpClient;
        }

        $uri = "";
        if ($this->auth_path != "") {
            $httpClient->setAuthorization($this->user, $this->password);
            $uri = $this->auth_path;
        }
        if (!isSet($_SESSION["AJXP_REMOTE_SESSION"])) {
            if ($uri == "") {
                // Retrieve a seed!
                $httpClient->get($this->path."?get_action=get_seed");
                $seed = $httpClient->getContent();
                $user = $this->user;
                $pass = $this->password;
                $pass = md5(md5($pass).$seed);
                $uri = $this->path."?get_action=login&userid=".$user."&password=".$pass."&login_seed=$seed";
            }
            $httpClient->setHeadersOnly(true);
            $httpClient->get($uri);
            $httpClient->setHeadersOnly(false);
            $cookies = $httpClient->getCookies();
            if (isSet($cookies["AjaXplorer"])) {
                $_SESSION["AJXP_REMOTE_SESSION"] = $cookies["AjaXplorer"];
                $remoteSessionId = $cookies["AjaXplorer"];
            }
        } else {
            $remoteSessionId = $_SESSION["AJXP_REMOTE_SESSION"];
            $httpClient->setCookies(array("AjaXplorer"=>$remoteSessionId));
        }
        AJXP_Logger::debug(__CLASS__,__FUNCTION__,"Http Client created", array());
        return $httpClient;
    }


}
