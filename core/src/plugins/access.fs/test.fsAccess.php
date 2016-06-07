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
namespace Pydio\Tests;

use Pydio\Access\Core\Model\Repository;
use Pydio\Core\Model\Context;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class fsAccessTest extends AbstractTest
{
    public function __construct() { parent::__construct("Filesystem Plugin", ""); }

    /**
     * Test Repository
     *
     * @param \Pydio\Access\Core\Model\Repository $repo
     * @return Boolean
     */
    public function doRepositoryTest($repo)
    {
        if ($repo->accessType != 'fs' ) return -1;
        // Check the destination path
        $this->failedInfo = "";
        $safePath = $repo->getSafeOption("PATH");
        if(strstr($safePath, "AJXP_USER")!==false) {
            return TRUE;
        } // CANNOT TEST THIS CASE!
        $ctx = Context::fromGlobalServices();
        $path       = $repo->getContextOption($ctx, "PATH");
        $createOpt  = $repo->getContextOption($ctx, "CREATE");
        $create     = (($createOpt=="true"||$createOpt===true)?true:false);

        if (!$create && !@is_dir($path)) {
            $this->failedInfo .= "Selected repository path ".$path." doesn't exist, and the CREATE option is false"; return FALSE;
        }
        return TRUE;
    }

};
