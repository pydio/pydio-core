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
class QuotaComputer extends AJXP_AbstractMetaSource
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

    protected function getWorkingPath()
    {
        $repo = $this->accessDriver->repository;
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
        if ( isSet($originalUser) ) {
            $originalUser->setParent($clearParent);
            $originalUser->setResolveAsParent(false);
            AuthService::updateUser($originalUser);
        }

        return $path;
    }

    /**
     * @return array
     */
    protected function getWorkingRepositoryOptions()
    {
        $p = array();
        $repo = $this->accessDriver->repository;
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
        $p["PATH"] = $path;
        if ( isSet($originalUser) ) {
            $originalUser->setParent($clearParent);
            $originalUser->setResolveAsParent(false);
            AuthService::updateUser($originalUser);
        }
        return $p;
    }

    public function getFilteredOption($optionName, $repoScope = AJXP_REPO_SCOPE_ALL, $userObject = null){
        $repo = $this->accessDriver->repository;
        if ($repo->hasParent() && $repo->getOwner() != null && $repo->getOwner() != AuthService::getLoggedUser()->getId()) {
            // Pass parent user instead of currently logged
            $userObject = ConfService::getConfStorageImpl()->createUserObject($repo->getOwner());
        }
        return parent::getFilteredOption($optionName, $repoScope, $userObject);
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
        $quota = $this->getAuthorized();
        $soft = $this->getSoftLimit();
        $q = $this->getUsage();
        $this->logDebug("QUOTA : Previous usage was $q");
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
        $u = $this->getUsage();
        HTMLWriter::charsetHeader("application/json");
        print json_encode(array('USAGE' => $u, 'TOTAL' => $this->getAuthorized()));
        return;
    }

    public function loadRepositoryInfo(&$data){
        $data['meta.quota'] = array(
            'usage' => $u = $this->getUsage(),
            'total' => $this->getAuthorized()
        );
    }

    public function recomputeQuotaUsage($oldNode = null, $newNode = null, $copy = false)
    {
        $repoOptions = $this->getWorkingRepositoryOptions();
        $q = $this->accessDriver->directoryUsage("", $repoOptions);
        $this->storeUsage($q);
        $t = $this->getAuthorized();
        AJXP_Controller::applyHook("msg.instant", array("<metaquota usage='{$q}' total='{$t}'/>", $this->accessDriver->repository->getId()));
    }

    protected function storeUsage($quota)
    {
        $data = $this->getUserData();
        $repo = $this->accessDriver->repository->getId();
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
    private function getUsage()
    {
        $data = $this->getUserData();
        $repo = $this->accessDriver->repository->getId();
        $repoOptions = $this->getWorkingRepositoryOptions();
        if (!isSet($data["REPO_USAGES"][$repo]) || $this->options["CACHE_QUOTA"] === false) {
            $quota = $this->accessDriver->directoryUsage("", $repoOptions);
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

}
