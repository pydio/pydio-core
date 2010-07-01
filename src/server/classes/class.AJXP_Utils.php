<?php
/**
 * @package info.ajaxplorer
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
 * Description : various utils methods.
 */
class AJXP_Utils
{
	
	/**
	* Performs a natural sort on the array keys. 
	* Behaves the same as ksort() with natural sorting added. 
	* 
	* @param Array $array The array to sort 
	*/  
	function natksort(&$array) {
		uksort($array, 'strnatcasecmp');
		return true;
	}

	/**
	* Performs a reverse natural sort on the array keys 
	* Behaves the same as krsort() with natural sorting added. 
	* 
	* @param Array $array The array to sort 
	*/  
	function natkrsort(&$array) {
		natksort($array);
		$array = array_reverse($array,TRUE);
		return true;
	}

	function securePath($path)
	{
		if($path == null) $path = ""; 
		//
		// REMOVE ALL "../" TENTATIVES
		//
		$dirs = explode('/', $path);
		for ($i = 0; $i < count($dirs); $i++)
		{
			if ($dirs[$i] == '.' or $dirs[$i] == '..')
			{
				$dirs[$i] = '';
			}
		}
		// rebuild safe directory string
		$path = implode('/', $dirs);
		
		//
		// REPLACE DOUBLE SLASHES
		//
		while (preg_match('/\/\//', $path)) 
		{
			$path = str_replace('//', '/', $path);
		}
		return $path;
	}
	
	public static function decodeSecureMagic($data){
		return SystemTextEncoding::fromUTF8(AJXP_Utils::securePath(SystemTextEncoding::magicDequote($data)));
	}
	
	function parseFileDataErrors($boxData)
	{
		$mess = ConfService::getMessages();
		$userfile_error = $boxData["error"];		
		$userfile_tmp_name = $boxData["tmp_name"];
		$userfile_size = $boxData["size"];
		if ($userfile_error != UPLOAD_ERR_OK)
		{
			$errorsArray = array();
			$errorsArray[UPLOAD_ERR_FORM_SIZE] = $errorsArray[UPLOAD_ERR_INI_SIZE] = array(409,"File is too big! Max is".ini_get("upload_max_filesize"));
			$errorsArray[UPLOAD_ERR_NO_FILE] = array(410,"No file found on server!");
			$errorsArray[UPLOAD_ERR_PARTIAL] = array(410,"File is partial");
			$errorsArray[UPLOAD_ERR_INI_SIZE] = array(410,"No file found on server!");
			if($userfile_error == UPLOAD_ERR_NO_FILE)
			{
				// OPERA HACK, do not display "no file found error"
				if(!ereg('Opera',$_SERVER['HTTP_USER_AGENT']))
				{
					return $errorsArray[$userfile_error];				
				}
			}
			else
			{
				return $errorsArray[$userfile_error];
			}
		}
		if ($userfile_tmp_name=="none" || $userfile_size == 0)
		{
			return array(410,$mess[31]);
		}
		return null;
	}
	
	public static function parseApplicationGetParameters($parameters, &$output, &$session){
		$output["EXT_REP"] = "/";
		
		if(isSet($parameters["repository_id"]) && isSet($parameters["folder"])){
			$loggedUser = AuthService::getLoggedUser();
			require_once("server/classes/class.SystemTextEncoding.php");
			if(AuthService::usersEnabled()){
				if($loggedUser!= null && $loggedUser->canSwitchTo($parameters["repository_id"])){			
					$output["EXT_REP"] = SystemTextEncoding::toUTF8(urldecode($parameters["folder"]));
					$loggedUser->setArrayPref("history", "last_repository", $parameters["repository_id"]);
					$loggedUser->setArrayPref("history", $parameters["repository_id"], $parameters["folder"]);
					$loggedUser->save();
				}else{
					$session["PENDING_REPOSITORY_ID"] = $parameters["repository_id"];
					$session["PENDING_FOLDER"] = $parameters["folder"];
				}
			}else{
				ConfService::switchRootDir($parameters["repository_id"]);
				$output["EXT_REP"] = SystemTextEncoding::toUTF8(urldecode($parameters["folder"]));
			}
		}
		
		
		if(isSet($parameters["skipDebug"])) {
			ConfService::setConf("JS_DEBUG", false);
		}
		if(ConfService::getConf("JS_DEBUG") && isSet($parameters["compile"])){
			require_once(SERVER_RESOURCES_FOLDER."/class.AJXP_JSPacker.php");
			AJXP_JSPacker::pack();
		}		
		if(ConfService::getConf("JS_DEBUG") && isSet($parameters["update_i18n"])){
			AJXP_Utils::updateI18nFiles();
		}
		
		if(isSet($parameters["external_selector_type"])){
			$output["SELECTOR_DATA"] = array("type" => $type, "data" => $data);
		}
		
	}
	
