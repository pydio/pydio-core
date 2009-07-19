<?php
/**
 * @package info.ajaxplorer
 *
 * Copyright 2007-2009 Cyril Russo
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 *
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 *
 * The main conditions are as follow :
 * You must conspicuously and appropriately publish on each copy distributed
 * an appropriate copyright notice and disclaimer of warranty and keep intact
 * all the notices that refer to this License and to the absence of any warranty;
 * and give any other recipients of the Program a copy of the GNU Lesser General
 * Public License along with the Program.
 *
 * If you modify your copy or copies of the library or any portion of it, you may
 * distribute the resulting library provided you do so under the GNU Lesser
 * General Public License. However, programs that link to the library may be
 * licensed under terms of your choice, so long as the library itself can be changed.
 * Any translation of the GNU Lesser General Public License must be accompanied by the
 * GNU Lesser General Public License.
 *
 * If you copy or distribute the program, you must accompany it with the complete
 * corresponding machine-readable source code or with a written offer, valid for at
 * least three years, to furnish the complete corresponding machine-readable source code.
 *
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * Description : Abstract representation of an action driver. Must be implemented.
 */
                                 
require_once(INSTALL_PATH.'/server/classes/class.AbstractTest.php');

class ftpAccessTest extends AbstractTest
{
    function ftpAccessTest() { parent::AbstractTest("Remote FTP Filesystem Plugin", ""); }
    
    function doRepositoryTest($repo)
    {
    	if($repo->accessType != "ftp") return -1;
    	
        $basePath = "../../../plugins/access.ftp/";
        // Check file exists
        if (!file_exists($basePath."class.ftpAccessDriver.php")
         || !file_exists($basePath."manifest.xml"))
        { $this->failedInfo .= "Missing at least one of the plugin files (class.ftpAccessDriver.php, manifest.xml, ftpActions.xml).\nPlease reinstall from lastest release."; return FALSE; }
        
        return TRUE;    
    }
};

?>
