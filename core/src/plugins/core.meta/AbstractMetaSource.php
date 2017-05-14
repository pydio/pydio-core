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
namespace Pydio\Access\Meta\Core;

use Pydio\Access\Core\AbstractAccessDriver;
use Pydio\Core\Model\ContextInterface;

use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\Services\RepositoryService;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Class AbstractMetaSource
 * Abstract class from which all meta.* plugins must extend.
 */
abstract class AbstractMetaSource extends Plugin {

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

        // Override options with parent META SOURCE options
        // Could be refined ?
        if($ctx->getRepository()->hasParent()){
            $parentRepo = RepositoryService::getRepositoryById($ctx->getRepository()->getParentId());
            if($parentRepo != null){
                $sources = $parentRepo->getContextOption($ctx, "META_SOURCES");
                $qParent = $sources["meta.quota"];
                if(is_array($qParent)) $this->options = array_merge($this->options, $qParent);
            }
        }

    }

} 