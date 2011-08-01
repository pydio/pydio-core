<?php
###################################################################
# smb.php
# This class implements a SMB stream wrapper based on 'smbclient'
#
# Date: lun oct 22 10:35:35 CEST 2007
#
# Homepage: http://www.phpclasses.org/smb4php
#
# Copyright (c) 2007 Victor M. Varela <vmvarela@gmail.com>
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

###################################################################
# CONFIGURATION SECTION - Change for your needs
###################################################################

define ('SMB4PHP_SMBCLIENT', 'smbclient');
define ('SMB4PHP_SMBOPTIONS', 'TCP_NODELAY IPTOS_LOWDELAY SO_KEEPALIVE SO_RCVBUF=8192 SO_SNDBUF=8192');
define ('SMB4PHP_AUTHMODE', 'arg'); # set to 'env' to use USER enviroment variable

###################################################################
# SMB - commands that does not need an instance
###################################################################

$GLOBALS['__smb_cache'] = array ('stat' => array (), 'dir' => array ());


class smb {

    function parse_url ($url) {
		$pu = smb::smbparseUrl(trim($url));
        //AJXP_Logger::debug("URL: " . print_r($pu,true));
        foreach (array ('domain', 'user', 'pass', 'host', 'port', 'path') as $i) {
            if (! isset($pu[$i])) $pu[$i] = '';
            //AJXP_Logger::debug("PUI: " . $pu[$i]);
        }
        if (count ($userdomain = explode (';', urldecode ($pu['user']))) > 1)
            @list ($pu['domain'], $pu['user']) = $userdomain;
        $path = preg_replace (array ('/^\//', '/\/$/'), '', urldecode ($pu['path']));
        list ($pu['share'], $pu['path']) = (preg_match ('/^([^\/]+)\/(.*)/', $path, $regs))
          ? array ($regs[1], preg_replace ('/\//', '\\', $regs[2]))
          : array ($path, '');
        $pu['type'] = $pu['path'] ? 'path' : ($pu['share'] ? 'share' : ($pu['host'] ? 'host' : '**error**'));
        if (! ($pu['port'] = intval(@$pu['port']))) $pu['port'] = 139;
       /* $i = 0; $atcount = 0;
        //AJXP_Logger::debug("COUNT: " . strlen($pu['host']));
        while ($i < strlen($pu['host'])) {
			if($pu['host'][$i] == '@'){$atcount++;} 
			$i++;
		}
		//AJXP_Logger::debug("ATCOUNT: " . $atcount);
        if($atcount > 0){
			while($pu['host'][$i] != '@'){$i--; continue;}
			$pu['pass'] = $pu['pass'] . '@' . substr($pu['host'], 0, $i);
			$pu['host'] = substr($pu['host'], $i + 1);
			
		}
		
		*/
		//AJXP_Logger::debug("PU: " . print_r($pu, true));
		//AJXP_Logger::debug("HOST: " . $pu['host']); 
        return $pu;
    }


    function look ($purl) {
        return smb::client ('-L ' . escapeshellarg ($purl['host']), $purl);
    }


    function execute ($command, $purl) {
        return smb::client ('-d 0 '
              . escapeshellarg ('//' . $purl['host'] . '/' . $purl['share'])
              . ' -c ' . escapeshellarg ($command), $purl
        );
    }

