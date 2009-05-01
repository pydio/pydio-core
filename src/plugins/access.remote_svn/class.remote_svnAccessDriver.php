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
 * Description : Still experimental, browse an SVN repository.
 */
include_once(INSTALL_PATH."/plugins/access.remote_fs/class.remote_fsAccessDriver.php");

class remote_svnAccessDriver extends remote_fsAccessDriver 
{
	function remote_svnAccessDriver($driverName, $filePath, $repository, $optOptions = NULL){
		parent::remote_fsAccessDriver($driverName, "", $repository, $optOptions);
		// ADDITIONNAL ACTIONS
		$this->xmlFilePath = INSTALL_PATH."/plugins/access.remote_svn/svnActions.xml";
		$this->parseXMLActions();
	}
	
	function svnStubAction($actionName, $httpVars, $filesVars){
		if($actionName == "svnlog"){
			AJXP_XMLWriter::header();
			echo '<log><logentry revision="310"><author>cdujeu</author><date>2008-02-19</date><msg>Commit type errors</msg></logentry><logentry revision="308"><author>mbronni</author><date>2008-02-19</date><msg>New Function</msg></logentry><logentry revision="300"><author>cdujeu</author><date>2008-02-19</date><msg>New Factory Class</msg></logentry></log>
			';
			AJXP_XMLWriter::close();
		}else if($actionName == "svndownload"){
			$file = $httpVars["file"];
			$rev = $httpVars["revision"];
			parent::switchAction("download", $httpVars);
		}
		exit(1);
	}
	
	function svnDownload($actionName, $httpVars, $filesVars){
		$sessionId = "";
		$crtRep = ConfService::getRepository();
		$httpClient = $this->getRemoteConnexion($sessionId);
		$httpVars["ajxp_sessid"] = $sessionId;
		$method = "get";
		if($method == "get"){
			$httpClient->get($crtRep->getOption("URI"), $httpVars);
		}else{			
			$httpClient->post($crtRep->getOption("URI"), $httpVars);
		}
		// check if session is expired
		if(strpos($httpClient->getHeader("content-type"), "text/xml") !== false && strpos($httpClient->getContent(), "require_auth") != false){
			$httpClient = $this->getRemoteConnexion($sessionId, true);
			$httpVars["ajxp_sessid"] = $sessionId;
			$method = "get";
			if($method == "get"){
				$httpClient->get($crtRep->getOption("URI"), $httpVars);
			}else{			
				$httpClient->post($crtRep->getOption("URI"), $httpVars);
			}
		}
		
		$size=strlen($httpClient->content);
		$filePath = $httpVars["file"];
		
		$svnFileName = $httpClient->getHeader("AjaXplorer-SvnFileName");
		
		header("Content-Type: application/force-download; name=\"".$svnFileName."\"");
		header("Content-Transfer-Encoding: binary");
		header("Content-Length: ".$size);
		header("Content-Disposition: attachment; filename=\"".$svnFileName."\"");
		header("Expires: 0");
		header("Cache-Control: no-cache, must-revalidate");
		header("Pragma: no-cache");
		// For SSL websites, bug with IE see article KB 323308
		if (ConfService::getConf("USE_HTTPS")==1 && preg_match('/ MSIE /',$_SERVER['HTTP_USER_AGENT'])){
			header("Cache-Control:");
			header("Pragma:");
		}
		print $httpClient->getContent();
		session_write_close();
		exit();				
	}
}

?>