	function mergeArrays($t1,$t2)
	{
		$liste = array();
		$tab1=$t1; $tab2=$t2;
		if(is_array($tab1)) {while (list($cle,$val) = each($tab1)) {$liste[$cle]=$val;}}
		if(is_array($tab2)) {while (list($cle,$val) = each($tab2)) {$liste[$cle]=$val;}}
		return $liste;
	}
	
	function removeWinReturn($fileContent)
	{
		$fileContent = str_replace(chr(10), "", $fileContent);
		$fileContent = str_replace(chr(13), "", $fileContent);
		return $fileContent;
	}
	
	function tipsandtricks()
	{
		$tips = array();
		$tips[] = "DoubleClick in the list to directly download a file or to open a folder.";
		$tips[] = "When the 'Edit' button is enabled (on text files), you can directly edit the selected file online.";
		$tips[] = "Type directly a folder URL in the location bar then hit 'ENTER' to go to a given folder.";
		$tips[] = "Use MAJ+Click and CTRL+Click to perform multiple selections in the list.";
		$tips[] = "Use the Bookmark button to save your frequently accessed locations in the bookmark bar.";
		$tips[] = "Use the TAB button to navigate through the main panels (tree, list, location bar).";
		$tips[] = "Use the 'u' key to go to the parent directory.";
		$tips[] = "Use the 'h' key to refresh current listing.";
		$tips[] = "Use the 'b' key to bookmark current location to your bookmark bar.";
		$tips[] = "Use the 'l' key to open Upload Form.";
		$tips[] = "Use the 'd' key to create a new directory in this folder.";
		$tips[] = "Use the 'f' key to create a new file in this folder.";
		$tips[] = "Use the 'r' key to rename a file.";
		$tips[] = "Use the 'c' key to copy one or more file or folders to a different folder.";
		$tips[] = "Use the 'm' key to move one or more file or folders to a different folder.";
		$tips[] = "Use the 's' key to delete one or more file or folders.";
		$tips[] = "Use the 'e' key to edit a file or view an image.";
		$tips[] = "Use the 'o' key to download a file to your hard drive.";
		return $tips[array_rand($tips, 1)];
	}
	
	function processFileName($fileName)
	{
		$max_caracteres = ConfService::getConf("MAX_CHAR");
		// Don't allow those chars : ' " & , ; / \ ` < > : * ? | ! + ^ 
		$fileName=SystemTextEncoding::magicDequote($fileName);
		// Unless I'm mistaken, ' is a valid char for a file name (under both Linux and Windows).
		// I've found this regular expression for Windows file name validation, not sure how it applies for linux :
		// ^[^\\\./:\*\?\"<>\|]{1}[^\\/:\*\?\"<>\|]{0,254}$   This reg ex remove ^ \ . / : * ? " < > | as the first char, and (same thing but . for any other char), and it limits to 254 chars (could use max_caracteres instead)
		// Anyway, here is the corrected version of the big str_replace calls below that doesn't kill UTF8 encoding
		$fileNameTmp=preg_replace("/[\",;\/`<>:\*\|\?!\^]/", "", $fileName);
		return substr($fileNameTmp, 0, $max_caracteres);
	}
	
