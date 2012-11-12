<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');
                                 
require_once(AJXP_BIN_FOLDER . '/class.AbstractTest.php');

class ftpAccessTest extends AbstractTest
{
    function ftpAccessTest() { parent::AbstractTest("Remote FTP Filesystem Plugin", ""); }
    
    function doRepositoryTest($repo)
    {
    	if($repo->accessType != "ftp") return -1;
    	
        $basePath = AJXP_INSTALL_PATH."/".AJXP_PLUGINS_FOLDER."/access.ftp/" ;
        // Check file exists
        if (!file_exists($basePath."class.ftpAccessDriver.php")
         || !file_exists($basePath."manifest.xml"))
        { $this->failedInfo .= "Missing at least one of the plugin files (class.ftpAccessDriver.php, manifest.xml, ftpActions.xml).\nPlease reinstall from lastest release."; return FALSE; }
        
        return TRUE;    
    }
};

?>
