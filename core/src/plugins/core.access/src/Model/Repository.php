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
namespace Pydio\Access\Core\Model;

use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Access\Core\Filter\ContentFilter;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Model\Context;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Model\RepositoryInterface;
use Pydio\Core\Services\AuthService;
use Pydio\Conf\Core\AjxpGroupPathProvider;
use Pydio\Core\Services\ConfService;
use Pydio\Core\Utils\VarsFilter;

defined('AJXP_EXEC') or die( 'Access not allowed');

define('AJXP_REPOSITORY_TYPE_LOCAL', 'local');

/**
 * Class Repository
 * @package Pydio\Access\Core\Model
 */
class Repository implements RepositoryInterface
{
    /**
     * @var string
     */
    public $uuid;
    /**
     * @var string
     */
    public $id;
    /**
     * @var string
     */
    public $path;
    /**
     * @var string
     */
    public $display;
    /**
     * @var string
     */
    public $displayStringId;
    /**
     * @var string
     */
    public $accessType = "fs";
    /**
     * @var string
     */
    public $recycle = "";
    /**
     * @var bool
     */
    public $create = true;
    /**
     * @var bool
     */
    public $writeable = true;
    /**
     * @var bool
     */
    public $enabled = true;
    /**
     * @var array
     */
    public $options = array();
    /**
     * @var string
     */
    public $slug;
    /**
     * @var bool
     */
    public $isTemplate = false;

    /**
     * @var string local or remote
     */
    protected $repositoryType = AJXP_REPOSITORY_TYPE_LOCAL;

    /**
     * @var string status
     */
    protected $accessStatus;

    /**
     * @var string
     */
    private $owner;
    /**
     * @var string
     */
    private $parentId;
    /**
     * @var string
     */
    private $uniqueUser;
    /**
     * @var bool
     */
    private $inferOptionsFromParent;

    /**
     * @var String the groupPath of the administrator who created that repository.
     */
    protected $groupPath;


    /**
     * @var AbstractAccessDriver
     */
    public $driverInstance;

    /**
     * @var ContentFilter
     */
    protected $contentFilter;

    /**
     * @var array Store filters as array variable to avoid serialization problems
     */
    private $contentFilterData;

    public function setContentFilter($contentFilter)
    {
        $this->contentFilter = $contentFilter;
        $this->contentFilterData  = $contentFilter->filters;
    }

    /**
     * Fix unserialization issues by reloading object from data array
     */
    private function checkCFilterData(){
        if(!is_object($this->contentFilter) && is_array($this->contentFilterData)){
            $cf = new ContentFilter(array());
            $cf->fromFilterArray($this->contentFilterData);
            $this->contentFilter = $cf;
        }
    }

    public function hasContentFilter(){
        $this->checkCFilterData();
        return isSet($this->contentFilter);
    }

    public function getContentFilter()
    {
        $this->checkCFilterData();
        return $this->contentFilter;
    }

    /**
     * @return AbstractAccessDriver
     */
    public function getDriverInstance()
    {
        return $this->driverInstance;
    }

    /**
     * @param AbstractAccessDriver $driverInstance
     */
    public function setDriverInstance($driverInstance)
    {
        $this->driverInstance = $driverInstance;
    }

    /**
     * @param string $id
     * @param string $display
     * @param string $driver
     */
    public function __construct($id, $display, $driver)
    {
        $this->setAccessType($driver);
        $this->setDisplay($display);
        $this->setId($id);
        $this->uuid = md5(microtime());
        $this->slug = \Pydio\Core\Utils\Utils::slugify($display);
        $this->inferOptionsFromParent = false;
        $this->options["CREATION_TIME"] = time();
        if (AuthService::usersEnabled() && AuthService::getLoggedUser() != null) {
            $this->options["CREATION_USER"] = AuthService::getLoggedUser()->getId();
        }
    }

    public function createSharedChild($newLabel, $newOptions, $parentId, $owner, $uniqueUser = null)
    {
        $repo = new Repository(0, $newLabel, $this->accessType);
        $newOptions = array_merge($this->options, $newOptions);
        $newOptions["CREATION_TIME"] = time();
        $newOptions["CREATION_USER"] = $owner;
        $repo->options = $newOptions;
        if ($parentId === null) {
            $parentId = $this->getId();
        }
        $repo->setInferOptionsFromParent(true);
        $repo->setOwnerData($parentId, $owner, $uniqueUser);
        return $repo;
    }