	function mimetype($fileName,$mode, $isDir)
	{
		$mess = ConfService::getMessages();
		$fileName = strtolower($fileName);
		if($isDir){$image="folder.png";$typeName=$mess[8];}
		else if(preg_match("/\.mid$/",$fileName)){$image="midi.png";$typeName=$mess[9];}
		else if(preg_match("/\.txt$/",$fileName)){$image="txt2.png";$typeName=$mess[10];}
		else if(preg_match("/\.sql$/",$fileName)){$image="txt2.png";$typeName=$mess[10];}
		else if(preg_match("/\.js$/",$fileName)){$image="javascript.png";$typeName=$mess[11];}
		else if(preg_match("/\.gif$/",$fileName)){$image="image.png";$typeName=$mess[12];}
		else if(preg_match("/\.jpg$/",$fileName)){$image="image.png";$typeName=$mess[13];}
		else if(preg_match("/\.html$/",$fileName)){$image="html.png";$typeName=$mess[14];}
		else if(preg_match("/\.htm$/",$fileName)){$image="html.png";$typeName=$mess[15];}
		else if(preg_match("/\.rar$/",$fileName)){$image="archive.png";$typeName=$mess[60];}
		else if(preg_match("/\.gz$/",$fileName)){$image="zip.png";$typeName=$mess[61];}
		else if(preg_match("/\.tgz$/",$fileName)){$image="archive.png";$typeName=$mess[61];}
		else if(preg_match("/\.z$/",$fileName)){$image="archive.png";$typeName=$mess[61];}
		else if(preg_match("/\.ra$/",$fileName)){$image="video.png";$typeName=$mess[16];}
		else if(preg_match("/\.ram$/",$fileName)){$image="video.png";$typeName=$mess[17];}
		else if(preg_match("/\.rm$/",$fileName)){$image="video.png";$typeName=$mess[17];}
		else if(preg_match("/\.pl$/",$fileName)){$image="source_pl.png";$typeName=$mess[18];}
		else if(preg_match("/\.zip$/",$fileName)){$image="zip.png";$typeName=$mess[19];}
		else if(preg_match("/\.wav$/",$fileName)){$image="sound.png";$typeName=$mess[20];}
		else if(preg_match("/\.php$/",$fileName)){$image="php.png";$typeName=$mess[21];}
		else if(preg_match("/\.php3$/",$fileName)){$image="php.png";$typeName=$mess[22];}
		else if(preg_match("/\.phtml$/",$fileName)){$image="php.png";$typeName=$mess[22];}
		else if(preg_match("/\.exe$/",$fileName)){$image="exe.png";$typeName=$mess[50];}
		else if(preg_match("/\.bmp$/",$fileName)){$image="image.png";$typeName=$mess[56];}
		else if(preg_match("/\.png$/",$fileName)){$image="image.png";$typeName=$mess[57];}
		else if(preg_match("/\.css$/",$fileName)){$image="css.png";$typeName=$mess[58];}
		else if(preg_match("/\.mp3$/",$fileName)){$image="sound.png";$typeName=$mess[59];}
		else if(preg_match("/\.xls$/",$fileName)){$image="spreadsheet.png";$typeName=$mess[64];}
		else if(preg_match("/\.doc$/",$fileName)){$image="document.png";$typeName=$mess[65];}
		else if(preg_match("/\.pdf$/",$fileName)){$image="pdf.png";$typeName=$mess[79];}
		else if(preg_match("/\.mov$/",$fileName)){$image="video.png";$typeName=$mess[80];}
		else if(preg_match("/\.avi$/",$fileName)){$image="video.png";$typeName=$mess[81];}
		else if(preg_match("/\.mpg$/",$fileName)){$image="video.png";$typeName=$mess[82];}
		else if(preg_match("/\.mpeg$/",$fileName)){$image="video.png";$typeName=$mess[83];}
		else if(preg_match("/\.wmv$/",$fileName)){$image="video.png";$typeName=$mess[81];}
		else if(preg_match("/\.swf$/",$fileName)){$image="flash.png";$typeName=$mess[91];}
		else if(preg_match("/\.flv$/",$fileName)){$image="flash.png";$typeName=$mess[91];}
		else {$image="mime_empty.png";$typeName=$mess[23];}
		if($mode=="image"){return $image;} else {return $typeName;}
	}
		
