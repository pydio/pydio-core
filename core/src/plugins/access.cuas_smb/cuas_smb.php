<?php
###################################################################
# cuas_smb.php
# This class implements a SMB stream wrapper based on 'smbclient'
#
# Date: lun oct 22 10:35:35 CEST 2007
#
# Homepage: http://www.phpclasses.org/smb4php
#
# Copyright (c) 2007 Victor M. Varela <vmvarela@gmail.com>
# modified by Mario Wehr for SMB Wrapper lib support
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
###################################################################

define ('SMB4PHP_VERSION', '0.8');



if (!defined('SMB4PHP_SMBTMP')) {
    define ('SMB4PHP_SMBTMP', '/tmp');
}

###################################################################
# SMB - commands that does not need an instance
###################################################################

$GLOBALS['__cuas_smb_cache'] = array ('stat' => array (), 'dir' => array ());

require_once(AJXP_INSTALL_PATH."/plugins/access.cuas_smb/vendor/SMB/autoload.php");

use Icewind\SMB\Server;

/**
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class cuas_smb
{
    protected $server = null;
    protected $share = null;
    protected $nativeAvailable = false;

    protected function getServer($purl){
        
        if(is_null($this->server)){
            $this->nativeAvailable = Server::NativeAvailable();
            if ($this->nativeAvailable) {
                $this->server = new Icewind\SMB\NativeServer($purl['host'], $purl['user'], $purl['pass']);
            }else {
                $this->server = new Server($purl['host'], $purl['user'], $purl['pass']);
            }
            $this->share = $this->server->getShare(trim($purl['share'], '/'));
        }
        return $this->share;
    }

    public function parse_url ($url){
        $pu = cuas_smb::smbparseUrl(trim($url));
        //self::debug("URL: " . print_r($pu,true));
        foreach (array ('domain', 'user', 'pass', 'host', 'port', 'path') as $i) {
            if (! isset($pu[$i])) $pu[$i] = '';
            //self::debug("PUI: " . $pu[$i]);
        }
        if (count ($userdomain = explode (';', urldecode ($pu['user']))) > 1)
            @list ($pu['domain'], $pu['user']) = $userdomain;
        $path = preg_replace (array ('/^\//', '/\/$/'), '', rawurldecode ($pu['path']));
        list ($pu['share'], $pu['path']) = (preg_match ('/^([^\/]+)\/(.*)/', $path, $regs))
          ? array ($regs[1], preg_replace ('/\//', '\\', $regs[2]))
          : array ($path, '');
        $pu['type'] = $pu['path'] ? 'path' : ($pu['share'] ? 'share' : ($pu['host'] ? 'host' : '**error**'));
        if (! ($pu['port'] = intval(@$pu['port']))) $pu['port'] = 139;
       /* $i = 0; $atcount = 0;
        //self::debug("COUNT: " . strlen($pu['host']));
        while ($i < strlen($pu['host'])) {
            if ($pu['host'][$i] == '@') {$atcount++;}
            $i++;
        }
        //self::debug("ATCOUNT: " . $atcount);
        if ($atcount > 0) {
            while ($pu['host'][$i] != '@') {$i--; continue;}
            $pu['pass'] = $pu['pass'] . '@' . substr($pu['host'], 0, $i);
            $pu['host'] = substr($pu['host'], $i + 1);

        }

        */
        //self::debug("PU: " . print_r($pu, true));
        //self::debug("HOST: " . $pu['host']);
        return $pu;
    }

    public static function debug($str, $array = null){
        if(!AJXP_SERVER_DEBUG) return;
        // blur credentials!
        $pos1 = strpos($str, "://");
        if ($pos1 !== false) {
            $pos1 += 3;
            $pos2 = strrpos($str, "@", $pos1) + 1;
            $str = substr($str, 0, $pos1) . "***:***@" . substr($str, $pos2);
        }
        if ($array != null) {
            if(!is_array($array)) $array = array($array);
            foreach ($array as $k=>$v) {
                if (is_string($v) && strpos($v, "://") != false) {
                    $pos1 = strpos($v, "://") + 3;
                    $pos2 = strrpos($v, "@", $pos1) + 1;
                    $array[$k] = substr($v, 0, $pos1) . "***:***@" . substr($v, $pos2);
                }
            }
        }
        AJXP_Logger::debug(__CLASS__,__FUNCTION__,$str, $array);
    }


    public function look($purl){
        $this->getServer($purl);  //invalid request smb://localhost
        $results = $this->server->listShares();
        $return['disk'] =array();
        foreach($results as $result){
            array_push($return['disk'], $result->getName());
        }
        return $return;
    }

    # stats

    public function url_stat ($url, $flags = STREAM_URL_STAT_LINK){

        global $__count;
        if ($s = cuas_smb::getstatcache($url)) {
            //self::debug("Using statcache for $url");
            return $s;
        }
        //self::debug("Getting statcache for $url");
        //self::debug("Hey: " $url['user']);
        list ($stat, $pu) = array (array (), cuas_smb::parse_url ($url));
        switch ($pu['type']) {
            case 'host':
                if ($o = cuas_smb::look ($pu))
                //self::debug($_SESSION["AJXP_SESSION_REMOTE_USER"]);
                   $stat = stat (SMB4PHP_SMBTMP);
                else
                  trigger_error ("url_stat(): list failed for host '{$host}'", E_USER_WARNING);
                break;
            case 'share':
                $id = "smb_".md5($pu["host"].$pu["user"]);
                if ($_SESSION[$id] == 0 || true) {
                    $_SESSION[$id] = 1;
                    //self::debug("OH HEY");
                    //$__count++;
                    //self::debug($__count);
                    if ($o = cuas_smb::look($pu)) {
                        $_SESSION[$id . "disk"] = $o['disk'];
                        self::debug(print_r($_SESSION[$id . "disk"], true));
                        //self::debug(print_r($_ENV, true));
                        $found = FALSE;
                        $lshare = strtolower($pu['share']);  # fix by Eric Leung
                        if (is_array($o) && isSet($o['disk']) && is_array($o['disk'])) {
                            foreach ($o['disk'] as $s) {
                                if ($lshare == strtolower($s)) {
                                    $found = TRUE;
                                    //self::debug("DISK: " . $s);
                                    $stat = stat(SMB4PHP_SMBTMP);
                                    break;
                                }
                            }
                        }
                        if (!$found)
                            //trigger_error ("url_stat(): disk resource '{$share}' not found in '{$host}'", E_USER_WARNING);
                            return null;
                    }
                    break;
                } else {
                    //self::debug($__count);
                    //self::debug("WORKING");
                    $found = FALSE;
                    $lshare = strtolower($pu['share']);  # fix by Eric Leung
                    if (is_array($_SESSION[$id."disk"]) && isSet($_SESSION[$id."disk"]) && is_array($_SESSION[$id."disk"])) {
                        foreach ($_SESSION[$id."disk"] as $s) if ($lshare == strtolower($s)) {
                            $found = TRUE;
                            //self::debug("oh boy");
                            $stat = stat(SMB4PHP_SMBTMP);
                            break;
                        }
                    }
                    if (!$found)
                        //trigger_error ("url_stat(): disk resource '{$share}' not found in '{$host}'", E_USER_WARNING);
                        return null;
                    break;
                }
            case 'path':
                //$o = cuas_smb::execute ('dir "'.$pu['path'].'"', $pu);
                try {
                    $this->getServer($pu);
                    if($this->nativeAvailable ) {
                        $stat = cuas_smb::addstatcache($url, $this->share->getStat($pu['path'] ));
                    }else{
                        $stat = cuas_smb::addstatcache($url, $this->share->stat($pu['path'] ));
                    }
                }catch (\Icewind\SMB\Exception\NotFoundException $ex){
                    return null;
                }catch  (\Icewind\SMB\Exception\Exception $ex){
                    trigger_error ("url_stat(): dir failed for path '{$pu['path']}'", E_USER_WARNING);
                }

                break;

            default:
                trigger_error ('error in URL', E_USER_ERROR);
        }

        return $stat;
    }

    public function addstatcache ($url, $info)
    {
        global $__cuas_smb_cache;

        $url = cuas_smb::cleanUrl($url);
        if ($this->nativeAvailable) {
            // in native mode we get a plain stats array
            return $__cuas_smb_cache['stat'][$url] = $info;
        }else{
            // in smbclient mode we get a FileInfo object
            $is_file = !$info->isDirectory();
            if (stripos(PHP_OS, "win") !== false) {
                $s = ($is_file) ? stat (__FILE__) : stat (dirname(__FILE__));
            } else {
                $s = ($is_file) ? stat ('/etc/passwd') : stat (SMB4PHP_SMBTMP);
            }
            if ($is_file) {
                $s[2] = $s['mode'] = 0666;
                $s[2] = $s['mode'] |= 0100000;
            }
            $s[7] = $s['size'] = $info->getSize();
            $s[8] = $s[9] = $s[10] = $s['atime'] = $s['mtime'] = $s['ctime'] = $info->getMTime();
            return $__smb_cache['stat'][$url] = $s;
        }
    }

    public function getstatcache ($url){
        global $__cuas_smb_cache;

        $url = cuas_smb::cleanUrl($url);
        return isset ($__cuas_smb_cache['stat'][$url]) ? $__cuas_smb_cache['stat'][$url] : FALSE;
    }

    public function clearstatcache ($url=''){
        global $__cuas_smb_cache;

        $url = cuas_smb::cleanUrl($url);
        if ($url == '') $__cuas_smb_cache['stat'] = array (); else unset ($__cuas_smb_cache['stat'][$url]);
    }

    public static function cleanUrl($url)
    {
        $url = str_replace("smblib://", "smblib:/__/__", $url);
        while (strstr($url, "//")!==FALSE) {
            $url = str_replace("//", "/", $url);
        }
        $url = str_replace("smblib:/__/__", "smblib://", $url);
        return $url;
    }


    # commands

    public function unlink ($url){
        $url = cuas_smb::cleanUrl($url);
        $pu = cuas_smb::parse_url($url);
        if ($pu['type'] <> 'path') trigger_error('unlink(): error in URL', E_USER_ERROR);
        cuas_smb::clearstatcache ($url);
        $path = str_replace('\\', '/', $pu['path']);
        return $this->getServer($pu)->del($path);
    }

    public function rename ($url_from, $url_to){
        $url_from = cuas_smb::cleanUrl($url_from);
        $url_to = cuas_smb::cleanUrl($url_to);

        list ($from, $to) = array (cuas_smb::parse_url($url_from), cuas_smb::parse_url($url_to));
        if ($from['host'] <> $to['host'] ||
            $from['share'] <> $to['share'] ||
            $from['user'] <> $to['user'] ||
            $from['pass'] <> $to['pass'] ||
            $from['domain'] <> $to['domain']) {
            trigger_error('rename(): FROM & TO must be in same server-share-user-pass-domain', E_USER_ERROR);
        }
        if ($from['type'] <> 'path' || $to['type'] <> 'path') {
            trigger_error('rename(): error in URL', E_USER_ERROR);
        }
        cuas_smb::clearstatcache ($url_from);
        $res = $this->getServer($to)->rename($from['path'], $to['path']);
        //$res = cuas_smb::execute ('rename "'.$from['path'].'" "'.$to['path'].'"', $to);
        if(empty($res)) return true;
        return false;
    }

    public function mkdir ($url, $mode, $options){
        //self::debug("hmmmmm");
        $url = cuas_smb::cleanUrl($url);
        $pu = cuas_smb::parse_url($url);

        //self::debug("huh");
        if ($pu['type'] <> 'path') trigger_error('mkdir(): error in URL', E_USER_ERROR);
        return $this->getServer($pu)->mkdir($pu['path']);
        //return cuas_smb::execute ('mkdir "'.$pu['path'].'"', $pu);
    }

    public function rmdir ($url){
        $url = cuas_smb::cleanUrl($url);
        $pu = cuas_smb::parse_url($url);
        if ($pu['type'] <> 'path') trigger_error('rmdir(): error in URL', E_USER_ERROR);
        cuas_smb::clearstatcache ($url);
        return $this->getServer($pu)->rmdir($pu['path']);
        //return cuas_smb::execute ('rmdir "'.$pu['path'].'"', $pu);
    }


    public function smbparseUrl ($url){
        $pass = $_SESSION["AJXP_SESSION_REMOTE_PASS"];
        //$pass = $pass["password"];
        $pu['scheme'] = 'smblib';
        $temp = substr($url, 9);
        //echo $temp . "\n";
        $pu['user'] = "";
        if (strstr($temp, ":") !== false) {
            $i = 0;
            while ($temp[$i] != ':') {
                $i++;
            }
            $pu['user'] = substr($temp, 0 , $i);
        }
        //echo $pu['user'] . "\n";

        $temp = substr($temp, $i + 1);
        //self::debug($temp);
        $i = 0;
        $j = 0;
        $k = 1;
        //self::debug("PASS: " . $pass);
        $pu['pass'] = '';
        while ($pass != $pu['pass']) {
            $i = 0;
            $j = 0;
            while ($temp[$i] != '@' || $j <= $k) {
                if($temp[$i] == '@')$j++;
                if($j == $k) break;
                if ($i >= strlen($temp)) {
                    exit("Parse error: bad password");
                }
                $i++;
            }
            $k++;
            $pu['pass'] = substr($temp, 0 , $i);
            //self::debug("PASS: " . $pu['pass']);
            //echo $pu['pass'] . "\n";
            //echo "J: " . $j . " K: " . $k . "\n";


        }
        $temp = substr($temp, $i+1);
        //echo $temp;
        $i = 0;
        while ($temp[$i] != '/') {
            $i++;
        }
        $pu['host'] = substr($temp, 0 , $i);
        $pu['path'] = substr($temp, $i);

        //echo $pu['pass'] . "\n";
        return $pu;
    }
}