    function client ($params, $purl) {

    	//var_dump($params);
    	
        static $regexp = array (
        '^added interface ip=(.*) bcast=(.*) nmask=(.*)$' => 'skip',
        'Anonymous login successful' => 'skip',
        '^Domain=\[(.*)\] OS=\[(.*)\] Server=\[(.*)\]$' => 'skip',
        '^\tSharename[ ]+Type[ ]+Comment$' => 'shares',
        '^\t---------[ ]+----[ ]+-------$' => 'skip',
        '^\tServer   [ ]+Comment$' => 'servers',
        '^\t---------[ ]+-------$' => 'skip',
        '^\tWorkgroup[ ]+Master$' => 'workg',
        '^\t(.*)[ ]+(Disk|IPC)[ ]+IPC.*$' => 'skip',
        '^\tIPC\\\$(.*)[ ]+IPC' => 'skip',
        '^\t(.*)[ ]+(Disk)[ ]+(.*)$' => 'share',
        '^\t(.*)[ ]+(Printer)[ ]+(.*)$' => 'skip',
        '([0-9]+) blocks of size ([0-9]+)\. ([0-9]+) blocks available' => 'skip',
        'Got a positive name query response from ' => 'skip',
        '^(session setup failed): (.*)$' => 'error',
        '^(.*): ERRSRV - ERRbadpw' => 'error',
        '^Error returning browse list: (.*)$' => 'error',
        '^tree connect failed: (.*)$' => 'error',
        '^(Connection to .* failed)$' => 'error',
        '^NT_STATUS_(.*) ' => 'error',
        '^NT_STATUS_(.*)\$' => 'error',
        'ERRDOS - ERRbadpath \((.*).\)' => 'error',
        'cd (.*): (.*)$' => 'error',
        '^cd (.*): NT_STATUS_(.*)' => 'error',
        '^\t(.*)$' => 'srvorwg',
        '^([0-9]+)[ ]+([0-9]+)[ ]+(.*)$' => 'skip',
        '^Job ([0-9]+) cancelled' => 'skip',
        '^[ ]+(.*)[ ]+([0-9]+)[ ]+(Mon|Tue|Wed|Thu|Fri|Sat|Sun)[ ](Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[ ]+([0-9]+)[ ]+([0-9]{2}:[0-9]{2}:[0-9]{2})[ ]([0-9]{4})$' => 'files',
        '^message start: ERRSRV - (ERRmsgoff)' => 'error'
        );

        if (SMB4PHP_AUTHMODE == 'env') {
            putenv("USER={$purl['user']}%{$purl['pass']}");
            $auth = '';
        } else {
			//$purl['pass'] = preg_replace('/@/', '\@', $purl['pass']);
            $auth = ($purl['user'] <> '' ? (' -U ' . escapeshellarg ($purl['user'] . '%' . $purl['pass'])) : '');
            //AJXP_Logger::debug($auth);
        }
        if ($purl['domain'] <> '') {
            $auth .= ' -W ' . escapeshellarg ($purl['domain']);
        }
        $port = ($purl['port'] <> 139 ? ' -p ' . escapeshellarg ($purl['port']) : '');
        $options = '-O ' . escapeshellarg(SMB4PHP_SMBOPTIONS);
        //AJXP_Logger::debug($auth);
        AJXP_Logger::debug("SMBCLIENT", " -N {$options} {$port} {$options} {$params} 2>/dev/null {$auth}");
	//AJXP_Logger::debug("I just ran an smbclient call");
        $output = popen (SMB4PHP_SMBCLIENT." -N {$options} {$port} {$options} {$params} 2>/dev/null {$auth}", 'r'); 
        $info = array ();
        while ($line = fgets ($output, 4096)) {
            list ($tag, $regs, $i) = array ('skip', array (), array ());
            reset ($regexp);
            foreach ($regexp as $r => $t) if (preg_match ('/'.$r.'/', $line, $regs)) {
                $tag = $t;
                break;
            }            
            switch ($tag) {            	
                case 'skip':    continue;
                case 'shares':  $mode = 'shares';     break;
                case 'servers': $mode = 'servers';    break;
                case 'workg':   $mode = 'workgroups'; break;
                case 'share':
                    list($name, $type) = array (
                        trim(substr($line, 1, 15)),
                        trim(strtolower(substr($line, 17, 10)))
                    );
                    $i = ($type <> 'disk' && preg_match('/^(.*) Disk/', $line, $regs))
                        ? array(trim($regs[1]), 'disk')
                        : array($name, 'disk');
                    break;
                case 'srvorwg':
                    list ($name, $master) = array (
                        strtolower(trim(substr($line,1,21))),
                        strtolower(trim(substr($line, 22)))
                    );
                    $i = ($mode == 'servers') ? array ($name, "server") : array ($name, "workgroup", $master);
                    break;
                case 'files':
                    list ($attr, $name) = preg_match ("/^(.*)[ ]+([D|A|H|S|R]+)$/", trim ($regs[1]), $regs2)
                        ? array (trim ($regs2[2]), trim ($regs2[1]))
                        : array ('', trim ($regs[1]));
                    list ($his, $im) = array (
                    explode(':', $regs[6]), 1 + strpos("JanFebMarAprMayJunJulAugSepOctNovDec", $regs[4]) / 3);
                    $i = ($name <> '.' && $name <> '..')
                        ? array (
                            $name,
                            (strpos($attr,'D') === FALSE) ? 'file' : 'folder',
                            'attr' => $attr,
                            'size' => intval($regs[2]),
                            'time' => mktime ($his[0], $his[1], $his[2], $im, $regs[5], $regs[7])
                          )
                        : array();
                    break;
                case 'error': 
                	if(strstr($regs[1], "NO_SUCH_FILE") == 0){
                		return "NOT_FOUND";
                	}
                	trigger_error($regs[1], E_USER_ERROR);
            }
            if ($i) switch ($i[1]) {
                case 'file':
                case 'folder':    $info['info'][$i[0]] = $i;
                case 'disk':
                case 'server':
                case 'workgroup': $info[$i[1]][] = $i[0];
            }
        }
        pclose($output);
        //AJXP_Logger::debug(print_r($info, true));
        return $info;
		//return;
    }


