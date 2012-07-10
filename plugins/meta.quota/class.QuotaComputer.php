<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */

defined('AJXP_EXEC') or die('Access not allowed');

class QuotaComputer extends AJXP_Plugin
{
    /**
     * @var AbstractAccessDriver
     */
    protected $accessDriver;
    protected $currentQuota;
    protected $computeLocal = true;
    static $loadedQuota;

    public function initMeta($accessDriver){
        $this->accessDriver = $accessDriver;
    }

    protected function getWorkingPath(){
        $repo = ConfService::getRepository();
        $path = $repo->getOption("PATH");
        return $path;
    }

    /**
     * @param AJXP_Node $node
     * @param int $newSize
     * @return mixed
     * @throws Exception
     */
    public function precheckQuotaUsage($node, $newSize = 0){
        // POSITIVE DELTA ?
        if($newSize == 0) {
            return null;
        }
        $delta = $newSize;
        $quota = $this->getAuthorized();
        $path = $this->getWorkingPath();
        $q = $this->getUsage($path);
        AJXP_Logger::debug("QUOTA : Previous usage was $q");
        if($q === false){
            $q = $this->computeDirSpace($path);
        }
        if($q + $delta >= $quota){
            $mess = ConfService::getMessages();
            throw new Exception($mess["meta.quota.3"]." (".AJXP_Utils::roundSize($quota) .")!");
        }
    }

    public function getCurrentQuota($action, $httpVars, $fileVars){
        $u = $this->getUsage($this->getWorkingPath());
        HTMLWriter::charsetHeader("application/json");
        print json_encode(array('USAGE' => $u, 'TOTAL' => $this->getAuthorized()));
        return;
    }

    public function recomputeQuotaUsage($oldNode = null, $newNode = null, $copy = false){
        $mtime = microtime(true);
        $path = $this->getWorkingPath();
        $q = $this->computeDirSpace($path);
        $this->storeUsage($path, $q);
        AJXP_Logger::debug("QUOTA : New usage is $q - it took ".(microtime(true) - $mtime)."ms");
    }

    private function storeUsage($dir, $quota){
        $data = $this->getUserData();
        $repo = ConfService::getRepository()->getId();
        if(!isset($data["REPO_USAGES"])) $data["REPO_USAGES"] = array();
        $data["REPO_USAGES"][$repo] = $quota;
        $this->saveUserData($data);
    }

    private function getAuthorized(){
        if(self::$loadedQuota != null) return self::$loadedQuota;
        $q = $this->options["DEFAULT_QUOTA"];
        if(!empty($this->options["CUSTOM_DATA_FIELD"])){
            $cdField = $this->options["CUSTOM_DATA_FIELD"];
            $t = AuthService::getLoggedUser()->getPref("CUSTOM_PARAMS");
            if(is_array($t) && array_key_exists($cdField, $t)){
                $q = $t[$cdField];
            }
        }
        self::$loadedQuota = AJXP_Utils::convertBytes($q);
        return self::$loadedQuota;
    }

    /**
     * @param String $dir
     * @return bool|int
     */
    private function getUsage($dir){
        $data = $this->getUserData();
        $repo = ConfService::getRepository()->getId();
        if(!isSet($data["REPO_USAGES"][$repo])) {
            $quota = $this->computeDirSpace($dir);
            if(!isset($data["REPO_USAGES"])) $data["REPO_USAGES"] = array();
            $data["REPO_USAGES"][$repo] = $quota;
            $this->saveUserData($data);
        }

        if($this->pluginConf["USAGE_SCOPE"] == "local"){
            return intval($data["REPO_USAGES"][$repo]);
        }else{
            return array_sum(array_map("intval", $data["REPO_USAGES"]));
        }

    }

    private function getUserData(){
        $logged = AuthService::getLoggedUser();
        $data = $logged->getPref("meta.quota");
        if(is_array($data)) return $data;
        else return array();
    }

    private function saveUserData($data){
        $logged = AuthService::getLoggedUser();
        $logged->setPref("meta.quota", $data);
        $logged->save("user");
        AuthService::updateUser($logged);
    }

    private function computeDirSpace($dir){

        AJXP_Logger::debug("Computing dir space for : ".$dir);
        $s = -1;
        if (PHP_OS == "WIN32" || PHP_OS == "WINNT" || PHP_OS == "Windows"){

            $obj = new COM ( 'scripting.filesystemobject' );
            if ( is_object ( $obj ) ){
                $ref = $obj->getfolder ( $dir );
                $s = intval($ref->size);
                $obj = null;
            }else{
                echo 'can not create object';
            }
        }else{
            if(PHP_OS == "Darwin") $option = "-sk";
            else $option = "-sb";
            $io = popen ( '/usr/bin/du '.$option.' ' . escapeshellarg($dir), 'r' );
           	$size = fgets ( $io, 4096);
            $size = trim(str_replace($dir, "", $size));
            $s = intval($size);
            if(PHP_OS == "Darwin") $s = $s * 1024;
           	//$s = intval(substr ( $size, 0, strpos ( $size, ' ' ) ));
           	pclose ( $io );
        }
        if($s == -1){
            $s = $this->foldersize($dir);
        }

        return $s;
    }

    private function foldersize($path) {

        $total_size = 0;
        $files = scandir($path);

        foreach($files as $t) {
            if (is_dir(rtrim($path, '/') . '/' . $t)) {
                if ($t<>"." && $t<>"..") {
                    $size = foldersize(rtrim($path, '/') . '/' . $t);
                    $total_size += $size;
                }
            } else {
                $size = filesize(rtrim($path, '/') . '/' . $t);
                $total_size += $size;
            }
        }
        return $total_size;
    }


}
