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
 * Computes used storage for user
 * @package AjaXplorer_Plugins
 * @subpackage Meta
 */
class QuotaComputer extends AJXP_Plugin
{
    /**
     * @var AbstractAccessDriver
     */
    protected $accessDriver;
    protected $currentQuota;
    protected $computeLocal = true;
    public static $loadedQuota;
    public static $loadedSoftLimit;
    /**
     * @var AjxpMailer
     */
    protected $mailer;

    public function initMeta($accessDriver)
    {
        $this->accessDriver = $accessDriver;
    }

    protected function getWorkingPath()
    {
        $repo = ConfService::getRepository();
        $clearParent = null;
        // SPECIAL : QUOTA MUST BE COMPUTED ON PARENT REPOSITORY FOLDER
        if ($repo->hasParent()) {
            $parentOwner = $repo->getOwner();
            if ($parentOwner !== null) {
                $repo = ConfService::getRepositoryById($repo->getParentId());
                $originalUser = AuthService::getLoggedUser();
                $loggedUser = AuthService::getLoggedUser();
                if (!$loggedUser->hasParent()) {
                    $loggedUser->setParent($parentOwner);
                    $clearParent = null;
                } else {
                    $clearParent = $loggedUser->getParent();
                }
                $loggedUser->setResolveAsParent(true);
                AuthService::updateUser($loggedUser);
            }
        }
        $path = $repo->getOption("PATH");
        if (iSset($originalUser)) {
            $originalUser->setParent($clearParent);
            $originalUser->setResolveAsParent(false);
            AuthService::updateUser($originalUser);
        }

        return $path;
    }

    /**
     * @param AJXP_Node $node
     * @param int $newSize
     * @return mixed
     * @throws Exception
     */
    public function precheckQuotaUsage($node, $newSize = 0)
    {
        // POSITIVE DELTA ?
        if ($newSize == 0) {
            return null;
        }
        $delta = $newSize;
        $path = $this->getWorkingPath();
        $quota = $this->getAuthorized();
        $soft = $this->getSoftLimit();
        $q = $this->getUsage($path);
        $this->logDebug("QUOTA : Previous usage was $q");
        if ($q === false) {
            $q = $this->computeDirSpace($path);
        }
        if ($q + $delta >= $quota) {
            $mess = ConfService::getMessages();
            throw new Exception($mess["meta.quota.3"]." (".AJXP_Utils::roundSize($quota) .")!");
        } else if ( $soft !== false && ($q + $delta) >= $soft && $q <= $soft) {
            $this->sendSoftLimitAlert();
        }
    }

    protected function sendSoftLimitAlert()
    {
        $mailers = AJXP_PluginsService::getInstance()->getPluginsByType("mailer");
        if (count($mailers)) {
            $this->mailer = array_shift($mailers);
            $percent = $this->getFilteredOption("SOFT_QUOTA");
            $quota = $this->getFilteredOption("DEFAULT_QUOTA");
            $this->mailer->sendMail(
                array(AuthService::getLoggedUser()->getId()),
                "You are close to exceed your quota!",
                "You are currently using more than $percent% of your authorized quota of $quota!");
        }
    }

    public function getCurrentQuota($action, $httpVars, $fileVars)
    {
        $u = $this->getUsage($this->getWorkingPath());
        HTMLWriter::charsetHeader("application/json");
        print json_encode(array('USAGE' => $u, 'TOTAL' => $this->getAuthorized()));
        return;
    }

    public function recomputeQuotaUsage($oldNode = null, $newNode = null, $copy = false)
    {
        $path = $this->getWorkingPath();
        $q = $this->computeDirSpace($path);
        $this->storeUsage($path, $q);
        $t = $this->getAuthorized();
        AJXP_Controller::applyHook("msg.instant", array("<metaquota usage='{$q}' total='{$t}'/>", ConfService::getRepository()->getId()));
    }

