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
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Core
 * @class AbstractAccessDriver
 * Abstract representation of an action driver. Must be implemented.
 */
class AbstractAccessDriver extends AJXP_Plugin
{
    /**
    * @var Repository
    */
    public $repository;
    public $driverType = "access";

    public function init($repository, $options = array())
    {
        //$this->loadActionsFromManifest();
        parent::init($options);
        $this->repository = $repository;
    }

    public function initRepository()
    {
        // To be implemented by subclasses
    }


    public function accessPreprocess($actionName, &$httpVars, &$filesVar)
    {
        if ($actionName == "apply_check_hook") {
            if (!in_array($httpVars["hook_name"], array("before_create", "before_path_change", "before_change"))) {
                return;
            }
            $selection = new UserSelection();
            $selection->initFromHttpVars($httpVars);
            $node = $selection->getUniqueNode($this);
            AJXP_Controller::applyHook("node.".$httpVars["hook_name"], array($node, $httpVars["hook_arg"]));
        }
        if ($actionName == "ls") {
            // UPWARD COMPATIBILTY
            if (isSet($httpVars["options"])) {
                if($httpVars["options"] == "al") $httpVars["mode"] = "file_list";
                else if($httpVars["options"] == "a") $httpVars["mode"] = "search";
                else if($httpVars["options"] == "d") $httpVars["skipZip"] = "true";
                // skip "complete" mode that was in fact quite the same as standard tree listing (dz)
            }
        }
    }

    protected function parseSpecificContributions(&$contribNode)
    {
        parent::parseSpecificContributions($contribNode);
    }


    /**
     * Backward compatibility, now moved to SharedCenter::loadPubliclet();
     * @param $data
     * @return void
     */
    public function loadPubliclet($data)
    {
        require_once(AJXP_INSTALL_PATH . "/" . AJXP_PLUGINS_FOLDER . "/action.share/class.ShareCenter.php");
        ShareCenter::loadPubliclet($data);
    }

    /**
     * Populate publiclet options
     * @param String $filePath The path to the file to share
     * @param String $password optionnal password
     * @param String $downloadlimit optional limit for downloads
     * @param String $expires optional expiration date
     * @param Repository $repository
     * @return Array
     */
    public function makePublicletOptions($filePath, $password, $expires, $downloadlimit, $repository) {}

    /**
     * Populate shared repository options
     * @param Array $httpVars
     * @param Repository $repository
     * @return Array
     */
    public function makeSharedRepositoryOptions($httpVars, $repository){}


    public function crossRepositoryCopy($httpVars)
    {
        ConfService::detectRepositoryStreams(true);
        $mess = ConfService::getMessages();
        $selection = new UserSelection();
        $selection->initFromHttpVars($httpVars);
        $files = $selection->getFiles();

        $accessType = $this->repository->getAccessType();
        $repositoryId = $this->repository->getId();
        $plugin = AJXP_PluginsService::findPlugin("access", $accessType);
        $origWrapperData = $plugin->detectStreamWrapper(true);
        $origStreamURL = $origWrapperData["protocol"]."://$repositoryId";

        $destRepoId = $httpVars["dest_repository_id"];
        $destRepoObject = ConfService::getRepositoryById($destRepoId);
        $destRepoAccess = $destRepoObject->getAccessType();
        $plugin = AJXP_PluginsService::findPlugin("access", $destRepoAccess);
        $destWrapperData = $plugin->detectStreamWrapper(true);
        $destStreamURL = $destWrapperData["protocol"]."://$destRepoId";
        // Check rights
        if (AuthService::usersEnabled()) {
            $loggedUser = AuthService::getLoggedUser();
            if(!$loggedUser->canRead($repositoryId) || !$loggedUser->canWrite($destRepoId)
                || (isSet($httpVars["moving_files"]) && !$loggedUser->canWrite($repositoryId))
            ){
                throw new Exception($mess[364]);
            }
        }

        $messages = array();
        foreach ($files as $file) {
            $origFile = $origStreamURL.$file;
            $localName = "";
            AJXP_Controller::applyHook("dl.localname", array($origFile, &$localName, $origWrapperData["classname"]));
            if (isSet($httpVars["moving_files"])) {
                AJXP_Controller::applyHook("node.before_path_change", array(new AJXP_Node($origFile)));
            }
            $bName = basename($file);
            if ($localName != "") {
                $bName = $localName;
            }
            if (isSet($httpVars["moving_files"])) {
                $touch = filemtime($origFile);
            }
            $destFile = $destStreamURL.SystemTextEncoding::fromUTF8($httpVars["dest"])."/".$bName;
            AJXP_Controller::applyHook("node.before_create", array($destFile));
            if (!is_file($origFile)) {
                throw new Exception("Cannot find $origFile");
            }
            $origHandler = fopen($origFile, "r");
            $destHandler = fopen($destFile, "w");
            if ($origHandler === false || $destHandler === false) {
                $errorMessages[] = AJXP_XMLWriter::sendMessage(null, $mess[114]." ($origFile to $destFile)", false);
                continue;
            }
            while (!feof($origHandler)) {
                fwrite($destHandler, fread($origHandler, 4096));
            }
            fflush($destHandler);
            fclose($origHandler);
            fclose($destHandler);
            AJXP_Controller::applyHook("node.change", array(null, new AJXP_Node($destFile)));
            if (isSet($httpVars["moving_files"])) {
                $wrapName = $destWrapperData["classname"];
                if (!call_user_func(array($wrapName, "isRemote"))) {
                    $real = call_user_func(array($this->wrapperClassName, "getRealFSReference"), $destFile, true);
                    $r = @touch($real, $touch, $touch);

                }
                AJXP_Controller::applyHook("node.change", array(new AJXP_Node($origFile), null));
            }
            $messages[] = $mess[34]." ".SystemTextEncoding::toUTF8(basename($origFile))." ".(isSet($httpVars["moving_files"])?$mess[74]:$mess[73])." ".SystemTextEncoding::toUTF8($destFile);
        }
        AJXP_XMLWriter::header();
        if (count($errorMessages)) {
            AJXP_XMLWriter::sendMessage(null, join("\n", $errorMessages), true);
        }
        AJXP_XMLWriter::sendMessage(join("\n", $messages), null, true);
        AJXP_XMLWriter::close();
    }

