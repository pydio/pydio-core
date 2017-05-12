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
namespace Pydio\Tests;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Access
 */
class ftpAccessTest extends AbstractTest
{
    /**
     * ftpAccessTest constructor.
     */
    public function __construct() { parent::__construct("Remote FTP Filesystem Plugin", ""); }

    /**
     * @param \Pydio\Access\Core\Model\Repository $repo
     * @return bool|int
     */
    public function doRepositoryTest($repo)
    {
        if($repo->accessType != "ftp") return -1;

        $basePath = AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/access.ftp/" ;
        // Check file exists
        if (!file_exists($basePath."FtpAccessDriver.php")
         || !file_exists($basePath."manifest.xml"))
        { $this->failedInfo .= "Missing at least one of the plugin files (FtpAccessDriver.php, manifest.xml, ftpActions.xml).\nPlease reinstall from lastest release."; return FALSE; }

        return TRUE;
    }
};
