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
namespace Pydio\Meta\Core;

use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Core\Model\ContextInterface;

use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\Services\RepositoryService;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Class AJXP_AbstractMetaSource
 * Abstract class from which all meta.* plugins must extend.
 */
abstract class AJXP_AbstractMetaSource extends Plugin {

    /**
     * @var AbstractAccessDriver
     */
    protected $accessDriver;

    /**
     * @param ContextInterface $ctx
     * @param AbstractAccessDriver $accessDriver
     */
    public function initMeta(ContextInterface $ctx, AbstractAccessDriver $accessDriver)
    {

        $this->accessDriver = $accessDriver;
        // Override options with parent META SOURCE options
        // Could be refined ?
        if($this->accessDriver->repository->hasParent()){
            $parentRepo = RepositoryService::getRepositoryById($this->accessDriver->repository->getParentId());
            if($parentRepo != null){
                $sources = $parentRepo->getContextOption($ctx, "META_SOURCES");
                $qParent = $sources["meta.quota"];
                if(is_array($qParent)) $this->options = array_merge($this->options, $qParent);
            }
        }

    }

} 