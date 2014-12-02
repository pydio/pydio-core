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

require_once(AJXP_BIN_FOLDER . '/class.AbstractTest.php');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class fsAccessTest extends AbstractTest
{
    public function fsAccessTest() { parent::AbstractTest("Filesystem Plugin", ""); }

    /**
     * Test Repository
     *
     * @param Repository $repo
     * @return Boolean
     */
    public function doRepositoryTest($repo)
    {
        if ($repo->accessType != 'fs' ) return -1;
        // Check the destination path
        $this->failedInfo = "";
        $safePath = $repo->getOption("PATH", true);
        if(strstr($safePath, "AJXP_USER")!==false) return TRUE; // CANNOT TEST THIS CASE!
        $path = $repo->getOption("PATH", false);
        $createOpt = $repo->getOption("CREATE");
        $create = (($createOpt=="true"||$createOpt===true)?true:false);
        if (!$create && !@is_dir($path)) {
            $this->failedInfo .= "Selected repository path ".$path." doesn't exist, and the CREATE option is false"; return FALSE;
        }/* else if ($create && !is_writeable($path)) {
            $this->failedInfo .= "Selected repository path ".$path." isn't writeable"; return FALSE;
        }*/
        // Do more tests here
        return TRUE;
    }

};
