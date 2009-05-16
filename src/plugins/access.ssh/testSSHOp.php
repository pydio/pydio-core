<?php
/**
 * @package info.ajaxplorer.plugins
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
 * Description : Test the plugin installation.
 */
require_once("class.SSHOperations.php");

   $SSHOp = new SSHOperations("ssh.alwaysdata.com", "ajaxplorer", "dumbpass");
   $SSHOp->testConnection("ssh.alwaysdata.com", "ajaxplorer", "dumbpass");
   echo "<h1>Testing connection</h1>";
   var_dump($SSHOp->checkConnection())."<br>";
   
   $arr = $SSHOp->listFilesIn('/www');
   print_r($arr[0]);
   
   // Create a file on the server
//   $SSHOp->setRemoteContent('/home/ajaxplorer/www/test.html', '<html><body>Hello</body></html>');
//   $SSHOp->uploadFile("/tmp/somefile", "/home/ajaxplorer/www/somefile");
//   $SSHOp->copyFile("/home/ajaxplorer/www/Charles/retryImap.patch", "/home/ajaxplorer/www");
?>
   