    # stats

    function url_stat ($url, $flags = STREAM_URL_STAT_LINK) {
		global $__count;
        if ($s = smb::getstatcache($url)) { 
        	AJXP_Logger::debug("Using statcache for $url");
        	return $s; 
        }
        AJXP_Logger::debug("Getting statcache for $url");
        //AJXP_Logger::debug("Hey: " $url['user']);
        list ($stat, $pu) = array (array (), smb::parse_url ($url));
        switch ($pu['type']) 
        {
            case 'host':
                if ($o = smb::look ($pu))
                //AJXP_Logger::debug($_SESSION["AJXP_SESSION_REMOTE_USER"]);
                   $stat = stat ("/tmp");
                else
                  trigger_error ("url_stat(): list failed for host '{$host}'", E_USER_WARNING);
                break;
            case 'share':
				if($_SESSION["COUNT"] == 0) {
					$_SESSION["COUNT"] = 1;
					//AJXP_Logger::debug("OH HEY");
					//$__count++;
					//AJXP_Logger::debug($__count);
				if ($o = smb::look ($pu)) {
					$_SESSION["disk"] = $o['disk'];
					AJXP_Logger::debug(print_r($_SESSION["disk"], true));
				 //AJXP_Logger::debug(print_r($_ENV, true));
                   $found = FALSE;
                   $lshare = strtolower ($pu['share']);  # fix by Eric Leung
                   if(is_array($o) && isSet($o['disk']) && is_array($o['disk'])){
	                   foreach ($o['disk'] as $s) if ($lshare == strtolower($s)) {
	                       $found = TRUE;
	                       //AJXP_Logger::debug("DISK: " . $s);
	                       $stat = stat ("/tmp");
	                       break;
	                   }
                   }
                   if (! $found)
                      //trigger_error ("url_stat(): disk resource '{$share}' not found in '{$host}'", E_USER_WARNING);
                      return null;
                 }
                break;
			} else {
				//AJXP_Logger::debug($__count);
				//AJXP_Logger::debug("WORKING");
				$found = FALSE;
                   $lshare = strtolower ($pu['share']);  # fix by Eric Leung
                   if(is_array($_SESSION["disk"]) && isSet($_SESSION["disk"]) && is_array($_SESSION["disk"])){
	                   foreach ($_SESSION["disk"] as $s) if ($lshare == strtolower($s)) {
	                       $found = TRUE;
	                       //AJXP_Logger::debug("oh boy");
	                       $stat = stat ("/tmp");
	                       break;
	                   }
                   }
                   if (! $found)
                      //trigger_error ("url_stat(): disk resource '{$share}' not found in '{$host}'", E_USER_WARNING);
                      return null;
                break;
             }
            case 'path':          	
            	$o = smb::execute ('dir "'.$pu['path'].'"', $pu);
            	//AJXP_Logger::debug(print_r($o, true));
                if ($o != null) {
                	if($o == "NOT_FOUND"){
                		return null;
                	}
                    $p = explode ("\\", $pu['path']);
                    $name = $p[count($p)-1];                    
                    if (isset ($o['info'][$name])) {
                       $stat = smb::addstatcache ($url, $o['info'][$name]);
                    } else {
						$stat = stat("/tmp");
                       //trigger_error ("url_stat(): path '{$pu['path']}' not found", E_USER_WARNING);
                    }
                } else {
			//$stat = stat("/tmp");
                    trigger_error ("url_stat(): dir failed for path '{$pu['path']}'", E_USER_WARNING);
                }
                break;
            default: trigger_error ('error in URL', E_USER_ERROR);
        }
	
        return $stat;
    }