    public function createTemplateChild($newLabel, $newOptions, $creator = null, $uniqueUser = null)
    {
        $repo = new Repository(0, $newLabel, $this->accessType);
        $newOptions["CREATION_TIME"] = time();
        $newOptions["CREATION_USER"] = $creator;
        $repo->options = $newOptions;
        $repo->setOwnerData($this->getId(), null, $uniqueUser);
        $repo->setInferOptionsFromParent(true);
        return $repo;
    }

    public function upgradeId()
    {
        if (!isSet($this->uuid)) {
            $this->uuid = md5(serialize($this));
            //$this->uuid = md5(time());
            return true;
        }
        return false;
    }

    public function getUniqueId($serial=false)
    {
        if ($serial) {
            return md5(serialize($this));
        }
        return $this->uuid;
    }

    public function getSlug()
    {
        return $this->slug;
    }

    public function setSlug($slug = null)
    {
        if ($slug === null) {
            $this->slug = \Pydio\Core\Utils\Utils::slugify($this->display);
        } else {
            $this->slug = $slug;
        }
    }

    public function getClientSettings(ContextInterface $ctx)
    {
        $plugin = $this->driverInstance;
        if(!$plugin) return "";
        if (isSet($this->parentId)) {
            $parentObject = ConfService::getRepositoryById($this->parentId);
            if ($parentObject != null && $parentObject->isTemplate) {
                $ic = $parentObject->getContextOption($ctx, "TPL_ICON_SMALL");
                $settings = $plugin->getManifestRawContent("//client_settings", "node");
                if (!empty($ic) && $settings->length) {
                    $newAttr = $settings->item(0)->ownerDocument->createAttribute("icon_tpl_id");
                    $newAttr->nodeValue = $this->parentId;
                    $settings->item(0)->appendChild($newAttr);
                    return $settings->item(0)->ownerDocument->saveXML($settings->item(0));
                }
            }
        }
        return $plugin->getManifestRawContent("//client_settings", "string");
    }

    public function addOption($oName, $oValue)
    {
        if (strpos($oName, "PATH") !== false) {
            $oValue = str_replace("\\", "/", $oValue);
        }
        $this->options[$oName] = $oValue;
    }

    /**
     * @param string $oName
     * @return mixed|string
     * @throws \Exception
     */
    public function getSafeOption($oName){
        if (isSet($this->options[$oName])) {
            $value = $this->options[$oName];
            return $value;
        }
        if ($this->inferOptionsFromParent && isSet($this->parentId)) {
            $parentTemplateObject = ConfService::getRepositoryById($this->parentId);
            if(empty($parentTemplateObject) || !$parentTemplateObject instanceof RepositoryInterface) {
                throw new PydioException("Option should be loaded from parent repository, but it was not found");
            }
            return $parentTemplateObject->getSafeOption($oName);
        }
        return null;
    }

    /**
     * @param ContextInterface $ctx
     * @param string $oName
     * @return mixed
     * @throws \Pydio\Core\Exception\PydioException
     */
    public function getContextOption(ContextInterface $ctx, $oName){
        if(isSet($this->inferOptionsFromParent) && isSet($this->parentId)){
            $parentTemplateObject = ConfService::getRepositoryById($this->parentId);
            if(empty($parentTemplateObject) || !is_a($parentTemplateObject, "Repository")) {
                throw new PydioException("Option should be loaded from parent repository, but it was not found");
            }
        }
        if ($this->inferOptionsFromParent && isSet($parentTemplateObject)) {
            $pvalue = $parentTemplateObject->getContextOption($ctx, $oName);
            $pathChanged = false;
            if (is_string($pvalue) && strstr($pvalue, "AJXP_ALLOW_SUB_PATH") !== false) {
                $pvalue = rtrim(str_replace("AJXP_ALLOW_SUB_PATH", "", $pvalue), "/")."/".$this->options[$oName];
                $pathChanged = true;
            }
            if ($pathChanged) {
                return \Pydio\Core\Utils\Utils::securePath($pvalue);
            }
        }
        if (isSet($this->options[$oName])) {
            return VarsFilter::filter($this->options[$oName], $ctx);
        }
        if ($this->inferOptionsFromParent && isset($parentTemplateObject)) {
                return $parentTemplateObject->getContextOption($ctx, $oName);
        }
        return null;

    }

