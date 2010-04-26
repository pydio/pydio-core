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
 * Description : Abstract representation of an action driver. Must be implemented.
 */
class AbstractAccessDriver extends AJXP_Plugin {
	
	/**
	* @var Repository
	*/
	var $repository;
	var $driverType = "access";
		
	public function init($repository, $options = null){
		//$this->loadActionsFromManifest();
		parent::init($options);
		$this->repository = $repository;
	}
	
	function initRepository(){
		// To be implemented by subclasses
	}
	
	
	function accessPreprocess($actionName, &$httpVars, &$filesVar)
	{
		if($actionName == "cross_copy"){
			$this->crossRepositoryCopy($httpVars);
			return ;
		}
		if($actionName == "ls"){
			// UPWARD COMPATIBILTY
			if(isSet($httpVars["options"])){
				if($httpVars["options"] == "al") $httpVars["mode"] = "file_list";
				else if($httpVars["options"] == "a") $httpVars["mode"] = "search";
				else if($httpVars["options"] == "d") $httpVars["skipZip"] = "true";
				// skip "complete" mode that was in fact quite the same as standard tree listing (dz)
			}
			if(!isSet($httpVars["skip_history"])){
				if(AuthService::usersEnabled() && AuthService::getLoggedUser()!=null){
					$user = AuthService::getLoggedUser();
					$user->setArrayPref("history", $this->repository->getId(), (isSet($httpVars["dir"])?$httpVars["dir"]:"/"));
					$user->save();
				}
			}
		}
	}

	protected function parseSpecificContributions(&$contribNode){
		parent::parseSpecificContributions($contribNode);
		if(isSet($this->actions["public_url"]) && !defined('PUBLIC_DOWNLOAD_FOLDER') || !is_dir(PUBLIC_DOWNLOAD_FOLDER) || !is_writable(PUBLIC_DOWNLOAD_FOLDER)){
			unset($this->actions["public_url"]);
		}		
		if($this->detectStreamWrapper() !== false){
			$this->actions["cross_copy"] = array();
		}
	}
	
	public function detectStreamWrapper($register = false){
		$files = $this->xPath->query("class_stream_wrapper");
		if(!$files->length) return false;
		$streamData = $this->nodeAttrToHash($files->item(0));
		if(!is_file(INSTALL_PATH."/".$streamData["filename"])){
			return false;
		}
		include_once(INSTALL_PATH."/".$streamData["filename"]);
		if(!class_exists($streamData["classname"])){
			return false;
		}
		if($register){
			if(!in_array($streamData["protocol"], stream_get_wrappers())){
				stream_wrapper_register($streamData["protocol"], $streamData["classname"]);
			}
		}
		return $streamData;
	}
	    