    function addstatcache ($url, $info) {
        global $__smb_cache;
        $url = smb::cleanUrl($url);
        $is_file = (strpos ($info['attr'],'D') === FALSE);
        $s = ($is_file) ? stat ('/etc/passwd') : stat ('/tmp');
        if($is_file){
        	$s[2] = $s['mode'] = 0666;
        	$s[2] = $s['mode'] |= 0100000;
        }
        $s[7] = $s['size'] = $info['size'];
        $s[8] = $s[9] = $s[10] = $s['atime'] = $s['mtime'] = $s['ctime'] = $info['time'];
        return $__smb_cache['stat'][$url] = $s;
    }

    function getstatcache ($url) {
        global $__smb_cache;
        $url = smb::cleanUrl($url);
        return isset ($__smb_cache['stat'][$url]) ? $__smb_cache['stat'][$url] : FALSE;
    }

    function clearstatcache ($url='') {
        global $__smb_cache;
        $url = smb::cleanUrl($url);
        if ($url == '') $__smb_cache['stat'] = array (); else unset ($__smb_cache['stat'][$url]);
    }

    static function cleanUrl($url){
    	$url = str_replace("smb://", "smb:/__/__", $url);
    	while (strstr($url, "//")!==FALSE) {
    		$url = str_replace("//", "/", $url);
    	}
    	$url = str_replace("smb:/__/__", "smb://", $url);
    	return $url;
    }
    

    # commands

    function unlink ($url) {
        $pu = smb::parse_url($url);
        if ($pu['type'] <> 'path') trigger_error('unlink(): error in URL', E_USER_ERROR);
        smb::clearstatcache ($url);
        smb::execute ('del "'.$pu['path'].'"', $pu);
        return true;
    }

    function rename ($url_from, $url_to) {
        list ($from, $to) = array (smb::parse_url($url_from), smb::parse_url($url_to));
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
        smb::clearstatcache ($url_from);
        return smb::execute ('rename "'.$from['path'].'" "'.$to['path'].'"', $to);
    }

    function mkdir ($url, $mode, $options) {
		//AJXP_Logger::debug("hmmmmm");
        $pu = smb::parse_url($url);
        //AJXP_Logger::debug("huh");
        if ($pu['type'] <> 'path') trigger_error('mkdir(): error in URL', E_USER_ERROR);
        return smb::execute ('mkdir "'.$pu['path'].'"', $pu);
    }