###################################################################
# CUAS_SMB_STREAM_WRAPPER - class to be registered for libsmb:// URLs
###################################################################

class cuas_smb_stream_wrapper extends cuas_smb
{
    # variables

    public $stream, $writeStream, $url, $parsed_url = array (), $mode, $tmpfile;
    public $need_flush = FALSE;
    public $dir = array (), $dir_index = -1;

    # directories

    public function dir_opendir ($url, $options)
    {
        $d = $this->getdircache ($url);
        if (is_array($d)) {
            $this->dir = $d;
            $this->dir_index = 0;
            return TRUE;
        }
        $url = cuas_smb::cleanUrl($url);
        $pu = cuas_smb::parse_url ($url);
        switch ($pu['type']) {
            case 'host':
                if ($o = cuas_smb::look ($pu)) {
                   $this->dir = $o['disk'];
                   //$this->dir = 'test';
                   $this->dir_index = 0;
                } else {
                   trigger_error ("dir_opendir(): list failed for host '{$pu['host']}'", E_USER_WARNING);
                }
                break;
            case 'share':
            case 'path':
                $path = str_replace('\\', '/', $pu['path']);
                try{
                    
                    $dirResult = $this->getServer($pu)->dir($path);
                    foreach($dirResult as $item){
                        if($this->nativeAvailable ) {
                            cuas_smb::addstatcache($url . $item->getName(), $this->share->getStat($item->getPath()));
                        }else{
                            cuas_smb::addstatcache($url . $item->getName(), $item);
                        }
                        $o['info'][$item->getName()] = '';
                    }
                }catch (\Icewind\SMB\Exception\NotFoundException $ex){
                    trigger_error ("dir_opendir(): dir failed for path '{$pu['path']}'", E_USER_WARNING);
                }

                if (isset($o['info'])) {
                   $this->dir = array_keys($o['info']);
                   $this->dir_index = 0;
                   $this->adddircache ($url, $this->dir);
                } else {
                    $this->dir = array();
                    $this->dir_index = 0;
                    $this->adddircache($url, $this->dir);
                }

                break;
            default:
                trigger_error ('dir_opendir(): error in URL', E_USER_ERROR);
        }
        return TRUE;
    }
    
