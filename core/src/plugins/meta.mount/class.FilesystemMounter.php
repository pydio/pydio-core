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

/**
 * Dynamically mount a remote folder when switching to the repository
 * @package AjaXplorer_Plugins
 * @subpackage Meta
 *
 */
class FilesystemMounter extends AJXP_Plugin
{
    protected $accessDriver;

    public function beforeInitMeta($accessDriver)
    {
        $this->accessDriver = $accessDriver;
        if($this->isAlreadyMounted()) return;
        $this->mountFS();
    }

    public function initMeta($accessDriver)
    {
        $this->accessDriver = $accessDriver;
        /*
        if($this->isAlreadyMounted()) return;
        $this->mountFS();
        */
    }

    protected function getCredentials()
    {
        // 1. Try from plugin config
        $user = $this->options["USER"];
        $password = $this->options["PASS"];
        // 1BIS : encoded?
        if ($user == "" && isSet($this->options["ENCODED_CREDENTIALS"])) {
            list($user,$password) = AJXP_Safe::getCredentialsFromEncodedString($this->options["ENCODED_CREDENTIALS"]);
        }
        // 2. Try from session
        if ($user=="" &&  isSet($this->options["USE_SESSION_CREDENTIALS"]) ) {
            $safeCred = AJXP_Safe::loadCredentials();
            if ($safeCred !== false) {
                $user = $safeCred["user"];
                $password = $safeCred["password"];
            } else {
                throw new Exception("Session credential are empty! Did you forget to check the Set Session Credential in the Authentication configuration panel?");
            }
        }
        return array($user, $password);
    }

    protected function getOption($name, $user="", $pass="", $escapePass = true)
    {
        $opt = $this->options[$name];
        $opt = str_replace("AJXP_USER", $user, $opt);
        if($escapePass)  $opt = str_replace("AJXP_PASS",  "'$pass'", $opt);
        else $opt = str_replace("AJXP_PASS",  $pass, $opt);
        $opt = str_replace("AJXP_SERVER_UID", posix_getuid(), $opt);
        $opt = str_replace("AJXP_SERVER_GID", posix_getgid(), $opt);
        if (stristr($opt, "AJXP_REPOSITORY_PATH") !== false) {
            $repo = ConfService::getRepository();
            $path = $repo->getOption("PATH");
            $opt = str_replace("AJXP_REPOSITORY_PATH", $path, $opt);
        }
        $opt = AJXP_VarsFilter::filter($opt);
        return $opt;
    }

    protected function isAlreadyMounted()
    {
        list($user, $password) = $this->getCredentials();
        $MOUNT_POINT = $this->getOption("MOUNT_POINT", $user, $password);
        return is_file($MOUNT_POINT."/.ajxp_mount");
    }

    public function mountFS()
    {
        list($user, $password) = $this->getCredentials();
        $this->logDebug("FSMounter::mountFS Should mount" . $user);
        $repo = ConfService::getRepository();

        $MOUNT_TYPE = $this->options["FILESYSTEM_TYPE"];
        $MOUNT_SUDO = $this->options["MOUNT_SUDO"];
        $MOUNT_POINT = $this->getOption("MOUNT_POINT", $user, $password);
        $MOUNT_POINT_ROOT = $this->getOption("MOUNT_POINT", "", "");
        $create = $repo->getOption("CREATE");
        if ( $MOUNT_POINT != $MOUNT_POINT_ROOT && !is_dir($MOUNT_POINT_ROOT) && $create) {
            @mkdir($MOUNT_POINT_ROOT, 0755);
        }
        $recycle = false;
        if (!is_dir($MOUNT_POINT) && $create) {
            @mkdir($MOUNT_POINT, 0755);
        } else {
            if ($repo->getOption("RECYCLE_BIN") != "") {
                // Make sure the recycle bin was not mounted inside the mount point!
                $recycle = $repo->getOption("PATH")."/".$repo->getOption("RECYCLE_BIN");
                if (@is_dir($recycle)) {
                    @rmdir($recycle);
                }
            }
        }
        $UNC_PATH = $this->getOption("UNC_PATH", $user, $password, false);
        $MOUNT_OPTIONS = $this->getOption("MOUNT_OPTIONS", $user, $password);

        $cmd = ($MOUNT_SUDO? "sudo ": ""). "mount -t " .$MOUNT_TYPE. (empty( $MOUNT_OPTIONS )? " " : " -o " .$MOUNT_OPTIONS. " " ) .$UNC_PATH. " " .$MOUNT_POINT;
        shell_exec($cmd);
        // Check it is correctly mounted now!
        $cmd = ($MOUNT_SUDO?"sudo":"")." mount | grep ".escapeshellarg($MOUNT_POINT);
        $output = shell_exec($cmd);
        if ($output == null || trim($output) == "") {
            throw new Exception("Error while mounting file system - Test was ".$cmd);
        } else {
            if (!is_file($MOUNT_POINT."/.ajxp_mount")) {
                @file_put_contents($MOUNT_POINT."/.ajxp_mount", "");
            }
            if ($recycle !== false && !is_dir($recycle)) {
                @mkdir($recycle, 0755);
            }
        }
    }

    public function umountFS()
    {
        $this->logDebug("FSMounter::unmountFS");
        list($user, $password) = $this->getCredentials();
        $MOUNT_POINT = $this->getOption("MOUNT_POINT", $user, $password);
        $MOUNT_SUDO = $this->options["MOUNT_SUDO"];

        system(($MOUNT_SUDO?"sudo":"")." umount ".$MOUNT_POINT);
        return true;
    }

}