    function rmdir ($url) {
        $pu = smb::parse_url($url);
        if ($pu['type'] <> 'path') trigger_error('rmdir(): error in URL', E_USER_ERROR);
        smb::clearstatcache ($url);
        return smb::execute ('rmdir "'.$pu['path'].'"', $pu);
    }
    
    
    function smbparseUrl ($url){
		
		$pass = $_SESSION["AJXP_SESSION_REMOTE_PASS"];
		//$pass = $pass["password"];
		$pu['scheme'] = 'smb';
		$temp = substr($url, 6);
		//echo $temp . "\n";
		$i = 0;
		while($temp[$i] != ':'){
			$i++;
		}
		$pu['user'] = substr($temp, 0 , $i);
		//echo $pu['user'] . "\n";

		$temp = substr($temp, $i + 1);
		//AJXP_Logger::debug($temp);
		$i = 0;
		$j = 0;
		$k = 1;
		//AJXP_Logger::debug("PASS: " . $pass);
		$pu['pass'] = '';
		while($pass != $pu['pass']){
			$i = 0;
			$j = 0;
			while($temp[$i] != '@' || $j <= $k){
				if($temp[$i] == '@')$j++;
				if($j == $k) break;
				if($i >= strlen($temp)) {
					exit("Parse error: bad password");
				}
				$i++;
			}
			$k++;
			$pu['pass'] = substr($temp, 0 , $i);
			//AJXP_Logger::debug("PASS: " . $pu['pass']);
			//echo $pu['pass'] . "\n";
			//echo "J: " . $j . " K: " . $k . "\n";

	
		}
		$temp = substr($temp, $i+1);
		//echo $temp;
		$i = 0;
		while($temp[$i] != '/'){
			$i++;
		}
		$pu['host'] = substr($temp, 0 , $i);
		$pu['path'] = substr($temp, $i);

		//echo $pu['pass'] . "\n";
		return $pu;
	}
}

###################################################################
# SMB_STREAM_WRAPPER - class to be registered for smb:// URLs
###################################################################

class smb_stream_wrapper extends smb {

    # variables

    var $stream, $url, $parsed_url = array (), $mode, $tmpfile;
    var $need_flush = FALSE;
    var $dir = array (), $dir_index = -1;


    # directories

    function dir_opendir ($url, $options) {
    	
    	$d = $this->getdircache ($url);
        if (is_array($d)) {
            $this->dir = $d;
            $this->dir_index = 0;
            return TRUE;
        }
        $pu = smb::parse_url ($url);
        switch ($pu['type']) {
            case 'host':
                if ($o = smb::look ($pu)) {
                   $this->dir = $o['disk'];
                   //$this->dir = 'test';
                   $this->dir_index = 0;
                } else {
                   trigger_error ("dir_opendir(): list failed for host '{$pu['host']}'", E_USER_WARNING);
                }
                break;
            case 'share':
            case 'path':
            	$o = smb::execute ('dir "'.$pu['path'].'\*"', $pu);            	
                if (is_array($o)) {
                	if(isSet($o['info'])){
	                   $this->dir = array_keys($o['info']);	                   
	                   $this->dir_index = 0;
	                   $this->adddircache ($url, $this->dir);
	                   foreach ($o['info'] as $name => $info) {
	                   		AJXP_Logger::debug("Adding to statcache ".$url.'/'.$name);
	                       //smb::addstatcache($url . '/' . urlencode($name), $info);
	                       smb::addstatcache($url .'/'. $name, $info);
	                   }
                	}else{
                		$this->dir = array();
                		$this->dir_index = 0;
                		$this->adddircache($url, $this->dir);
                	}
                } else {                	
                   trigger_error ("dir_opendir(): dir failed for path '{$path}'", E_USER_WARNING);
                }
                break;
            default:
                trigger_error ('dir_opendir(): error in URL', E_USER_ERROR);
        }
        return TRUE;
    }

    function dir_readdir () { return ($this->dir_index < count($this->dir)) ? $this->dir[$this->dir_index++] : FALSE; }

    function dir_rewinddir () { $this->dir_index = 0; }

    function dir_closedir () { $this->dir = array(); $this->dir_index = -1; return TRUE; }


