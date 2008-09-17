<?php

require_once(INSTALL_PATH."/plugins/ajxp.fs/class.fsDriver.php");

class demoDriver extends fsDriver 
{
	/**
	* @var Repository
	*/
	var $repository;
	
	function demoDriver($driverName, $filePath, $repository){
		parent::fsDriver($driverName, INSTALL_PATH."/plugins/ajxp.fs/fsActions.xml", $repository);		
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
					$this->readFile($this->repository->getPath()."/".SystemTextEncoding::fromUTF8(Utils::securePath($_GET["file"])), "plain");
				}
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