	function getAjxpMimes($keyword){
		if($keyword == "editable"){
			return "txt,sql,php,php3,phtml,htm,html,cgi,pl,js,css,inc,xml,xsl,java";
		}else if($keyword == "image"){
			return "png,bmp,jpg,jpeg,gif";
		}else if($keyword == "audio"){
			return "mp3";
		}else if($keyword == "zip"){
			if(ConfService::zipEnabled()){
				return "zip";
			}else{
				return "none_allowed";
			}
		}
		return "";
	}
		
	function is_image($fileName)
	{
		if(preg_match("/\.png$|\.bmp$|\.jpg$|\.jpeg$|\.gif$/i",$fileName)){
			return 1;
		}
		return 0;
	}
	
	function is_mp3($fileName)
	{
		if(preg_match("/\.mp3$/i",$fileName)) return 1;
		return 0;
	}
	
	function getImageMimeType($fileName)
	{
		if(preg_match("/\.jpg$|\.jpeg$/i",$fileName)){return "image/jpeg";}
		else if(preg_match("/\.png$/i",$fileName)){return "image/png";}	
		else if(preg_match("/\.bmp$/i",$fileName)){return "image/bmp";}	
		else if(preg_match("/\.gif$/i",$fileName)){return "image/gif";}	
	}
	
	function roundSize($filesize)
	{
		$mess = ConfService::getMessages();
		$size_unit = $mess["byte_unit_symbol"];
		if($filesize < 0){
			$filesize = sprintf("%u", $filesize);
		}
		if ($filesize >= 1073741824) {$filesize = round($filesize / 1073741824 * 100) / 100 . " G".$size_unit;}
		elseif ($filesize >= 1048576) {$filesize = round($filesize / 1048576 * 100) / 100 . " M".$size_unit;}
		elseif ($filesize >= 1024) {$filesize = round($filesize / 1024 * 100) / 100 . " K".$size_unit;}
		else {$filesize = $filesize . " ".$size_unit;}
		if($filesize==0) {$filesize="-";}
		return $filesize;
	}
		
	function isHidden($fileName){
		return (substr($fileName,0,1) == ".");
	}
	
	function isBrowsableArchive($fileName){
		return preg_match("/\.zip$/",$fileName);
	}
	
	/**
	 * Convert a shorthand byte value from a PHP configuration directive to an integer value
	 * @param    string   $value
	 * @return   int
	 */
	function convertBytes( $value ) 
	{
	    if ( is_numeric( $value ) ) 
	    {
	        return $value;
	    } 
	    else 
	    {
	        $value_length = strlen( $value );
	        $qty = substr( $value, 0, $value_length - 1 );
	        $unit = strtolower( substr( $value, $value_length - 1 ) );
	        switch ( $unit ) 
	        {
	            case 'k':
	                $qty *= 1024;
	                break;
	            case 'm':
	                $qty *= 1048576;
	                break;
	            case 'g':
	                $qty *= 1073741824;
	                break;
	        }
	        return $qty;
	    }
	}

	function xmlEntities($string, $toUtf8=false){
		$xmlSafe = str_replace(array("&", "<",">"), array("&amp;", "&lt;","&gt;"), $string);
		if($toUtf8){
			return SystemTextEncoding::toUTF8($xmlSafe);
		}else{
			return $xmlSafe;
		}
	}

