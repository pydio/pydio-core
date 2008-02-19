<?php
include_once(INSTALL_PATH."/plugins/ajxp.remote_fs/class.remote_fsDriver.php");

class remote_svnDriver extends remote_fsDriver 
{
	function remote_svnDriver($driverName, $filePath, $repository){
		parent::remote_fsDriver($driverName, "", $repository);
		// ADDITIONNAL ACTIONS
		$this->xmlFilePath = INSTALL_PATH."/plugins/ajxp.remote_svn/svnActions.xml";
		$this->parseXMLActions();
	}

	function sendInfoPanelsDef(){
		$fileData = file_get_contents(INSTALL_PATH."/plugins/ajxp.remote_svn/svnActions.xml");
		$matches = array();
		preg_match('/<infoPanels>.*<\/infoPanels>/', str_replace("\n", "",$fileData), $matches);
		if(count($matches)){
			AJXP_XMLWriter::header();
			AJXP_XMLWriter::write($this->replaceAjxpXmlKeywords(str_replace("\n", "",$matches[0])), true);
			AJXP_XMLWriter::close();
			exit(1);
		}		
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
	
}

?>