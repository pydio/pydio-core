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
namespace Pydio\Core\Http\Dav;

use Pydio\Core\Model\FilteredRepositoriesList;
use \Sabre;
use Pydio\Core\Model\ContextInterface;

use Pydio\Core\PluginFramework\PluginsService;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Class RootCollection
 * @package Pydio\Core\Http\Dav
 */
class RootCollection extends Sabre\DAV\SimpleCollection
{
    /** @var  ContextInterface */
    protected $context;

    /**
     * @return ContextInterface
     */
    public function getContext(){
        return $this->context;
    }

    /**
     * @return ContextInterface
     */
    public function setContext($context){
        return $this->context = $context;
    }

    /**
     * @return Node[]
     */
    public function getChildren()
    {
        $this->children = array();
        if($this->context == null || !$this->context->hasUser()){
            return $this->children;
        }
        $filteredList = new FilteredRepositoriesList($this->context->getUser());
        $repos = $filteredList->load();
        // Refilter to make sure the driver is an AjxpWebdavProvider
        foreach ($repos as $repository) {
            $accessType = $repository->getAccessType();
            $driver = PluginsService::getInstance($this->context)->getPluginByTypeName("access", $accessType);
            if ($driver instanceof \Pydio\Access\Core\IAjxpWrapperProvider && $repository->getContextOption($this->context, "AJXP_WEBDAV_DISABLED") !== true) {
                $this->children[$repository->getSlug()] = new Sabre\DAV\SimpleCollection($repository->getSlug());
            }
        }
        return $this->children;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function childExists($name)
    {
        $c = $this->getChildren();
        return array_key_exists($name, $c);
    }

    /**
     * @param string $name
     * @return Node
     */
    public function getChild($name)
    {
        $c = $this->getChildren();
        return $c[$name];
    }

}