    public function dir_readdir () { return ($this->dir_index < count($this->dir)) ? $this->dir[$this->dir_index++] : FALSE; }

    public function dir_rewinddir () { $this->dir_index = 0; }

    public function dir_closedir () { $this->dir = array(); $this->dir_index = -1; return TRUE; }


    # cache

    public function adddircache ($url, $content)
    {
        global $__cuas_smb_cache;
        $url = cuas_smb::cleanUrl($url);
        //self::debug("Adding to dir cache", array("url"=>$url));
        return $__cuas_smb_cache['dir'][$url] = $content;
    }

    public function getdircache ($url)
    {
        global $__cuas_smb_cache;
        $url = cuas_smb::cleanUrl($url);
        //self::debug("Testing dir cache", array("url"=>$url));
        return isset ($__cuas_smb_cache['dir'][$url]) ? $__cuas_smb_cache['dir'][$url] : FALSE;
    }

    public function cleardircache ($url='')
    {
        global $__cuas_smb_cache;
        $url = cuas_smb::cleanUrl($url);
        if ($url == '') $__cuas_smb_cache['dir'] = array (); else unset ($__cuas_smb_cache['dir'][$url]);
    }


    # streams

    public function stream_open ($url, $mode, $options, $opened_path)
    {
        $url = cuas_smb::cleanUrl($url);
        $this->url = $url;
        $this->mode = $mode;
        //$this->defer_stream_read;
        $this->parsed_url = $pu = cuas_smb::parse_url($url);
        if ($pu['type'] <> 'path') trigger_error('stream_open(): error in URL', E_USER_ERROR);
        switch ($mode) {
            case 'r':
            case 'r+':
            case 'rb':
            case 'a':
            case 'ab':
            case 'a+':

                //$this->defer_stream_read = true;
                break;
            case 'w':
            case 'w+':
            case 'wb':
            case 'x':
            case 'x+':
                $this->cleardircache();
                $this->writeStream = $this->getWriteStream();
                $this->need_flush = TRUE;

        }
        //$this->stream = fopen ($this->tmpfile, $mode);
        return TRUE;
    }

