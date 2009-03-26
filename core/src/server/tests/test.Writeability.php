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
                                 
require_once('../classes/class.AbstractTest.php');

class Writeability extends AbstractTest
{
    function Writeability() { parent::AbstractTest("Required writeable folder", "One of the following folder should be writeable and is not : "); }
    function doTest() 
    { 
        $server = is_writable("../");
        $logs = is_writable("../logs");
        $conf = is_writable("../conf");
        $this->testedParams["[Server, logs, conf]"] = "[$server,$logs,$conf]";
        if(!$server || !$logs || !$conf){
        	$this->failedInfo .= "INSTALL_PATH/server, INSTALL_PATH/server/conf, INSTALL_PATH/server/logs";
        	return FALSE;
        }
        $this->failedLevel = "info";
        $this->failedInfo = "[$server,$logs,$conf]";
        return FALSE;
    }
};

?>
