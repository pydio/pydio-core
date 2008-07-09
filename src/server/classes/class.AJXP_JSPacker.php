<?php

class AJXP_JSPacker{
	
	/**
	 * Static function for packing all js into two big files
	 */
	function pack(){
		AJXP_JSPacker::concatListAndPack(CLIENT_RESOURCES_FOLDER."/js/scriptslist.txt", 
										CLIENT_RESOURCES_FOLDER."/js/ajaxplorer.js", 
										"Normal");
		AJXP_JSPacker::concatListAndPack(CLIENT_RESOURCES_FOLDER."/css/csslist.txt", 
										CLIENT_RESOURCES_FOLDER."/css/allz.css",
										"None");
	}
	
	function concatListAndPack($src, $out, $mode){
		
		if(!is_file($src) || !is_readable($src)){
			return false;
		}
		
		// Concat List into one big string	
		$jscode = '' ;
		$handle = @fopen($src, 'r');
		if ($handle) {
		    while (!feof($handle)) {
		        $jsline = fgets($handle, 4096) ;
		        if(rtrim($jsline,"\n") != ""){
					$code = file_get_contents(INSTALL_PATH."/".CLIENT_RESOURCES_FOLDER."/".rtrim($jsline,"\n")) ;
					if ($code) $jscode .= $code ;
		        }
		    }
		    fclose($handle);
		}
		
		// Pack and write to file
		require_once("packer/class.JavaScriptPacker.php");
		$packer = new JavaScriptPacker($jscode, $mode , true, false);
		$packed = $packer->pack();
		if($mode == "None"){ // css case, hack for I.E.
			$packed = str_replace("solid#", "solid #", $packed);
		}
		@file_put_contents($out, $packed);
		
		return true;
	}
	
}

?>