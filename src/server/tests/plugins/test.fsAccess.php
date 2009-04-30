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

class fsAccessTest extends AbstractTest
{
    function fsAccessTest() { parent::AbstractTest("Filesystem Plugin", ""); }

    /**
     * Test Repository
     *
     * @param Repository $repo
     * @return Boolean
     */
    function doRepositoryTest($repo){
        if ($repo->accessType != 'fs' ) return -1;
        // Check the destination path
        $path = $repo->getOption("PATH", true);
        $createOpt = $repo->getOption("CREATE");
        $create = (($createOpt=="true"||$createOpt===true)?true:false);
        if(strstr($path, "AJXP_USER")!==false) return TRUE; // CANNOT TEST THIS CASE!        
        if (!$create && !is_dir($path))
        { 
        	$this->failedInfo .= "Selected repository path ".$path." doesn't exist, and the CREATE option is false"; return FALSE; 
        }
        else if (!$create && !is_writeable($path))
        { $this->failedInfo .= "Selected repository path ".$path." isn't writeable"; return FALSE; }
        // Do more tests here  
        return TRUE;    	
    }
    
};

?>