	function updateI18nFiles(){
		include(INSTALL_PATH."/".CLIENT_RESOURCES_FOLDER."/i18n/en.php");
		$reference = $mess;
		$languages = ConfService::listAvailableLanguages();
		foreach ($languages as $key=>$value){
			$filename = INSTALL_PATH."/".CLIENT_RESOURCES_FOLDER."/i18n/".$key.".php";
			include($filename);
			$missing = array();
			foreach ($reference as $messKey=>$message){
				if(!array_key_exists($messKey, $mess)){
					$missing[] = "\"$messKey\" => \"$message\",";
				}
			}
			//print_r($missing);
			if(count($missing)){
				$header = array();
				$currentMessages = array();
				$footer = array();
				$fileLines = file($filename);
				foreach ($fileLines as $line){
					if(strstr($line, "\"") !== false){
						$currentMessages[] = trim($line);
					}else{
						if(!count($currentMessages)){
							$header[] = trim($line);
						}else{
							$footer[] = trim($line);
						}
					}
				}
				$currentMessages = array_merge($header, $currentMessages, $missing, $footer);
				file_put_contents($filename, join("\n", $currentMessages));
			}
		}
	}
	
	function testResultsToTable($outputArray, $testedParams, $showSkipLink = true){
		$style = '
		<style>
		body {
		background-color:#f1f1ef;
		margin:0;
		padding:20;
		}
		* {font-family:arial, sans-serif;font-size:11px;color:#006}
		h1 {font-size: 20px; color:black}
		thead tr{background-color: #ccc; font-weight:bold;}
		tr.dump{background-color: #ee9;}
		tr.passed{background-color: #ae9;}
		tr.failed{background-color: #ea9;}
		tr.warning{background-color: #f90;}
		td {padding: 3px 6px;}
		td.col{font-weight: bold;}
		</style>
		';
		$htmlHead = "<html><head><title>AjaXplorer : Diagnostic Tool</title>$style</head><body><h1>AjaXplorer Diagnostic Tool</h1>";
		if($showSkipLink){
			$htmlHead .= "<p>The diagnostic tool detected some errors or warning : you are likely to have problems running AjaXplorer!</p>";
		}
		$html = "<table width='700' border='0' cellpadding='0' cellspacing='1'><thead><tr><td>Name</td><td>Result</td><td>Info</td></tr></thead>"; 
		$dumpRows = "";
		$passedRows = "";
		$warnRows = "";
		$errs = $warns = 0;
		foreach($outputArray as $item)
		{
		    // A test is output only if it hasn't succeeded (doText returned FALSE)
		    $result = $item["result"] ? "passed" : ($item["level"] == "info" ? "dump" : ($item["level"]=="warning"? "warning":"failed"));
		    $success = $result == "passed";    
		    $row = "<tr class='$result'><td class='col'>".$item["name"]."</td><td>".$result."&nbsp;</td><td>".(!$success ? $item["info"] : "")."&nbsp;</td></tr>";
		    if($result == "dump"){
		    	$dumpRows .= $row;
		    }else if($result == "passed"){
		    	$passedRows .= $row;
		    }else if($item["level"] == "warning"){
		    	$warnRows .= $row;
		    	$warns ++;
		    }else{
		    	$html .= $row;
		    	$errs ++;
		    }
		}
		$html .= $warnRows;
		$html .= $passedRows;
		$html .= $dumpRows;
		$html .= "</table>";
		if($showSkipLink){
			if(!$errs){
				$htmlHead .= "<p>STATUS : You have some warning, but no fatal error, AjaXplorer should run ok, <a href='index.php?ignore_tests=true'>click here to continue to AjaXplorer!</a> (this test won't be launched anymore)</p>";
			}else{
				$htmlHead .= "<p>STATUS : You have some errors that may prevent AjaXplorer from running. Please check the red lines to see what action you should do. If you are confident enough and know that your usage of AjaXplorer does not need these errors to fixed, <a href='index.php?ignore_tests=true'>continue here to Ajaxplorer!.</a></p>";
			}
		}
		$html.="</body></html>";
		return $htmlHead.nl2br($html);
	}
	
	function runTests(&$outputArray, &$testedParams){
		// At first, list folder in the tests subfolder
		chdir(INSTALL_PATH.'/server/tests');
		$files = glob('*.php'); 
		
		$outputArray = array();
		$testedParams = array();
		$passed = true;
		foreach($files as $file)
		{
		    require_once($file);
		    // Then create the test class
		    $testName = str_replace(".php", "", substr($file, 5));
		    $class = new $testName();
		    
		    $result = $class->doTest();
		    if(!$result && $class->failedLevel != "info") $passed = false;
		    $outputArray[] = array(
		    	"name"=>$class->name, 
		    	"result"=>$result, 
		    	"level"=>$class->failedLevel, 
		    	"info"=>$class->failedInfo); 
		   	if(count($class->testedParams)){
			    $testedParams = array_merge($testedParams, $class->testedParams);
		   	}
		}
		
        // PREPARE REPOSITORY LISTS
        $repoList = array();
        require_once("../classes/class.ConfService.php");
        require_once("../classes/class.Repository.php");
        include("../conf/conf.php");
        foreach($REPOSITORIES as $index => $repo){
            $repoList[] = ConfService::createRepositoryFromArray($index, $repo);
        }        
        // Try with the serialized repositories
        if(is_file("../conf/repo.ser")){
            $fileLines = file("../conf/repo.ser");
            $repos = unserialize($fileLines[0]);
            $repoList = array_merge($repoList, $repos);
        }
		
		// NOW TRY THE PLUGIN TESTS
		chdir(INSTALL_PATH.'/server/tests/plugins');
		$files = glob('*.php'); 
		foreach($files as $file)
		{
		    require_once($file);
		    // Then create the test class
		    $testName = str_replace(".php", "", substr($file, 5))."Test";
		    $class = new $testName();
		    foreach ($repoList as $repository){
			    $result = $class->doRepositoryTest($repository);
			    if($result === false || $result === true){			    	
				    if(!$result && $class->failedLevel != "info") $passed = false;
				    $outputArray[] = array(
				    	"name"=>$class->name . "\n Testing repository : ".$repository->getDisplay(), 
				    	"result"=>$result, 
				    	"level"=>$class->failedLevel, 
				    	"info"=>$class->failedInfo); 				    
				   	if(count($class->testedParams)){
					    $testedParams = array_merge($testedParams, $class->testedParams);
				   	}
			    }
		    }
		}
		
		return $passed;
	}	
	
	function testResultsToFile($outputArray, $testedParams){
		ob_start();
		echo '$diagResults = ';
		var_export($testedParams);
		echo ';';
		echo '$outputArray = ';
		var_export($outputArray);
		echo ';';
		$content = '<?php '.ob_get_contents().' ?>';
		ob_end_clean();
		//print_r($content);
		file_put_contents(TESTS_RESULT_FILE, $content);		
	}
	
	/**
	 * Load an array stored serialized inside a file.
	 *
	 * @param String $filePath Full path to the file
	 * @return Array
	 */
	function loadSerialFile($filePath){
		$filePath = str_replace("AJXP_INSTALL_PATH", INSTALL_PATH, $filePath);
		$result = array();
		if(is_file($filePath))
		{
			$fileLines = file($filePath);
			$result = unserialize($fileLines[0]);
		}
		return $result;
	}
	
	/**
	 * Stores an Array as a serialized string inside a file.
	 *
	 * @param String $filePath Full path to the file
	 * @param Array $value The value to store
	 * @param Boolean $createDir Whether to create the parent folder or not, if it does not exist.
	 */
	function saveSerialFile($filePath, $value, $createDir=true){
		$filePath = str_replace("AJXP_INSTALL_PATH", INSTALL_PATH, $filePath);
		if($createDir && !is_dir(dirname($filePath))) {			
			if(!is_writeable(dirname(dirname($filePath)))){
				die("Cannot write into ".dirname(dirname($filePath)));
			}
			mkdir(dirname($filePath));
		}
		$fp = fopen($filePath, "w");
		fwrite($fp, serialize($value));
		fclose($fp);
	}
	
	public static function userAgentIsMobile(){
		$isMobile = false;
		
		$op = strtolower($_SERVER['HTTP_X_OPERAMINI_PHONE'] OR "");
		$ua = strtolower($_SERVER['HTTP_USER_AGENT']);
		$ac = strtolower($_SERVER['HTTP_ACCEPT']);		
		$isMobile = strpos($ac, 'application/vnd.wap.xhtml+xml') !== false
		        || $op != ''
		        || strpos($ua, 'sony') !== false 
		        || strpos($ua, 'symbian') !== false 
		        || strpos($ua, 'nokia') !== false 
		        || strpos($ua, 'samsung') !== false 
		        || strpos($ua, 'mobile') !== false
		        || strpos($ua, 'windows ce') !== false
		        || strpos($ua, 'epoc') !== false
		        || strpos($ua, 'opera mini') !== false
		        || strpos($ua, 'nitro') !== false
		        || strpos($ua, 'j2me') !== false
		        || strpos($ua, 'midp-') !== false
		        || strpos($ua, 'cldc-') !== false
		        || strpos($ua, 'netfront') !== false
		        || strpos($ua, 'mot') !== false
		        || strpos($ua, 'up.browser') !== false
		        || strpos($ua, 'up.link') !== false
		        || strpos($ua, 'audiovox') !== false
		        || strpos($ua, 'blackberry') !== false
		        || strpos($ua, 'ericsson,') !== false
		        || strpos($ua, 'panasonic') !== false
		        || strpos($ua, 'philips') !== false
		        || strpos($ua, 'sanyo') !== false
		        || strpos($ua, 'sharp') !== false
		        || strpos($ua, 'sie-') !== false
		        || strpos($ua, 'portalmmm') !== false
		        || strpos($ua, 'blazer') !== false
		        || strpos($ua, 'avantgo') !== false
		        || strpos($ua, 'danger') !== false
		        || strpos($ua, 'palm') !== false
		        || strpos($ua, 'series60') !== false
		        || strpos($ua, 'palmsource') !== false
		        || strpos($ua, 'pocketpc') !== false
		        || strpos($ua, 'smartphone') !== false
		        || strpos($ua, 'rover') !== false
		        || strpos($ua, 'ipaq') !== false
		        || strpos($ua, 'au-mic,') !== false
		        || strpos($ua, 'alcatel') !== false
		        || strpos($ua, 'ericy') !== false
		        || strpos($ua, 'up.link') !== false
		        || strpos($ua, 'vodafone/') !== false
		        || strpos($ua, 'wap1.') !== false
		        || strpos($ua, 'wap2.') !== false;
	/*
	      		$isBot = false;		
	      		$ip = $_SERVER['REMOTE_ADDR'];
	      		
		        $isBot =  $ip == '66.249.65.39' 
		        || strpos($ua, 'googlebot') !== false 
		        || strpos($ua, 'mediapartners') !== false 
		        || strpos($ua, 'yahooysmcm') !== false 
		        || strpos($ua, 'baiduspider') !== false
		        || strpos($ua, 'msnbot') !== false
		        || strpos($ua, 'slurp') !== false
		        || strpos($ua, 'ask') !== false
		        || strpos($ua, 'teoma') !== false
		        || strpos($ua, 'spider') !== false 
		        || strpos($ua, 'heritrix') !== false 
		        || strpos($ua, 'attentio') !== false 
		        || strpos($ua, 'twiceler') !== false 
		        || strpos($ua, 'irlbot') !== false 
		        || strpos($ua, 'fast crawler') !== false                        
		        || strpos($ua, 'fastmobilecrawl') !== false 
		        || strpos($ua, 'jumpbot') !== false
		        || strpos($ua, 'googlebot-mobile') !== false
		        || strpos($ua, 'yahooseeker') !== false
		        || strpos($ua, 'motionbot') !== false
		        || strpos($ua, 'mediobot') !== false
		        || strpos($ua, 'chtml generic') !== false
		        || strpos($ua, 'nokia6230i/. fast crawler') !== false;
	*/
		return $isMobile;
	}	
		
}

?>