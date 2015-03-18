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
 * The basic abstraction of a data store. Can map a FileSystem, but can also map data from a totally
 * different source, like the application configurations, a mailbox, etc.
 * @package Pydio
 * @subpackage Core
 */
class Repository implements AjxpGroupPathProvider
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
     * @var Repository
     */
    private $parentTemplateObject;
    /**
     * @var array
     */
    public $streamData;

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
     * @param \ContentFilter $contentFilter
     */
    public function setContentFilter($contentFilter)
    {
        $this->contentFilter = $contentFilter;
    }

    /**
     * Check if a ContentFilter is set or not
     * @return bool
     */
    public function hasContentFilter(){
        return isSet($this->contentFilter);
    }

    /**
     * @return \ContentFilter
     */
    public function getContentFilter()
    {
        return $this->contentFilter;
    }

    /**
     * @param string $id
     * @param string $display
     * @param string $driver
     * @return void
     */
    public function Repository($id, $display, $driver)
    {
        $this->setAccessType($driver);
        $this->setDisplay($display);
        $this->setId($id);
        $this->uuid = md5(microtime());
        $this->slug = AJXP_Utils::slugify($display);
        $this->inferOptionsFromParent = false;
        $this->options["CREATION_TIME"] = time();
        if (AuthService::usersEnabled() && AuthService::getLoggedUser() != null) {
            $this->options["CREATION_USER"] = AuthService::getLoggedUser()->getId();
        }
    }

    /**
     * Create a shared version of this repository
     * @param string $newLabel
     * @param array $newOptions
     * @param string $parentId
     * @param string $owner
     * @param string $uniqueUser
     * @return Repository
     */
    public function createSharedChild($newLabel, $newOptions, $parentId = null, $owner = null, $uniqueUser = null)
    {
        $repo = new Repository(0, $newLabel, $this->accessType);
        $newOptions = array_merge($this->options, $newOptions);
        $repo->options = $newOptions;
        if ($parentId == null) {
            $parentId = $this->getId();
        }
        $repo->setInferOptionsFromParent(true);
        $repo->setOwnerData($parentId, $owner, $uniqueUser);
        return $repo;
    }
    /**
     * Create a child from this repository if it's a template
     * @param string $newLabel
     * @param array $newOptions
     * @param string $owner
     * @param string $uniqueUser
     * @return Repository
     */
    public function createTemplateChild($newLabel, $newOptions, $owner = null, $uniqueUser = null)
    {
        $repo = new Repository(0, $newLabel, $this->accessType);
        $repo->options = $newOptions;
        $repo->setOwnerData($this->getId(), $owner, $uniqueUser);
        $repo->setInferOptionsFromParent(true);
        return $repo;
    }
    /**
     * Recompute uuid
     * @return bool
     */
    public function upgradeId()
    {
        if (!isSet($this->uuid)) {
            $this->uuid = md5(serialize($this));
            //$this->uuid = md5(time());
            return true;
        }
        return false;
    }
    /**
     * Get a uuid
     * @param bool $serial
     * @return string
     */
    public function getUniqueId($serial=false)
    {
        if ($serial) {
            return md5(serialize($this));
        }
        return $this->uuid;
    }
    /**
     * Alias for this repository
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }
    /**
     * Use the slugify function to generate an alias from the label
     * @param string $slug
     * @return void
     */
    public function setSlug($slug = null)
    {
        if ($slug == null) {
            $this->slug = AJXP_Utils::slugify($this->display);
        } else {
            $this->slug = $slug;
        }
    }
    /**
     * Get the <client_settings> content of the manifest.xml
     * @return DOMElement|DOMNodeList|string
     */
    public function getClientSettings()
    {
        $plugin = AJXP_PluginsService::findPlugin("access", $this->accessType);
        if(!$plugin) return "";
        if (isSet($this->parentId)) {
            $parentObject = ConfService::getRepositoryById($this->parentId);
            if ($parentObject != null && $parentObject->isTemplate) {
                $ic = $parentObject->getOption("TPL_ICON_SMALL");
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
    /**
     * Find the streamWrapper declared by the access driver
     * @param bool $register
     * @param array $streams
     * @return bool
     */
    public function detectStreamWrapper($register = false, &$streams=null)
    {
        $plugin = AJXP_PluginsService::findPlugin("access", $this->accessType);
        if(!$plugin) return(false);
        $streamData = $plugin->detectStreamWrapper($register);
        if (!$register && $streamData !== false && is_array($streams)) {
            $streams[$this->accessType] = $this->accessType;
        }
        if($streamData !== false) $this->streamData = $streamData;
        return ($streamData !== false);
    }

    /**
     * Add options
     * @param $oName
     * @param $oValue
     * @return void
     */
    public function addOption($oName, $oValue)
    {
        if (strpos($oName, "PATH") !== false) {
            $oValue = str_replace("\\", "/", $oValue);
        }
        $this->options[$oName] = $oValue;
    }
    /**
     * Get the repository options, filtered in various maners
     * @param string $oName
     * @param bool $safe Do not filter
     * @param AbstractAjxpUser $resolveUser
     * @return mixed|string
     */
    public function getOption($oName, $safe=false, $resolveUser = null)
    {
        if (!$safe && $this->inferOptionsFromParent) {
            if (!isset($this->parentTemplateObject)) {
                $this->parentTemplateObject = ConfService::getRepositoryById($this->parentId);
            }
            if (isSet($this->parentTemplateObject)) {
                $value = $this->parentTemplateObject->getOption($oName, $safe);
                if (is_string($value) && strstr($value, "AJXP_ALLOW_SUB_PATH") !== false) {
                    $val = rtrim(str_replace("AJXP_ALLOW_SUB_PATH", "", $value), "/")."/".$this->options[$oName];
                    return AJXP_Utils::securePath($val);
                }
            }
        }
        if (isSet($this->options[$oName])) {
            $value = $this->options[$oName];
            if(!$safe) $value = AJXP_VarsFilter::filter($value, $resolveUser);
            return $value;
        }
        if ($this->inferOptionsFromParent) {
            if (!isset($this->parentTemplateObject)) {
                $this->parentTemplateObject = ConfService::getRepositoryById($this->parentId);
            }
            if (isSet($this->parentTemplateObject)) {
                return $this->parentTemplateObject->getOption($oName, $safe);
            }
        }
        return "";
    }

    public function resolveVirtualRoots($path)
    {
        // Gathered from the current role
        $roots = $this->listVirtualRoots();
        if(!count($roots)) return $path;
        foreach ($roots as $rootKey => $rootValue) {
            if (strpos($path, "/".ltrim($rootKey, "/")) === 0) {
                return preg_replace("/^\/{$rootKey}/", $rootValue["path"], $path, 1);
            }
        }
        return $path;

    }

    public function listVirtualRoots()
    {
        return array();
        /* TEST STUB
        $roots = array(
            "root1" => array(
                "right" => "rw",
                "path" => "/Test"),
            "root2" => array(
                "right" => "r",
                "path" => "/Retoto/sub"
            ));
        return $roots;
        */
    }

    /**
     * Get the options that already have a value
     * @return array
     */
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

    /**
     * Get the DEFAULT_RIGHTS option
     * @return string
     */
    public function getDefaultRight()
    {
        $opt = $this->getOption("DEFAULT_RIGHTS");
        return (isSet($opt)?$opt:"");
    }


    /**
     * The the access driver type
     * @return String
     */
    public function getAccessType()
    {
        return $this->accessType;
    }

    /**
     * The label of this repository
     * @return String
     */
    public function getDisplay()
    {
        if (isSet($this->displayStringId)) {
            $mess = ConfService::getMessages();
            if (isSet($mess[$this->displayStringId])) {
                return $mess[$this->displayStringId];
            }
        }
        return AJXP_VarsFilter::filter($this->display);
    }

    /**
     * @return string
     */
    public function getId()
    {
        if($this->isWriteable() || $this->id === null) return $this->getUniqueId();
        return $this->id;
    }

    /**
     * @return boolean
     */
    public function getCreate()
    {
        return (bool) $this->getOption("CREATE");
    }

    /**
     * @param boolean $create
     */
    public function setCreate($create)
    {
        $this->options["CREATE"] = (bool) $create;
    }


    /**
     * @param String $accessType
     */
    public function setAccessType($accessType)
    {
        $this->accessType = $accessType;
    }

    /**
     * @param String $display
     */
    public function setDisplay($display)
    {
        $this->display = $display;
    }

    /**
     * @param int $id
     */
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

    public function isEnabled()
    {
        return (bool) $this->enabled;
    }

    public function setEnabled($e)
    {
        $this->enabled = (bool) $e;
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

    /**
     * @param String $descriptionText
     */
    public function setDescription( $descriptionText )
    {
        $this->options["USER_DESCRIPTION"] = $descriptionText;
    }

    /**
     * @param bool $public
     * @return String
     */
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
                $date = AJXP_Utils::relativeDate($this->options["CREATION_TIME"], $m);
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
            $date = AJXP_Utils::relativeDate($this->options["CREATION_TIME"], $m);
            if (isSet($this->options["CREATION_USER"])) {
                return str_replace(array("%date", "%user"), array($date, $this->options["CREATION_USER"]), $m["471"]);
            } else {
                return str_replace(array("%date"), array($date), $m["470"]);
            }
        } else {
            return $m["474"];
        }
    }

    /**
     * Infer a security scope for this repository. Will determine to whome the messages
     * will be broadcasted.
     * @return bool|string
     */
    public function securityScope()
    {
        $path = $this->getOption("PATH", true);
        if($this->accessType == "ajxp_conf") return "USER";
        if(empty($path)) return false;
        if(strpos($path, "AJXP_USER") !== false) return "USER";
        if(strpos($path, "AJXP_GROUP_PATH") !== false) return "GROUP";
        if(strpos($path, "AJXP_GROUP_PATH_FLAT") !== false) return "GROUP";
        return false;
    }

}