    public function getOption($oName, $safe=false, $resolveUser = null)
    {
        if(isSet($this->inferOptionsFromParent) && isSet($this->parentId)){
            $parentTemplateObject = ConfService::getRepositoryById($this->parentId);
            if(empty($parentTemplateObject) || !is_a($parentTemplateObject, "Repository")) {
                throw new \Exception("Option should be loaded from parent repository, but it was not found");
            }
        }
        if (!$safe && $this->inferOptionsFromParent) {
            if (isSet($parentTemplateObject)) {
                $pvalue = $parentTemplateObject->getOption($oName, $safe);
                $pathChanged = false;
                if (is_string($pvalue) && strstr($pvalue, "AJXP_WORKSPACE_UUID") !== false) {
                    $pvalue = rtrim(str_replace("AJXP_WORKSPACE_UUID", $this->getUniqueId(), $pvalue), "/");
                    $pathChanged = true;
                }
                if (is_string($pvalue) && strstr($pvalue, "AJXP_WORKSPACE_SLUG") !== false) {
                    $pvalue = rtrim(str_replace("AJXP_WORKSPACE_SLUG", $this->getSlug(), $pvalue), "/");
                    $pathChanged = true;
                }
                if (is_string($pvalue) && strstr($pvalue, "AJXP_ALLOW_SUB_PATH") !== false) {
                    $pvalue = rtrim(str_replace("AJXP_ALLOW_SUB_PATH", "", $pvalue), "/")."/".$this->options[$oName];
                    $pathChanged = true;
                }
                if ($pathChanged) {
                    return \Pydio\Core\Utils\Utils::securePath($pvalue);
                }
            }
        }
        if (isSet($this->options[$oName])) {
            $value = $this->options[$oName];
            $tmpContext = Context::fromGlobalServices();
            if($resolveUser !== null) $tmpContext->setUserId($resolveUser);
            if(!$safe) $value = VarsFilter::filter($value, $tmpContext);
            return $value;
        }
        if ($this->inferOptionsFromParent) {
            if (!isset($parentTemplateObject)) {
                $parentTemplateObject = ConfService::getRepositoryById($this->parentId);
            }
            if (isSet($parentTemplateObject)) {
                return $parentTemplateObject->getOption($oName, $safe);
            }
        }
        return "";
    }

    public function getOptionsDefined()
    {
        //return array_keys($this->options);
        $keys = array();
        foreach ($this->options as $key => $value) {
            if(is_string($value) && strstr($value, "AJXP_ALLOW_SUB_PATH") !== false) continue;
            $keys[] = $key;
        }
        return $keys;
    }

    public function getDefaultRight()
    {
        $opt = $this->getSafeOption("DEFAULT_RIGHTS");
        return (isSet($opt)?$opt:"");
    }


    public function getAccessType()
    {
        return $this->accessType;
    }

    public function getDisplay()
    {
        if (isSet($this->displayStringId)) {
            $mess = ConfService::getMessages();
            if (isSet($mess[$this->displayStringId])) {
                return $mess[$this->displayStringId];
            }
        }
        return VarsFilter::filter($this->display, Context::fromGlobalServices());
    }

    public function getId()
    {
        if($this->isWriteable() || $this->id === null) return $this->getUniqueId();
        return $this->id;
    }

    public function getCreate()
    {
        return (bool) $this->getSafeOption("CREATE");
    }

    public function setCreate($create)
    {
        $this->options["CREATE"] = (bool) $create;
    }


    public function setAccessType($accessType)
    {
        $this->accessType = $accessType;
    }

