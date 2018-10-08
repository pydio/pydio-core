<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
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
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Access\Meta\Quota;

use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Services\LocaleService;
use Pydio\Core\Services\UsersService;
use Pydio\Core\Utils\Vars\StatHelper;
use Pydio\Core\PluginFramework\PluginsService;
use Pydio\Access\Meta\Core\AbstractMetaSource;

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * Computes used storage for user
 * @package AjaXplorer_Plugins
 * @subpackage Meta
 */
class QuotaComputer extends AbstractMetaSource
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
     * @var \Pydio\Mailer\Core\Mailer
     */
    protected $mailer;
    
    /**
     * @param ContextInterface $ctx
     * @return ContextInterface
     */
    protected function getEffectiveContext(ContextInterface $ctx){
        $repository = $ctx->getRepository();
        $parentOwner = $repository->getOwner();
        if($repository->hasParent() && !empty($parentOwner)){
            return $ctx->withRepositoryId($repository->getParentId())->withUserId($parentOwner);
        }else{
            return $ctx;
        }
    }

    /**
     * @param ContextInterface $ctx
     * @param $optionName
     * @return mixed|null
     */
    public function getContextualOption(ContextInterface $ctx, $optionName)
    {
        $repo = $ctx->getRepository();
        $user = $ctx->getUser();
        if ($repo->hasParent() && $repo->getOwner() != null && $repo->getOwner() != $user->getId()) {
            // Pass parent user instead of currently logged
            $userObject = UsersService::getUserById($repo->getOwner());
            $newCtx = \Pydio\Core\Model\Context::contextWithObjects($userObject, $repo);
            return parent::getContextualOption($newCtx, $optionName);
        }else{
            return parent::getContextualOption($ctx, $optionName);
        }

    }

    /**
     * @param \Pydio\Access\Core\Model\AJXP_Node $node
     * @param int $newSize
     * @return mixed
     * @throws \Exception
     */
    public function precheckQuotaUsage($node, $newSize = 0)
    {
        // POSITIVE DELTA ?
        if ($newSize == 0) {
            return null;
        }
        $delta = $newSize;
        $quota = $this->getAuthorized($node->getContext());
        $soft = $this->getSoftLimit($node->getContext());
        $q = $this->getUsageForContext($node->getContext());
        $this->logDebug("QUOTA : Previous usage was $q");
        if ($q + $delta >= $quota) {
            $mess = LocaleService::getMessages();
            throw new \Exception($mess["meta.quota.3"]." (". StatHelper::roundSize($quota) .")!");
        } else if ( $soft !== false && ($q + $delta) >= $soft && $q <= $soft) {
            $this->sendSoftLimitAlert($node->getContext());
        }
    }

    /**
     * @param ContextInterface $ctx
     */
    protected function sendSoftLimitAlert(ContextInterface $ctx)
    {
        $mailer = PluginsService::getInstance($ctx)->getActivePluginsForType("mailer", true);
        if ($mailer !== false && $ctx->hasUser()) {
            $percent = $this->getContextualOption($ctx, "SOFT_QUOTA");
            $quota = $this->getContextualOption($ctx, "DEFAULT_QUOTA");
            $mailer->sendMail(
                $ctx, 
                array($ctx->getUser()->getId()),
                "You are close to exceed your quota!",
                "You are currently using more than $percent% of your authorized quota of $quota!");
        }
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $requestInterface
     * @param \Psr\Http\Message\ResponseInterface $responseInterface
     */
    public function getCurrentQuota(\Psr\Http\Message\ServerRequestInterface $requestInterface, \Psr\Http\Message\ResponseInterface &$responseInterface)
    {
        $ctx = $requestInterface->getAttribute("ctx");
        $u = $this->getUsageForContext($ctx);
        $responseInterface = new \Zend\Diactoros\Response\JsonResponse(['USAGE' => $u, 'TOTAL' => $this->getAuthorized($ctx)]);
        return;
    }

    /**
     * @param ContextInterface $ctx
     * @param $data
     */
    public function loadRepositoryInfo(ContextInterface $ctx, &$data){
        $data['meta.quota'] = array(
            'usage' => $u = $this->getUsageForContext($ctx),
            'total' => $this->getAuthorized($ctx)
        );
    }

    /**
     * @param AJXP_Node $oldNode
     * @param AJXP_Node $newNode
     * @param bool $copy
     * @throws \Exception
     */
    public function recomputeQuotaUsage($oldNode = null, $newNode = null, $copy = false)
    {
        $refNode = ($oldNode !== null ? $oldNode : $newNode);
        if($refNode->getRepository() !== null && $refNode->getRepository()->getOwner() !== null){
            // Do not recompute usage for shared repositories, this will be called by other forwarded events (up or down)
            return;
        }
        $q = $this->getUsageForContext($refNode->getContext());
        // Warning, do not store usage again here!
        //$this->storeUsage($refNode->getContext(), $q);
        $t = $this->getAuthorized($refNode->getContext());
        Controller::applyHook("msg.instant", array($refNode->getContext(), "<metaquota usage='{$q}' total='{$t}'/>"));
    }

    /**
     * @param ContextInterface $ctx
     * @param $quota
     */
    protected function storeUsage(ContextInterface $ctx, $quota)
    {
        $data = $this->getUserData($ctx);
        $repo = $ctx->getRepositoryId();
        if(!isset($data["REPO_USAGES"])) $data["REPO_USAGES"] = array();
        $data["REPO_USAGES"][$repo] = $quota;
        $this->saveUserData($ctx, $data);
    }

    /**
     * @param ContextInterface $ctx
     * @return int
     */
    protected function getAuthorized(ContextInterface $ctx)
    {
        if(self::$loadedQuota != null) return self::$loadedQuota;
        $q = $this->getContextualOption($ctx, "DEFAULT_QUOTA");
        self::$loadedQuota = StatHelper::convertBytes($q);
        return self::$loadedQuota;
    }

    /**
     * @param ContextInterface $ctx
     * @return bool|float
     */
    protected function getSoftLimit(ContextInterface $ctx)
    {
        if(self::$loadedSoftLimit != null) return self::$loadedSoftLimit;
        $l = $this->getContextualOption($ctx, "SOFT_QUOTA");
        if (!empty($l)) {
            self::$loadedSoftLimit = round($this->getAuthorized($ctx)*intval($l)/100);
        } else {
            self::$loadedSoftLimit = false;
        }
        return self::$loadedSoftLimit;
    }

    /**
     * @param ContextInterface $ctx
     * @param bool
     * @return integer
     */
    private function getUsageForContext(ContextInterface $ctx){

        $ctx = $this->getEffectiveContext($ctx);
        $rootNode = new AJXP_Node($ctx->getUrlBase()."/");
        $data = $this->getUserData($ctx);
        if (!isSet($data["REPO_USAGES"][$ctx->getRepositoryId()]) || $this->options["CACHE_QUOTA"] === false) {

            $quota = $rootNode->getSizeRecursive();
            if(!isset($data["REPO_USAGES"])) $data["REPO_USAGES"] = array();
            $data["REPO_USAGES"][$ctx->getRepositoryId()] = $quota;
            $this->saveUserData($ctx, $data);

        }

        if ($this->getContextualOption($ctx, "USAGE_SCOPE") == "local") {
            return floatval($data["REPO_USAGES"][$ctx->getRepositoryId()]);
        } else {
            return array_sum(array_map("floatval", $data["REPO_USAGES"]));
        }

    }

    /**
     * @param ContextInterface $ctx
     * @return array|mixed|string
     */
    private function getUserData(ContextInterface $ctx)
    {
        $logged = $ctx->getUser();
        $data = $logged->getPref("meta.quota");
        if(is_array($data)) return $data;
        else return array();
    }

    /**
     * @param ContextInterface $ctx
     * @param $data
     */
    private function saveUserData(ContextInterface $ctx, $data)
    {
        $logged = $ctx->getUser();
        $logged->setPref("meta.quota", $data);
        $logged->save("user");
    }

}
