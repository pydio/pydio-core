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
namespace Pydio\Notification\Core;

use Pydio\Access\Core\Model\AJXP_Node;
use Pydio\Access\Core\Model\NodesList;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Model\UserInterface;
use Pydio\Core\Services\SessionService;
use Pydio\Core\Services\UsersService;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class RecentListManager
 * @package Pydio\Notification\Core
 */
class RecentListManager
{
    const LIST_SIZE = 30;

    /** @var  UserInterface */
    private $user;
    /** @var  array */
    private $list;
    /**
     * RecentListManager constructor.
     * @param ContextInterface $ctx
     */
    public function __construct($ctx){
        $this->user = $ctx->getUser();
    }

    /**
     * @return array
     */
    public function load(){
        $recentList = $this->user->getPref('RECENT_LIST');
        if(!empty($recentList)) {
            $recentList = json_decode($recentList, true);
        }
        if(empty($recentList)) {
            $recentList = [];
        }
        $this->list = $recentList;
        return $this->list;
    }

    /**
     * @param AJXP_Node $node
     */
    public function store($node){

        $nodeUrl = $node->getUrl();
        if(!isSet($this->list)){
            $this->load();
        }
        if(in_array($nodeUrl, $this->list)){
            $this->list = array_diff($this->list, [$nodeUrl]);
        }else{
            while(count($this->list) > self::LIST_SIZE){
                array_pop($this->list);
            }
        }
        $this->list[time()] = $nodeUrl;
        krsort($this->list, SORT_NUMERIC);
        $this->user->setPref('RECENT_LIST', json_encode($this->list));
        UsersService::updateUser($this->user, "user");
        $userKey = SessionService::USER_KEY;
        if(SessionService::has($userKey) && SessionService::fetch($userKey)->getId()=== $this->user->getId()){
            SessionService::save($userKey, $this->user);
        }

    }

    /**
     * @return NodesList
     */
    public function toNodesList(){
        if(!isSet($this->list)){
            $this->load();
        }
        $nodesList = new NodesList();
        foreach($this->list as $time => $nodeUrl){
            $node = new AJXP_Node($nodeUrl);
            try{
                @$node->loadNodeInfo();
                $repoId = $node->getRepositoryId();
                $node->mergeMetadata(['recent_access_time' => $time, 'repository_id' => $repoId]);
            }catch(\Exception $e){}
            $nodesList->addBranch($node);
        }
        return $nodesList;
    }
}