    protected function storeUsage($dir, $quota)
    {
        $data = $this->getUserData();
        $repo = ConfService::getRepository()->getId();
        if(!isset($data["REPO_USAGES"])) $data["REPO_USAGES"] = array();
        $data["REPO_USAGES"][$repo] = $quota;
        $this->saveUserData($data);
    }

    protected function getAuthorized()
    {
        if(self::$loadedQuota != null) return self::$loadedQuota;
        $q = $this->getFilteredOption("DEFAULT_QUOTA");
        self::$loadedQuota = AJXP_Utils::convertBytes($q);
        return self::$loadedQuota;
    }

    protected function getSoftLimit()
    {
        if(self::$loadedSoftLimit != null) return self::$loadedSoftLimit;
        $l = $this->getFilteredOption("SOFT_QUOTA");
        if (!empty($l)) {
            self::$loadedSoftLimit = round($this->getAuthorized()*intval($l)/100);
        } else {
            self::$loadedSoftLimit = false;
        }
        return self::$loadedSoftLimit;
    }

    /**
     * @param String $dir
     * @return bool|int
     */
    private function getUsage($dir)
    {
        $data = $this->getUserData();
        $repo = ConfService::getRepository()->getId();
        if (!isSet($data["REPO_USAGES"][$repo]) || $this->options["CACHE_QUOTA"] === false) {
            $quota = $this->computeDirSpace($dir);
            if(!isset($data["REPO_USAGES"])) $data["REPO_USAGES"] = array();
            $data["REPO_USAGES"][$repo] = $quota;
            $this->saveUserData($data);
        }

        if ($this->getFilteredOption("USAGE_SCOPE", $repo) == "local") {
            return floatval($data["REPO_USAGES"][$repo]);
        } else {
            return array_sum(array_map("floatval", $data["REPO_USAGES"]));
        }

    }

    private function getUserData()
    {
        $logged = AuthService::getLoggedUser();
        $data = $logged->getPref("meta.quota");
        if(is_array($data)) return $data;
        else return array();
    }

    private function saveUserData($data)
    {
        $logged = AuthService::getLoggedUser();
        $logged->setPref("meta.quota", $data);
        $logged->save("user");
        AuthService::updateUser($logged);
    }

    private function computeDirSpace($dir)
    {
        $this->logDebug("Computing dir space for : ".$dir);
        $s = -1;
        if (PHP_OS == "WIN32" || PHP_OS == "WINNT" || PHP_OS == "Windows") {

            $obj = new COM ( 'scripting.filesystemobject' );
            if ( is_object ( $obj ) ) {
                $ref = $obj->getfolder ( $dir );
                $s = floatval($ref->size);
                $obj = null;
            } else {
                echo 'can not create object';
            }
        } else {
            if(PHP_OS == "Darwin") $option = "-sk";
            else $option = "-sb";
            $io = popen ( '/usr/bin/du '.$option.' ' . escapeshellarg($dir), 'r' );
               $size = fgets ( $io, 4096);
            $size = trim(str_replace($dir, "", $size));
            $s =  floatval($size);
            if(PHP_OS == "Darwin") $s = $s * 1024;
               //$s = intval(substr ( $size, 0, strpos ( $size, ' ' ) ));
               pclose ( $io );
        }
        if ($s == -1) {
            $s = $this->foldersize($dir);
        }

        return $s;
    }

    private function foldersize($path)
    {
        $total_size = 0;
        $files = scandir($path);

        foreach ($files as $t) {
            if (is_dir(rtrim($path, '/') . '/' . $t)) {
                if ($t<>"." && $t<>"..") {
                    $size = foldersize(rtrim($path, '/') . '/' . $t);
                    $total_size += $size;
                }
            } else {
                $size = sprintf("%u", filesize(rtrim($path, '/') . '/' . $t));
                $total_size += $size;
            }
        }
        return $total_size;
    }


}
