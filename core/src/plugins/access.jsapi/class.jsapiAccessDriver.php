<?php
class jsapiAccessDriver extends AbstractAccessDriver{
	
	public function switchAction($action, $httpVars, $fileVars){
		
		switch ($action){
			case "get_js_source" :				
				$jsName = AJXP_Utils::decodeSecureMagic($httpVars["object_name"]);
				$jsType = $httpVars["object_type"]; // class or interface?
				$fName = "class.".strtolower($jsName).".js";
				if($jsName == "Splitter"){
					$fName = "splitter.js";
				}
				// Locate the file class.ClassName.js
				if($jsType == "class"){
					$searchLocations = array(
						CLIENT_RESOURCES_FOLDER."/js/ajaxplorer",
						CLIENT_RESOURCES_FOLDER."/js/lib",
						INSTALL_PATH."/plugins/"
					);
				}else if($jsType == "interface"){
					$searchLocations = array(
						CLIENT_RESOURCES_FOLDER."/js/ajaxplorer/interfaces",
					);
				}
				foreach ($searchLocations as $location){
					$dir_iterator = new RecursiveDirectoryIterator($location);
					$iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);
					// could use CHILD_FIRST if you so wish
					$break = false;
					foreach ($iterator as $file) {
					    if(strtolower(basename($file->getPathname())) == $fName){
					    	HTMLWriter::charsetHeader("text/plain", "utf-8");
					    	echo(file_get_contents($file->getPathname()));
					    	$break = true;
					    	break;
					    }
					}
					if($break) break;
				}
			break;
		}
		
	}
	
}
?>