    /**
     *
     * Try to reapply correct permissions
     * @param oct $mode
     * @param Repository $repoObject
     * @param Function $remoteDetectionCallback
     */
    public static function fixPermissions(&$stat, $repoObject, $remoteDetectionCallback = null)
    {
        $fixPermPolicy = $repoObject->getOption("FIX_PERMISSIONS");
        $loggedUser = AuthService::getLoggedUser();
        if ($loggedUser == null) {
            return;
        }
        $sessionKey = md5($repoObject->getId()."-".$loggedUser->getId()."-fixPermData");


        if (!isSet($_SESSION[$sessionKey])) {
            if ($fixPermPolicy == "detect_remote_user_id" && $remoteDetectionCallback != null) {
                list($uid, $gid) = call_user_func($remoteDetectionCallback, $repoObject);
                if ($uid != null && $gid != null) {
                    $uid = $repoObject->getOption("UID");
                    $_SESSION[$sessionKey] = array("uid" => $uid, "gid" => $gid);
                }

            } else if (substr($fixPermPolicy, 0, strlen("file:")) == "file:") {
                $filePath = AJXP_VarsFilter::filter(substr($fixPermPolicy, strlen("file:")));
                if (file_exists($filePath)) {
                    // GET A GID/UID FROM FILE
                    $lines = file($filePath);
                    foreach ($lines as $line) {
                        $res = explode(":", $line);
                        if ($res[0] == $loggedUser->getId()) {
                            $uid = $res[1];
                            $gid = $res[2];
                            $_SESSION[$sessionKey] = array("uid" => $uid, "gid" => $gid);
                            break;
                        }
                    }
                }
            }
            // If not set, set an empty anyway
            if (!isSet($_SESSION[$sessionKey])) {
                $_SESSION[$sessionKey] = array(null, null);
            }

        } else {
            $data = $_SESSION[$sessionKey];
            if (!empty($data)) {
                if(isSet($data["uid"])) $uid = $data["uid"];
                if(isSet($data["gid"])) $gid = $data["gid"];
            }
        }

        $p = $stat["mode"];
        //$st = sprintf("%07o", ($p & 7777770));
        //AJXP_Logger::debug("FIX PERM DATA ($fixPermPolicy, $st)".$p,sprintf("%o", ($p & 000777)));
        if ($p != NULL) {
            /*
                decoct returns a string, it's more convenient to manipulate as we know the structure
                of the octal form of stat["mode"]
                    - first two or three chars => file type (dir: 40, file: 100, symlink: 120)
                    - three remaining characters => file permissions (1st char: user, 2nd char: group, 3rd char: others)
            */

            $p = decoct($p);
            $lastInd = (intval($p[0]) == 4)? 4 : 5;
            $otherPerms = decbin(intval($p[$lastInd]));
            $actualPerms = $otherPerms;

            if ( ( isSet($uid) && $stat["uid"] == $uid ) || $fixPermPolicy == "user"  ) {
                AJXP_Logger::debug("upgrading abit to ubit");
                $userPerms = decbin(intval($p[$lastInd - 2]));
                $actualPerms |= $userPerms;
            } else if ( ( isSet($gid) && $stat["gid"] == $gid ) || $fixPermPolicy == "group"  ) {
                AJXP_Logger::debug("upgrading abit to gbit");
                $groupPerms = decbin(intval($p[$lastInd - 1]));
                $actualPerms |= $groupPerms;
            }
            $test = bindec($actualPerms);
            $p[$lastInd] = $test;

            $stat["mode"] = $stat[2] = octdec($p);
            //AJXP_Logger::debug("FIXED PERM DATA ($fixPermPolicy)",sprintf("%o", ($p & 000777)));
        }
    }

    protected function resetAllPermission($value)
    {
    }

}