    public function stream_close ()
    {
        if (isset($this->stream)) {
            return fclose($this->stream);
        } elseif (isset($this->writeStream)) {
                return fclose($this->writeStream);
        } else {
            // Stream was in fact never opened!
            return true;
        }
    }

    public function stream_read ($count) { return fread($this->getStream(), $count); }

    public function stream_write ($data) {
        $this->need_flush = TRUE;
        return fwrite($this->getWriteStream(), $data);
    }

    public function stream_eof () { return feof($this->getStream()); }

    public function stream_tell () { return ftell($this->getStream()); }

    public function stream_seek ($offset, $whence=null) { return fseek($this->getStream(), $offset, $whence); }

    public function stream_flush ()
    {
        if ($this->mode <> 'r' && $this->need_flush) {
            cuas_smb::clearstatcache ($this->url);
            $this->need_flush = FALSE;
        }
    }

    public function stream_stat () { return cuas_smb::url_stat ($this->url); }

    public function __destruct ()
    {
        if ($this->tmpfile <> '') {
            if ($this->need_flush) $this->stream_flush ();
            unlink ($this->tmpfile);

        }
    }

    private function getStream()
    {
        if (isset($this->stream)) {
            return $this->stream;
        }
        $this->stream = $this->getServer($this->parsed_url)->read($this->parsed_url['path']);
        return $this->stream;
    }

    private function getWriteStream()
    {
        if (isset($this->writeStream)) {
            return $this->writeStream;
        }

        $this->writeStream = $this->getServer($this->parsed_url)->write($this->parsed_url['path']);
        return $this->writeStream;
    }

}

###################################################################
# Register 'smb' protocol !
###################################################################

stream_wrapper_register('smblib', 'cuas_smb_stream_wrapper')
    or die ('Failed to register protocol');
