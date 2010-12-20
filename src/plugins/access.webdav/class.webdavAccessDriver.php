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
 * Description : The most used and standard plugin : FileSystem access
 */

defined('AJXP_EXEC') or die( 'Access not allowed');
require_once(INSTALL_PATH."/plugins/access.fs/class.fsAccessDriver.php");
@include_once("HTTP/WebDAV/Client.php");

class webdavAccessDriver extends fsAccessDriver
{
	/**
	* @var Repository
	*/
	public $repository;
	public $driverConf;
	protected $wrapperClassName;
	protected $urlBase;
		
	function initRepository(){
		if(is_array($this->pluginConf)){
			$this->driverConf = $this->pluginConf;
		}else{
			$this->driverConf = array();
		}

		if(!class_exists('HTTP_WebDAV_Client_Stream')){
			throw new Exception("You must have Pear HTTP/WebDAV/Client package installed to use this access driver!");
		}
		$create = $this->repository->getOption("CREATE");
		$path = $this->repository->getOption("PATH");
		$recycle = $this->repository->getOption("RECYCLE_BIN");
		/*
		if($create == true){
			if(!is_dir($path)) @mkdir($path);
			if(!is_dir($path)){
				throw new AJXP_Exception("Cannot create root path for repository (".$this->repository->getDisplay()."). Please check repository configuration or that your folder is writeable!");
			}
			if($recycle!= "" && !is_dir($path."/".$recycle)){
				@mkdir($path."/".$recycle);
				if(!is_dir($path."/".$recycle)){
					throw new AJXP_Exception("Cannot create recycle bin folder. Please check repository configuration or that your folder is writeable!");
				}
			}
		}else{
			if(!is_dir($path)){
				throw new AJXP_Exception("Cannot find base path ($path) for your repository! Please check the configuration!");
			}
		}
		*/
		$wrapperData = $this->detectStreamWrapper(true);
		$this->wrapperClassName = $wrapperData["classname"];
		$this->urlBase = $wrapperData["protocol"]."://".$this->repository->getId();
		if(!is_dir($this->urlBase)){
			throw new AJXP_Exception("Cannot find base path ($path) for your repository! Please check the configuration!");
		}
		if($recycle != ""){
			RecycleBinManager::init($this->urlBase, "/".$recycle);
		}
	}
}	

?>