    /** Cypher the publiclet object data and write to disk.
        @param $data The publiclet data array to write 
                     The data array must have the following keys:
                     - DRIVER      The driver used to get the file's content      
                     - OPTIONS     The driver options to be successfully constructed (usually, the user and password)
                     - FILE_PATH   The path to the file's content
                     - PASSWORD    If set, the written publiclet will ask for this password before sending the content
                     - ACTION      If set, action to perform
                     - USER        If set, the AJXP user 
                     - EXPIRE_TIME If set, the publiclet will deny downloading after this time, and probably self destruct.
        @return the URL to the downloaded file
    */
    function writePubliclet($data)
    {
    	if(!defined('PUBLIC_DOWNLOAD_FOLDER') || !is_dir(PUBLIC_DOWNLOAD_FOLDER)){
    		return "Public URL folder does not exist!";
    	}
    	if($data["PASSWORD"] && !is_file(PUBLIC_DOWNLOAD_FOLDER."/allz.css")){    		
    		@copy(INSTALL_PATH."/".AJXP_THEME_FOLDER."/css/allz.css", PUBLIC_DOWNLOAD_FOLDER."/allz.css");
    		@copy(INSTALL_PATH."/".AJXP_THEME_FOLDER."/images/actions/22/dialog_ok_apply.png", PUBLIC_DOWNLOAD_FOLDER."/dialog_ok_apply.png");
    		@copy(INSTALL_PATH."/".AJXP_THEME_FOLDER."/images/actions/16/public_url.png", PUBLIC_DOWNLOAD_FOLDER."/dialog_ok_apply.png");
    	}
        $data["PLUGIN_ID"] = $this->id;
        $data["BASE_DIR"] = $this->baseDir;
        $data["REPOSITORY"] = $this->repository;
        // Force expanded path in publiclet
        $data["REPOSITORY"]->addOption("PATH", $this->repository->getOption("PATH"));
        if ($data["ACTION"] == "") $data["ACTION"] = "download";
        // Create a random key
        $data["FINAL_KEY"] = md5(mt_rand().time());
        // Cypher the data with a random key
        $outputData = serialize($data);
        // Hash the data to make sure it wasn't modified
        $hash = md5($outputData);
        // The initialisation vector is only required to avoid a warning, as ECB ignore IV
        $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
        // We have encoded as base64 so if we need to store the result in a database, it can be stored in text column
        $outputData = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $hash, $outputData, MCRYPT_MODE_ECB, $iv));
        // Okay, write the file:
        $fileData = "<"."?"."php \n".
        '   require_once("'.str_replace("\\", "/", INSTALL_PATH).'/publicLet.inc.php"); '."\n".
        '   $id = str_replace(".php", "", basename(__FILE__)); '."\n". // Not using "" as php would replace $ inside
        '   $cypheredData = base64_decode("'.$outputData.'"); '."\n".
        '   $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND); '."\n".
        '   $inputData = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $id, $cypheredData, MCRYPT_MODE_ECB, $iv));  '."\n".
        '   if (md5($inputData) != $id) { header("HTTP/1.0 401 Not allowed, script was modified"); exit(); } '."\n".
        '   // Ok extract the data '."\n".
        '   $data = unserialize($inputData); AbstractAccessDriver::loadPubliclet($data); ?'.'>';
        if (@file_put_contents(PUBLIC_DOWNLOAD_FOLDER."/".$hash.".php", $fileData) === FALSE){
            return "Can't write to PUBLIC URL";
        }
        if(defined('PUBLIC_DOWNLOAD_URL') && PUBLIC_DOWNLOAD_URL != ""){
        	return rtrim(PUBLIC_DOWNLOAD_URL, "/")."/".$hash.".php";
        }else{
	        $http_mode = (!empty($_SERVER['HTTPS'])) ? 'https://' : 'http://';
	        $fullUrl = $http_mode . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);    
	        return str_replace("\\", "/", $fullUrl.rtrim(str_replace(INSTALL_PATH, "", PUBLIC_DOWNLOAD_FOLDER), "/")."/".$hash.".php");
        }
    }

    /** Load a uncyphered publiclet */
    function loadPubliclet($data)
    {
        // create driver from $data
        $className = $data["DRIVER"]."AccessDriver";
        if ($data["EXPIRE_TIME"] && time() > $data["EXPIRE_TIME"])
        {
            // Remove the publiclet, it's done
            if (strstr(PUBLIC_DOWNLOAD_FOLDER, $_SERVER["SCRIPT_FILENAME"]) !== FALSE)
                unlink($_SERVER["SCRIPT_FILENAME"]);
            
            echo "Link is expired, sorry.";
            exit();
        }
        // Check password
        if (strlen($data["PASSWORD"]))
        {
            if ($_POST['password'] != $data["PASSWORD"])
            {            	
                echo "<html><head><link rel='stylesheet' type='text/css' href='allz.css'/></head><body><form action='' method='post'><div class='dialogBox' style='display:block;width:20%;left:40%;'><div class='dialogTitle'><img width='16' height='16' align='top' src='public_url.png'>&nbsp;AjaXplorer Public Download</div><div class='dialogContent'>A password is required for this download :<br><input type='password' name='password' style='width:100%;'><br><div class='dialogButtons'><input width='22' height='22' type='image' name='ok' src='dialog_ok_apply.png' title='Download' class='dialogButton'></div></div></div></form></body></html>";
                exit(1);
            }
        }
        $filePath = INSTALL_PATH."/plugins/access.".$data["DRIVER"]."/class.".$className.".php";
        if(!is_file($filePath)){
                die("Warning, cannot find driver for conf storage! ($name, $filePath)");
        }
        require_once($filePath);
        $driver = new $className($data["PLUGIN_ID"], $data["BASE_DIR"]);
        $driver->loadManifest();
        $driver->init($data["REPOSITORY"], $data["OPTIONS"]);
        ConfService::setRepository($data["REPOSITORY"]);
        $driver->initRepository();
        $driver->switchAction($data["ACTION"], array("file"=>$data["FILE_PATH"]), "");
    }

    /** Create a publiclet object, that will be saved in PUBLIC_DOWNLOAD_FOLDER
        Typically, the class will simply create a data array, and call return writePubliclet($data)
        @param $filePath The path to the file to share
        @return The full public URL to the publiclet.
    */
    function makePubliclet($filePath) {}
    
    function crossRepositoryCopy($httpVars){
    	
    	ConfService::detectRepositoryStreams(true);
    	$mess = ConfService::getMessages();
		$selection = new UserSelection();
		$selection->initFromHttpVars($httpVars);
    	$files = $selection->getFiles();
    	
    	$accessType = $this->repository->getAccessType();    	
    	$repositoryId = $this->repository->getId();
    	$origStreamURL = "ajxp.$accessType://$repositoryId";    	
    	
    	$destRepoId = $httpVars["dest_repository_id"];
    	$destRepoObject = ConfService::getRepositoryById($destRepoId);
    	$destRepoAccess = $destRepoObject->getAccessType();
    	$destStreamURL = "ajxp.$destRepoAccess://$destRepoId";
    	
    	// Check rights
    	if(AuthService::usersEnabled()){
	    	$loggedUser = AuthService::getLoggedUser();
	    	if(!$loggedUser->canRead($repositoryId) || !$loggedUser->canWrite($destRepoId)){
	    		AJXP_XMLWriter::header();
	    		AJXP_XMLWriter::sendMessage(null, "You do not have the right to access one of the repositories!");
	    		AJXP_XMLWriter::close();
	    		exit(1);
	    	}
    	}
    	
    	$messages = array();
    	foreach ($files as $file){
    		$origFile = $origStreamURL.$file;
    		$destFile = $destStreamURL.$httpVars["dest"]."/".basename($file);    		
			$origHandler = fopen($origFile, "r");
			$destHandler = fopen($destFile, "w");
			if($origHandler === false || $destHandler === false) {
				$errorMessages[] = AJXP_XMLWriter::sendMessage(null, $mess[114]." ($origFile to $destFile)", false);
				continue;
			}
			while(!feof($origHandler)){
				fwrite($destHandler, fread($origHandler, 4096));
			}
			fflush($destHandler);
			fclose($origHandler); 
			fclose($destHandler);			
			$messages[] = $mess[34]." ".SystemTextEncoding::toUTF8(basename($origFile))." ".$mess[73]." ".SystemTextEncoding::toUTF8($destFile);
    	}
    	AJXP_XMLWriter::header();    	
    	if(count($errorMessages)){
    		AJXP_XMLWriter::sendMessage(null, join("\n", $errorMessages), true);
    	}
    	AJXP_XMLWriter::sendMessage(join("\n", $messages), null, true);
    	AJXP_XMLWriter::close();
    	exit(0);
    }

}

?>