    public function setDisplay($display)
    {
        $this->display = $display;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function isWriteable()
    {
        return (bool) $this->writeable;
    }

    public function setWriteable($w)
    {
        $this->writeable = (bool) $w;
    }

    public function setDisplayStringId($id)
    {
        $this->displayStringId = $id;
    }

    public function setOwnerData($repoParentId, $ownerUserId = null, $childUserId = null)
    {
        $this->owner = $ownerUserId;
        $this->uniqueUser = $childUserId;
        $this->parentId = $repoParentId;
    }

    public function getOwner()
    {
        return $this->owner;
    }

    public function getParentId()
    {
        return $this->parentId;
    }

    public function getUniqueUser()
    {
        return $this->uniqueUser;
    }

    public function hasOwner()
    {
        return isSet($this->owner);
    }

    public function hasParent()
    {
        return isSet($this->parentId);
    }

    public function setInferOptionsFromParent($bool)
    {
        $this->inferOptionsFromParent = (bool) $bool;
    }

    public function getInferOptionsFromParent()
    {
        return (bool) $this->inferOptionsFromParent;
    }

    /**
     * @param String $groupPath
     */
    public function setGroupPath($groupPath)
    {
        if(strlen($groupPath) > 1) $groupPath = rtrim($groupPath, "/");
        $this->groupPath = $groupPath;
    }

    /**
     * @return String
     */
    public function getGroupPath()
    {
        return $this->groupPath;
    }

    public function setDescription( $descriptionText )
    {
        $this->options["USER_DESCRIPTION"] = $descriptionText;
    }

    public function getAccessStatus()
    {
        return $this->accessStatus;
    }

    public function setAccessStatus($accessStatus)
    {
        $this->accessStatus = $accessStatus;
    }

    public function getRepositoryType()
    {
        return $this->repositoryType;
    }

    public function setRepositoryType($repositoryType)
    {
        $this->repositoryType = $repositoryType;
    }

    public function getDescription( $public = false, $ownerLabel = null )
    {
        $m = ConfService::getMessages();
        if (isset($this->options["USER_DESCRIPTION"]) && !empty($this->options["USER_DESCRIPTION"])) {
            if (isSet($m[$this->options["USER_DESCRIPTION"]])) {
                return $m[$this->options["USER_DESCRIPTION"]];
            } else {
                return $this->options["USER_DESCRIPTION"];
            }
        }
        if (isSet($this->parentId) && isset($this->owner)) {
            if (isSet($this->options["CREATION_TIME"])) {
                $date = \Pydio\Core\Utils\Utils::relativeDate($this->options["CREATION_TIME"], $m);
                return str_replace(
                    array("%date", "%user"),
                    array($date, $ownerLabel!= null ? $ownerLabel : $this->owner),
                    $public?$m["470"]:$m["473"]);
            } else {
                if($public) return $m["474"];
                else return str_replace(
                    array("%user"),
                    array($ownerLabel!= null ? $ownerLabel : $this->owner),
                    $m["472"]);
            }
        } else if ($this->isWriteable() && isSet($this->options["CREATION_TIME"])) {
            $date = \Pydio\Core\Utils\Utils::relativeDate($this->options["CREATION_TIME"], $m);
            if (isSet($this->options["CREATION_USER"])) {
                return str_replace(array("%date", "%user"), array($date, $this->options["CREATION_USER"]), $m["471"]);
            } else {
                return str_replace(array("%date"), array($date), $m["470"]);
            }
        } else {
            return $m["474"];
        }
    }

    public function securityScope()
    {
        if($this->hasParent()){
            $parentRepo = ConfService::getRepositoryById($this->getParentId());
            if(!empty($parentRepo) && $parentRepo->isTemplate){
                $path = $parentRepo->getSafeOption("PATH");
                $container = $parentRepo->getSafeOption("CONTAINER");
                // If path is set in the template, compute identifier from the template path.
                if(!empty($path) || !empty($container)) return $parentRepo->securityScope();
            }
        }
        $path = $this->getSafeOption("CONTAINER");
        if(!empty($path)){
            if(strpos($path, "AJXP_USER") !== false) return "USER";
            if(strpos($path, "AJXP_GROUP_PATH") !== false) return "GROUP";
            if(strpos($path, "AJXP_GROUP_PATH_FLAT") !== false) return "GROUP";
        }
        $path = $this->getSafeOption("PATH");
        if($this->accessType == "ajxp_conf" || $this->accessType == "ajxp_admin" || $this->accessType == "inbox") return "USER";
        if(empty($path)) return false;
        if(strpos($path, "AJXP_USER") !== false) return "USER";
        if(strpos($path, "AJXP_GROUP_PATH") !== false) return "GROUP";
        if(strpos($path, "AJXP_GROUP_PATH_FLAT") !== false) return "GROUP";
        return false;
    }

    public function __sleep()
    {
        $this->driverInstance = null;
        return array_keys(get_object_vars($this));
    }

}
