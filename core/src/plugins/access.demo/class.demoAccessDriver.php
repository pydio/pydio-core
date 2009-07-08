<?php
/**
 * @package info.ajaxplorer.plugins
 * 
 * Copyright 2007-2009 Charles du Jeu
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
 * Description : The copy of FS driver but with no "write" access
 */
require_once(INSTALL_PATH."/plugins/access.fs/class.fsAccessDriver.php");

class demoAccessDriver extends fsAccessDriver 
{
	/**
	* @var Repository
	*/
	var $repository;
	
	function demoAccessDriver($driverName, $filePath, $repository, $optOptions = NULL){
		parent::fsAccessDriver($driverName, INSTALL_PATH."/plugins/access.fs/fsActions.xml", $repository);		
	}
	
	function switchAction($action, $httpVars, $fileVars){
		if(!isSet($this->actions[$action])) return;
		$errorMessage = "This is a demo, all 'write' actions are disabled!";
		switch($action)
		{			
			//------------------------------------
			//	ONLINE EDIT
			//------------------------------------
			case "edit":	
				if(isset($save) && $save==1)
				{
					$xmlBuffer .= AJXP_XMLWriter::sendMessage(null, $errorMessage, false);
				}
				else 
				{
					$this->readFile($this->getPath()."/".SystemTextEncoding::fromUTF8(Utils::securePath($_GET["file"])), "plain");
				}
				exit(0);
			break;
			case "public_url":
				print($errorMessage);
				exit(0);
			break;
			//------------------------------------
			//	COPY / MOVE
			//------------------------------------
			case "copy":
			case "move":
			case "rename":
			case "delete":
			case "mkdir":
			case "mkfile":
				return AJXP_XMLWriter::sendMessage(null, $errorMessage, false);
			break;
			
			//------------------------------------
			//	UPLOAD
			//------------------------------------	
			case "upload":
				
				$fancyLoader = false;
				foreach ($fileVars as $boxName => $boxData)
				{
					if($boxName == "Filedata") $fancyLoader = true;
				}
				if($fancyLoader)
				{
					header('HTTP/1.0 '.$errorMessage);
					die('Error '.$errorMessage);
				}
				else
				{
					print("<html><script language=\"javascript\">\n");
					print("\n if(parent.ajaxplorer.actionBar.multi_selector)parent.ajaxplorer.actionBar.multi_selector.submitNext('".str_replace("'", "\'", $errorMessage)."');");		
					print("</script></html>");
				}
				exit;
				
			break;			
			
			default:
			break;
		}

		return parent::switchAction($action, $httpVars, $fileVars);
		
	}
	    
}

?>
