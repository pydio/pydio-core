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
                                 
require_once('../../classes/class.AbstractTest.php');

class ajxp_ssh extends AbstractTest
{
    function ajxp_ssh() { parent::AbstractTest("Remote SSH Filesystem Plugin", ""); }
    
    function doRepositoryTest($repo)
    {
    	if($repo->accessType != "ssh") return -1;
    	
        $basePath = "../../../plugins/ajxp.ssh/";
        // Check file exists
        if (!file_exists($basePath."class.sshDriver.php")
         || !file_exists($basePath."class.SSHOperations.php")
         || !file_exists($basePath."manifest.xml")
         || !file_exists($basePath."showPass.php")
         || !file_exists($basePath."sshActions.xml"))
        { $this->failedInfo .= "Missing at least one of the plugin files (class.sshDriver.php, class.SSHOperations.php, manifest.xml, showPass.php, sshActions.xml).\nPlease reinstall from lastest release."; return FALSE; }
        
        // Check if showPass is executable from ssh
        $stat = stat($basePath."showPass.php");
        $mode = $stat['mode'] & 0x7FFF; // We don't care about the type
        if (is_executable($basePath.'showPass.php')
         || (($mode & 0x40) && $stat['uid'] == posix_getuid())
         || (($mode & 0x08) && $stat['gid'] == posix_getgid())
         || ($mode & 0x01))
        { 
            chmod($basePath.'showPass.php', 0555);
            if (!is_executable($basePath.'showPass.php'))
            { $this->failedInfo .= "showPass.php must be executable. Please log in on your server and set showPass.php as executable (chmod u+x showPass.php)."; return FALSE; }
        }
        
        // Check if ssh is accessible
        $handle = popen("ssh 2>&1", "r");
        $usage = fread($handle, 30);
        pclose($handle);
        if (strpos($usage, "usage") === FALSE)
        { $this->failedInfo .= "Couldn't find or execute 'ssh' on your system. Please install latest SSH client."; return FALSE; }
                                            
        return TRUE;    
    }

};

?>