    # cache

    function adddircache ($url, $content) {
        global $__smb_cache;        
        $url = smb::cleanUrl($url);
        AJXP_Logger::debug("Adding to dir cache", array("url"=>$url));
        return $__smb_cache['dir'][$url] = $content;
    }

    function getdircache ($url) {
        global $__smb_cache;
        $url = smb::cleanUrl($url);
        AJXP_Logger::debug("Testing dir cache", array("url"=>$url));
        return isset ($__smb_cache['dir'][$url]) ? $__smb_cache['dir'][$url] : FALSE;
    }

    function cleardircache ($url='') {
        global $__smb_cache;
        $url = smb::cleanUrl($url);
        if ($url == '') $__smb_cache['dir'] = array (); else unset ($__smb_cache['dir'][$url]);
    }


    # streams

    function stream_open ($url, $mode, $options, $opened_path) {
        $this->url = $url;
        $this->mode = $mode;
        $this->defer_stream_read;
        $this->parsed_url = $pu = smb::parse_url($url);
        if ($pu['type'] <> 'path') trigger_error('stream_open(): error in URL', E_USER_ERROR);
        switch ($mode) {
            case 'r':
            case 'r+':
            case 'rb':
            case 'a':
            case 'a+':  
            	// REFERENCE STREAM BUT DO NOT OPEN IT UNTIL READING IS REALLY NECESSARY!
            	/*
            	$this->tmpfile = tempnam('/tmp', 'smb.down.');
                smb::execute ('get "'.$pu['path'].'" "'.$this->tmpfile.'"', $pu);
                $this->stream = fopen ($this->tmpfile, $mode);
                */
            	$this->defer_stream_read = true;
                break;
            case 'w':
            case 'w+':
            case 'wb':
            case 'x':
            case 'x+':  
            	$this->cleardircache();
                $this->tmpfile = tempnam('/tmp', 'smb.up.');
                $this->stream = fopen ($this->tmpfile, $mode);
                $this->need_flush = TRUE;

        }
        //$this->stream = fopen ($this->tmpfile, $mode);
        return TRUE;
    }

    function stream_close () { 
    	if(isSet($this->stream)){
	    	return fclose($this->stream); 
    	}else{
    		// Stream was in fact never opened!
    		return true;
    	}
    }

    function stream_read ($count) { return fread($this->getStream(), $count); }

    function stream_write ($data) { 
    	$this->need_flush = TRUE; 
    	return fwrite($this->getStream(), $data); 
    }

    function stream_eof () { return feof($this->getStream()); }

    function stream_tell () { return ftell($this->getStream()); }

    function stream_seek ($offset, $whence=null) { return fseek($this->getStream(), $offset, $whence); }

    function stream_flush () {
        if ($this->mode <> 'r' && $this->need_flush) {
            smb::clearstatcache ($this->url);
            smb::execute ('put "'.$this->tmpfile.'" "'.$this->parsed_url['path'].'"', $this->parsed_url);
            $this->need_flush = FALSE;
        }
    }

    function stream_stat () { return smb::url_stat ($this->url); }

    function __destruct () {
        if ($this->tmpfile <> '') {
            if ($this->need_flush) $this->stream_flush ();
            unlink ($this->tmpfile);

        }
    }
    
    private function getStream(){
    	if(isSet($this->stream)){
    		return $this->stream;
    	}
    	if(isSet($this->defer_stream_read)){
    		$this->tmpfile = tempnam('/tmp', 'smb.down');
    		AJXP_Logger::debug("Creating real tmp file now");
    		smb::execute ('get "'.$this->parsed_url['path'].'" "'.$this->tmpfile.'"', $this->parsed_url);
    		$this->stream = fopen($this->tmpfile, $this->mode);
    	}
    	return $this->stream;
    }

}

###################################################################
# Register 'smb' protocol !
###################################################################

stream_wrapper_register('smb', 'smb_stream_wrapper')
    or die ('Failed to register protocol');
    
?>
