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
class AbstractAccessDriver extends AbstractDriver {
	
	/**
	* @var Repository
	*/
	var $repository;
	var $driverType = "access";
	
	function AbstractAccessDriver($driverName, $filePath, $repository) {
		
		parent::AbstractDriver($driverName);
		$this->repository = $repository;
		$this->initXmlActionsFile($filePath);		
		$this->actions["get_driver_info_panels"] = array();
	}
	
	function initRepository(){
		// To be implemented by subclasses
	}
	
	
	function applyAction($actionName, $httpVars, $filesVar)
	{
		if($actionName == "get_ajxp_info_panels" || $actionName == "get_driver_info_panels"){
			$this->sendInfoPanelsDef();
			return;
		}
		return parent::applyAction($actionName, $httpVars, $filesVar);
	}
	
	/**
	 * Print the XML for actions
	 *
	 * @param boolean $filterByRight
	 * @param User $user
	 */
	function sendActionsToClient($filterByRight, $user){
		parent::sendActionsToClient($filterByRight, $user, $this->repository);
	}
		
	function sendInfoPanelsDef(){
		$fileData = file_get_contents($this->xmlFilePath);
		$matches = array();
		preg_match('/<infoPanels>.*<\/infoPanels>/', str_replace("\n", "",$fileData), $matches);
		if(count($matches)){
			AJXP_XMLWriter::header();
			AJXP_XMLWriter::write($this->replaceAjxpXmlKeywords(str_replace("\n", "",$matches[0])), true);
			AJXP_XMLWriter::close();
			exit(1);
		}		
	}
    
    /** Cypher the publiclet object data and write to disk.
        @param $data The publiclet data array to write 
                     The data array must have the following keys:
                     - DRIVER      The driver used to get the file's content      
                     - OPTION      The driver options to be successfully constructed (usually, the user and password)
                     - FILE_PATH   The path to the file's content
                     - PASSWORD    If set, the written publiclet will ask for this password before sending the content
                     - ACTION      If set, action to perform
                     - EXPIRE_TIME If set, the publiclet will deny downloading after this time, and probably self destruct.
        @return the URL to the downloaded file
    */
    function writePubliclet($data)
    {
          $data["DRIVER_NAME"] = $this->driverName;
          $data["XML_FILE_PATH"] = $this->xmlFilePath;
          $data["REPOSITORY"] = $this->repository;
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
          $outputData = serialize(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $hash, $outputData, MCRYPT_MODE_ECB, $iv));
          // Okay, write the file:
          $fileData = "<"."?"."php \n".
          '   require_once("'.AXJP_INSTALL_PATH.'"/server/conf/conf.php"); '."\n".
          '   require_once("'.AXJP_INSTALL_PATH.'"/server/classes/class.AbstractDriver.php"); '."\n".
          '   $id = str_replace(".php", "", basename($_SERVER["PHP_SELF"])); '."\n". // Not using "" as php would replace $ inside
          '   $cypheredData = unserialize("'.$outputData.'"); '."\n".
          '   $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND); '."\n".
          '   $inputData = unserialize(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $id, $cypheredData, MCRYPT_MODE_ECB, $iv));  '."\n".
          '   if (md5($inputData) != $id) { header("HTTP/1.0 401 Not allowed"); exit(); } '."\n".
          '   // Ok extract the data '."\n".
          '   $data = unserialize($inputData); AbstractAccessDriver::loadPubliclet($data); ?'.'>';
          file_put_content(PUBLIC_URL_FOLDER."/".$hash.".php");   
          return str_replace(AJXP_INSTALL_PATH, substr($_SERVER["REQUEST_URI"], 0, strlen($_SERVER["REQUEST_URI"]) - strlen(basename($_SERVER["REQUEST_URI"]))), 
                             PUBLIC_URL_FOLDER."/".$hash.".php");
    }

    /** Load a uncyphered publiclet */
    function loadPubliclet($data)
    {
        // create driver from $data
        $className = "class.".$data["DRIVER"]."AccessDriver.php";
        $filePath = INSTALL_PATH."/plugins/access.".$data["DRIVER"]."/".$className;
        if(!is_file($filePath)){
                die("Warning, cannot find driver for conf storage! ($name, $filePath)");
        }
        require_once($filePath);
        $driver = new $className( $data["DRIVER_NAME"], $data["XML_FILE_PATH"], $data["REPOSITORY"], $data["OPTIONS"]);
        $driver->initRepository();
        $driver->getAction($data["ACTION"], array("file"=>$data["FILEPATH"]), "");
    }

    /** Create a publiclet object, that will be saved in PUBLIC_URL_FOLDER
        Typically, the class will simply create a data array, and call return writePubliclet($data)
        @param $filePath The path to the file to share
        @return The full public URL to the publiclet.
    */
    function makePubliclet($filePath) {}

}

?>
