<?php
/*
 * Copyright 2007-2016 Abstrium <contact (at) pydio.com>
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
 * The latest code can be found at <https://pydio.com/>.
 */
namespace Pydio\Access\Driver\DataProvider\Provisioning;

use Pydio\Access\Core\Model\NodesList;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Services\UsersService;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Parent Class for CRUD operation of application objects.
 * @package Pydio\Access\Driver\DataProvider\Provisioning
 */
abstract class AbstractManager
{
    /** @var  ContextInterface */
    protected $context;

    /** @var  array */
    protected $bookmarks;

    /** @var  string */
    protected $pluginName;

    /**
     * Manager constructor.
     * @param ContextInterface $ctx
     * @param string $pluginName
     */
    public function __construct(ContextInterface $ctx, $pluginName){
        $this->context = $ctx;
        $this->pluginName = $pluginName;
    }

    /**
     * @return bool
     */
    protected function currentUserIsGroupAdmin(){
        return (UsersService::usersEnabled() && $this->context->getUser()->getGroupPath() !== "/");
    }

    /**
     * @return array
     */
    protected function getBookmarks(){
        if(!isSet($this->bookmarks)){
            $this->bookmarks = [];
            if(UsersService::usersEnabled()) {
                $bookmarks = $this->context->getUser()->getBookmarks($this->context->getRepositoryId());
                foreach ($bookmarks as $bm) {
                    $this->bookmarks[] = $bm["PATH"];
                }
            }
        }
        return $this->bookmarks;
    }

    /**
     * @param string $nodePath
     * @param array $meta
     */
    protected function appendBookmarkMeta($nodePath, &$meta){
        if(in_array($nodePath, $this->getBookmarks())) {
            $meta = array_merge($meta, array(
                "ajxp_bookmarked" => "true",
                "overlay_icon" => "bookmark.png"
            ));
        }
    }

    /**
     * @param array $httpVars Full set of query parameters
     * @param string $rootPath Path to prepend to the resulting nodes
     * @param string $relativePath Specific path part for this function
     * @param string $paginationHash Number added to url#2 for pagination purpose.
     * @param string $findNodePosition Path to a given node to try to find it
     * @param string $aliasedDir Aliased path used for alternative url
     *
     * @return NodesList A populated NodesList object, eventually recursive.
     */
    public abstract function listNodes($httpVars, $rootPath, $relativePath, $paginationHash = null, $findNodePosition=null, $aliasedDir=null);

}