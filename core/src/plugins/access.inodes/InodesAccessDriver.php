<?php
/*
 * Copyright 2007-2017 Abstrium <contact (at) pydio.com>
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
namespace Pydio\Access\Driver\StreamProvider\Inodes;

use Pydio\Access\Driver\StreamProvider\FS\FsAccessDriver;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\Utils\Vars\InputFilter;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class InodesAccessDriver
 * @package Pydio\Access\Driver\StreamProvider\Inodes
 */
class InodesAccessDriver extends FsAccessDriver
{
    /**
     * @param ContextInterface $ctx
     * @param array $httpVars
     * @return array
     */
    public function makeSharedRepositoryOptions(ContextInterface $ctx, $httpVars){
        $file = InputFilter::decodeSecureMagic($httpVars["file"]);
        $stat = stat($ctx->getUrlBase() . $file);
        $inode = $stat["ino"];
        $mirrorRepo = $ctx->getRepository()->getContextOption($ctx, "MIRROR_REPOSITORY_ID");
        return [
            "ROOT_INODES" => $inode,
            "MIRROR_REPOSITORY_ID" => $mirrorRepo
        ];
    }
}