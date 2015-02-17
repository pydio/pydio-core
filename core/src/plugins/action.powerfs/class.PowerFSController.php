<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 */

defined('AJXP_EXEC') or die('Access not allowed');

/**
 * @package AjaXplorer_Plugins
 * @subpackage Action
 */
class PowerFSController extends AJXP_Plugin
{

    public function performChecks(){
        if(ShareCenter::currentContextIsLinkDownload()) {
            throw new Exception("Disable during link download");
        }
    }

    public function switchAction($action, $httpVars, $fileVars)
    {
        if(!isSet($this->actions[$action])) return;
        $selection = new UserSelection();
        $dir = $httpVars["dir"] OR "";
        $dir = AJXP_Utils::decodeSecureMagic($dir);
        if($dir == "/") $dir = "";
        $selection->initFromHttpVars($httpVars);
        if (!$selection->isEmpty()) {
            //$this->filterUserSelectionToHidden($selection->getFiles());
        }
        $urlBase = "ajxp.fs://". ConfService::getRepository()->getId();
        $mess = ConfService::getMessages();
        switch ($action) {

            case "monitor_compression" :

                $percentFile = fsAccessWrapper::getRealFSReference($urlBase.$dir."/.zip_operation_".$httpVars["ope_id"]);
                $percent = 0;
                if (is_file($percentFile)) {
                    $percent = intval(file_get_contents($percentFile));
                }
                if ($percent < 100) {
                    AJXP_XMLWriter::header();
                    AJXP_XMLWriter::triggerBgAction(
                        "monitor_compression",
                        $httpVars,
                        $mess["powerfs.1"]." ($percent%)",
                        true,
                        1);
                    AJXP_XMLWriter::close();
                } else {
                    @unlink($percentFile);
                    AJXP_XMLWriter::header();
                    if ($httpVars["on_end"] == "reload") {
                        AJXP_XMLWriter::triggerBgAction("reload_node", array(), "powerfs.2", true, 2);
                    } else {
                        $archiveName =  $httpVars["archive_name"];
                        $jsCode = "
                            var regex = new RegExp('.*?[&\\?]' + 'minisite_session' + '=(.*?)&.*');
                            val = window.ajxpServerAccessPath.replace(regex, \"\$1\");
                            var minisite_session = ( val == window.ajxpServerAccessPath ? false : val );

                            $('download_form').action = window.ajxpServerAccessPath;
                            $('download_form').secure_token.value = window.Connexion.SECURE_TOKEN;
                            $('download_form').select('input').each(function(input){
                                if(input.name!='secure_token') input.remove();
                            });
                            $('download_form').insert(new Element('input', {type:'hidden', name:'ope_id', value:'".$httpVars["ope_id"]."'}));
                            $('download_form').insert(new Element('input', {type:'hidden', name:'archive_name', value:'".$archiveName."'}));
                            $('download_form').insert(new Element('input', {type:'hidden', name:'get_action', value:'postcompress_download'}));
                            if(minisite_session) $('download_form').insert(new Element('input', {type:'hidden', name:'minisite_session', value:minisite_session}));
                            $('download_form').submit();
                            $('download_form').get_action.value = 'download';
                        ";
                        AJXP_XMLWriter::triggerBgJsAction($jsCode, $mess["powerfs.3"], true);
                        AJXP_XMLWriter::triggerBgAction("reload_node", array(), "powerfs.2", true, 2);
                    }
                    AJXP_XMLWriter::close();
                }

                break;

            case "postcompress_download":

                $archive = AJXP_Utils::getAjxpTmpDir()."/".$httpVars["ope_id"]."_".$httpVars["archive_name"];
                $fsDriver = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("access");
                if (is_file($archive)) {
                    register_shutdown_function("unlink", $archive);
                    $fsDriver->readFile($archive, "force-download", $httpVars["archive_name"], false, null, true);
                } else {
                    echo("<script>alert('Cannot find archive! Is ZIP correctly installed?');</script>");
                }
                break;

            case "compress" :
            case "precompress" :
		
		$zipper = $this->getFilteredOption("ZIPPER");
		AJXP_Logger::debug("Selected Zipper " . $zipper);
		
                $archiveName = AJXP_Utils::sanitize(AJXP_Utils::decodeSecureMagic($httpVars["archive_name"]), AJXP_SANITIZE_FILENAME);
                if (!ConfService::currentContextIsCommandLine() && ConfService::backgroundActionsSupported()) {
                    $opeId = substr(md5(time()),0,10);
                    $httpVars["ope_id"] = $opeId;
                    AJXP_Controller::applyActionInBackground(ConfService::getRepository()->getId(), $action, $httpVars);
                    AJXP_XMLWriter::header();
                    $bgParameters = array(
                        "dir" => $dir,
                        "archive_name"  => $archiveName,
                        "on_end" => (isSet($httpVars["on_end"])?$httpVars["on_end"]:"reload"),
                        "ope_id" => $opeId
                    );
                    AJXP_XMLWriter::triggerBgAction(
                        "monitor_compression",
                        $bgParameters,
                        $mess["powerfs.1"]." (0%)",
                        true);
                    AJXP_XMLWriter::close();
                    session_write_close();
                    exit();
                }
			
		if ($zipper == "zip")
			$this->compress_zip($action, $httpVars, $fileVars, $dir, $selection, $urlBase);
		else if ($zipper != "")
			$this->compress_general($action, $httpVars, $fileVars, $dir, $selection, $urlBase, $zipper);
		else
			AJXP_Logger::logAction("Not a valid ZIP tool found.");
		
                break;
            default:
                break;
        }
	}

	public function compress_general ($action, $httpVars, $fileVars, $dir, $selection, $urlBase, $zipper)
	{
		$rootDir = fsAccessWrapper::getRealFSReference($urlBase) . $dir;
		$percentFile = $rootDir."/.zip_operation_".$httpVars["ope_id"];
		$compressLocally = ($action == "compress" ? true : false);
		// List all files
		$args = array();
		foreach ($selection->getFiles() as $selectionFile) {
			$baseFile = $selectionFile;
			$args[] = escapeshellarg(substr($selectionFile, strlen($dir)+($dir=="/"?0:1)));
			$selectionFile = fsAccessWrapper::getRealFSReference($urlBase.$selectionFile);
			
			if(trim($baseFile, "/") == ""){
				// ROOT IS SELECTED, FIX IT
				$args = array(escapeshellarg(basename($rootDir)));
				$rootDir = dirname($rootDir);
				break;
			}
		}
		$files = ""; // Getting list of all files and writing them in file
		foreach ($args as &$value)
		{
			$valuef = $rootDir."\\".str_replace("\"","",$value);
			if (is_dir($valuef))
				$files = $files . $this->read_dir_recursive($valuef, $rootDir); 
			else if (substr($value, 0, 5) != ".axp.")
				$files = $files . str_replace("\"","",$value) . "\r\n"; 
		}
		$files = substr($files, 0, strlen($files) - 2); 
		
		//Setting file names
		$cmdSeparator = ((PHP_OS == "WIN32" || PHP_OS == "WINNT" || PHP_OS == "Windows")? "&" : ";");
		$archiveName = AJXP_Utils::sanitize(AJXP_Utils::decodeSecureMagic($httpVars["archive_name"]), AJXP_SANITIZE_FILENAME);
		$filesname = ".axp.".$httpVars["archive_name"].".files.txt";
		$logfile = ".axp.".$httpVars["archive_name"].".processed.txt";
		$clioutputfile = $rootDir."\\.axp.".$httpVars["archive_name"].".cli_output.log";
		$clierrorfile = $rootDir."\\.axp.".$httpVars["archive_name"].".cli_error.log";
		if (!$compressLocally) {
			$archiveName = AJXP_Utils::getAjxpTmpDir()."\\".$httpVars["ope_id"]."_".$archiveName;
			$filesname = ".axp.".$httpVars["ope_id"]."_".$httpVars["archive_name"].".files.txt";
			$logfile = ".axp.".$httpVars["ope_id"]."_".$httpVars["archive_name"].".processed.txt";
			$clioutputfile = AJXP_Utils::getAjxpTmpDir()."\\".$httpVars["ope_id"]."_".$httpVars["archive_name"].".cli_output.log";
			$clierrorfile = AJXP_Utils::getAjxpTmpDir()."\\".$httpVars["ope_id"]."_".$httpVars["archive_name"].".cli_error.log";
		}
		$zipspeedfile = AJXP_Utils::getAjxpTmpDir() . "\\" . "AJXP_zip_avgspeed.txt";
		AJXP_Logger::debug("Files \r\n" . $archiveName . "\r\n" . $filesname . "\r\n" . $logfile . "\r\n" . $clioutputfile . "\r\n" . $clierrorfile . "\r\n" . $zipspeedfile);
		
		//Changing root dir
		//AJXP_Logger::debug(getcwd());
		chdir($rootDir);
		AJXP_Logger::debug(getcwd());
		
		// Setting parameters
	        $zip_exe = $this->getFilteredOption("ZIPPER_MAN");	
	        if($this->getFilteredOption("ZIPPER_PATH") != "")//Setting ZIPPER_PATH
	        {
			$zip_path = $this->getFilteredOption("ZIPPER_PATH");
			if (substr($zip_path, -1) != "\\" || substr($zip_path, -1) != "/") $zip_path = $zip_path . "\\";
		}
	        else
	            $zip_path = "";
	        if($this->getFilteredOption("MAX_EXE_TIME") != 0)//Setting max execution time
	            set_time_limit (intval($this->getFilteredOption("MAX_EXE_TIME")));
	        else
	            set_time_limit (1400);  
	        if($this->getFilteredOption("MAX_ZIP_TIME") != 0)//Setting zip time
	            $max_zip_time = intval($this->getFilteredOption("MAX_ZIP_TIME"));
	        else
	            $max_zip_time = 1200;  
	        if($this->getFilteredOption("MAX_ZIP_TIME_FILE") != 0)//Setting zip time file
	            $max_zip_time_file = intval($this->getFilteredOption("MAX_ZIP_TIME_FILE"));
	        else
	            $max_zip_time_file = 120;  
	        if($this->getFilteredOption("ZIPPER_MONITOR_PAUSE") != 0)//Setting ZIPPER_MONITOR_PAUSE
	            $monitor_pause = intval($this->getFilteredOption("ZIPPER_MONITOR_PAUSE")) * 1000;
	        else
	            $monitor_pause = 500000;  
	        if($this->getFilteredOption("ZIPPER_MONITOR_RECOG_PATTERN") != "")//Setting ZIPPER_MONITOR_RECOG_AFFECTS
	            $recogpattern = $this->getFilteredOption("ZIPPER_MONITOR_RECOG_PATTERN");
	        else
	            $recogpattern = "%file%";
	        if($this->getFilteredOption("ZIPPER_MONITOR_RECOG_AFFECTS") == "end")//Setting ZIPPER_MONITOR_RECOG_AFFECTS
	            $fproc_regoc_at_zipstart = false;
	        else
	            $fproc_regoc_at_zipstart = true;
	        if($this->getFilteredOption("ZIPPER_MONITOR_SUCCESS_STATEMENT") != "")//Setting ZIPPER_MONITOR_RECOG_AFFECTS
	            $zipsuccess_statement = $this->getFilteredOption("ZIPPER_MONITOR_SUCCESS_STATEMENT");
	        else
	            $zipsuccess_statement = "#none#";
		
		// Preparing and executing CLI
		file_put_contents($filesname, $files);
		if (file_exists($clioutputfile)) unlink($clioutputfile);
		$descriptorspec = array(
		   0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
		   1 => array("file", $clioutputfile, "a"),  // stdout is a pipe that the child will write to
		   2 => array("file", $clierrorfile, "a") // stderr is a file to write to
		);
		$oopt = array('bypass_shell' => true); 
		
		switch ($zipper){
			case "zip2" :
				//$cmd = $zip_path . "zip -r ".escapeshellarg($archiveName)." ".implode(" ", $args);
				$cmd = $zip_path . "zip -r ".escapeshellarg($archiveName)." ". "\"" . str_replace("\r\n", "\" \"", $files) . "\"";
				if (PHP_OS != "WIN32" && PHP_OS != "WINNT" && PHP_OS != "Windows") {
					$fsDriver = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("access");
					$c = $fsDriver->getConfigs();
					if (!isSet($c["SHOW_HIDDEN_FILES"]) || $c["SHOW_HIDDEN_FILES"] == false) {
						$cmd .= " -x .\*";
					}
				}
				break;
			case "winrar" :
				$cmd = $zip_path . "winrar a -afzip -ibck -logf=" . escapeshellarg($logfile) . " " . escapeshellarg($archiveName) . " @" . escapeshellarg($filesname);
				break;
			case "7zip" :
				$cmd = $zip_path . "7z a -tzip ".escapeshellarg($archiveName)." @" . escapeshellarg($filesname);
				break;
			case "other" :
				$zip_exe = str_replace("%archive%", escapeshellarg($archiveName), $zip_exe);
				$zip_exe = str_replace("%files%", "\"" . implode("\" \"", $args) . "\"" , $zip_exe);
				$zip_exe = str_replace("%listfile%", escapeshellarg($filesname), $zip_exe);
				$zip_exe = str_replace("%outputfile%", escapeshellarg($logfile), $zip_exe);
				$cmd = $zip_path . $zip_exe;
				break;
			default:
				break;
		}
		AJXP_Logger::debug("Executing zip command");
		AJXP_Logger::debug($cmd);				
		$proc = proc_open($cmd, $descriptorspec, $pipes, $rootDir, null, $oopt);
		
		// Preparing progress check and getting information on files while ZIP is running to save time
		$toks = array();
		$handled = array();
		$finishedEchoed = false;
		
		$files_name = array();
		$files_size = array();
		$files_fnd = array();
		$files_totalsize = 0;
		
		$files_name = explode("\r\n",$files);
		AJXP_Logger::debug("Counting files " . count($files_name));
		foreach ($files_name as &$fle)
		{
			if (file_exists($fle))
			{
				$files_size[] = filesize($fle);
				$files_totalsize = $files_totalsize + $files_size[count($files_size) - 1];
			}
			else
				$files_size[] = 0;
			$files_fnd[] = false;
		}
		AJXP_Logger::debug("Totalsize MB " . $files_totalsize / 1000000);
		$linesarr = array();
		$linescnt = count($linesarr);
		$fprocessed = 0;
						
		$maxwaiting = 5; //Waiting for zipper to start
		for ($l = 0; $l <= $maxwaiting * 10; $l++)
		{
			$stat = proc_get_status($proc);
			if (!$stat['running'])
				usleep(100000);
			else
				$l = $maxwaiting + 1;
		}
		$filetime = 0;
		$kill_proc = false;
		$zipstart = time();
		$zipstartfile = time();
		$percent = 0;
		$zipcomplete = false;
		$zipOK = -1;
		$zipstat = 0;
		$fprocessed_delta = 0;
		$fprocessed_cnt = 0;
		$cliout = "";
		
		//select & configure monitoring process
		switch ($zipper){
			case "winrar" :
				if ($this->getFilteredOption("ZIPPER_MONITOR") == "fileoutput")
					$monproc = "fileoutput";
				else if ($this->getFilteredOption("ZIPPER_MONITOR") == "avgspeed")
					$monproc = "avgspeed";
				else {
					$monproc = "fileoutput";
					AJXP_Logger::logAction("Not a valid monitoring process was set, setting to fileoutput");
				}
				$recogpattern = "/%file%/";
				$zipOK = 0;
				$fproc_regoc_at_zipstart = true;
				break;
			case "7zip" :
				$monproc = "avgspeed";
				$zipsuccess_statement = "Everything is Ok";
				$zipOK = 0;
				break;
			case "zip2" :
				if ($this->getFilteredOption("ZIPPER_MONITOR") == "clioutput")
					$monproc = "clioutput";
				else if ($this->getFilteredOption("ZIPPER_MONITOR") == "avgspeed")
					$monproc = "avgspeed";
				else {
					$monproc = "clioutput";
					AJXP_Logger::logAction("Not a valid monitoring process was set, setting to clioutput");
				}
				$recogpattern = '/(\w+): (%file%) \(([^\(]+)\) \(([^\(]+)\)/';
				$zipOK = 0;
				break;
			case "other" :
				$monproc = $this->getFilteredOption("ZIPPER_MONITOR");
				if ($zipsuccess_statement != "#none#")
					$zipOK = 0;
				break;
			default:
				$monproc = "avgspeed"; 
				AJXP_Logger::logAction("Applying default zip monitor");
				break;
		}
		AJXP_Logger::debug("Selected monitor " . $monproc);
		
		// Monitoring progress
		if (is_resource($proc))
		{
			switch ($monproc){
				case "clioutput" :
				case "fileoutput" :
					if ($monproc == "clioutput")
						$moninputfile = $clioutputfile;
					else if ($monproc == "fileoutput")
						$moninputfile = $logfile;
					else
						$moninputfile = "";
					while (!$zipcomplete && !$kill_proc)
					{
						if (file_exists($moninputfile))
						{
							$linesarr = explode("\r\n", file_get_contents($moninputfile));
							if ($linescnt != count($linesarr)) // Has a new line been added
							{
								for ($i = 0; $i <= count($files_name) - 1 ; $i++) // Check all files to be in the zip
								{
									//AJXP_Logger::debug("Current file " . $files_name[$i]);
									if ($files_fnd[$i] == false)
									{
										$patt = str_replace("%file%", str_replace("\\", "\\\\", $files_name[$i]), $recogpattern);
										//AJXP_Logger::debug("Pattern " . $patt);
										foreach ($linesarr as &$str2)			// If file matches a should file, mark it as found, add the size, calc percent and restart max zip file time
										{
											$obj = str_replace("/", "\\", $str2);
											$test = preg_match($patt, $obj);
											//AJXP_Logger::debug(" Comp " . $obj);
											//AJXP_Logger::debug(" Resu " . $test);
											if($fproc_regoc_at_zipstart) $fprocessed_delta = $files_size[$i] / 2;
												
											if ($test == 1) 
											{
												$fprocessed = $fprocessed + $files_size[$i];
												$percent = round(($fprocessed - $fprocessed_delta) / $files_totalsize * 100);
												file_put_contents($percentFile, $percent);
												$zipstartfile = time();
												$files_fnd[$i] = true;
												$fprocessed_cnt++;
												//AJXP_Logger::debug("File found " . $files_name[$i]);
											}
										}
									}
								}
								$linescnt = count($linesarr);
							}
							
							if ($fprocessed_cnt >= count($files_name))
							{
								$zipcomplete = true;
								AJXP_Logger::debug("All found files compressed! Setting 100%");
							}
						}
						$stat = proc_get_status($proc); // Checking wether the process is running or has completed
						if (!$stat['running']) { if ($zipstat == 0)
							{ $zipstat = 1; AJXP_Logger::debug("Process end detected");}
						else 
							{ $zipcomplete = true; AJXP_Logger::debug("Setting ZIP Complete true");}}
						if (file_exists($moninputfile)) $cliout = file_get_contents($moninputfile); // Checking success statement in command line output
						if (strpos($cliout, $zipsuccess_statement) > 1) {$zipcomplete = true; $zipOK = 1;}
						if (time() - $zipstart > $max_zip_time) $kill_proc = true; // Maximum exe time reached
						if (time() - $zipstartfile > $max_zip_time_file) // Max execution time per file
						{
							AJXP_Logger::logAction("Abort due monitoringfile has not changed max given time.");
							$kill_proc = true;
						}
						
						if(!$zipcomplete) usleep($monitor_pause);
					}
					//AJXP_Logger::debug($fprocessed_cnt);
					//AJXP_Logger::debug(count($files_name));
					if ($fprocessed_cnt == count($files_name))
						$zipOK = 1;
					break;
				case "avgspeed" :
					$zipspeed = 0;
					$zipwaiting = false;
					$zipspeeda = array(1, 25); //seconds per MB
					if (file_exists($zipspeedfile)) $zipspeeda = explode("#",file_get_contents($zipspeedfile));
					$zipspeed = ( ((int)$zipspeeda[1]) / ((int)$zipspeeda[0]) );
					AJXP_Logger::debug("AvgSpeed MB/s " . $zipspeed);
					if (((int)$zipspeeda[0]) > 10000)
					{
						AJXP_Logger::logAction("Reducing speed average values to avoid stack overflow");
						$zipspeeda[0] = ((int)( (int)$zipspeeda[0] / 100 ));
						$zipspeeda[1] = ((int)( (int)$zipspeeda[1] / 100 ));
					}
					
					while (!$zipcomplete && !$kill_proc)
					{
						$percent = round((time() - $zipstart) * $zipspeed / ($files_totalsize / 1000000) * 100);
						if ($percent <= 98)
							file_put_contents($percentFile, $percent);
						else if (!$zipwaiting)
						{
							file_put_contents($percentFile, 98);
							AJXP_Logger::debug("Waiting for ending ... 98% is reached");
							$zipwaiting = true;
						}
							
						$stat = proc_get_status($proc); // Checking wether the process is running or has completed
						if (!$stat['running']) { if ($zipstat == 0) $zipstat = 1; else $zipcomplete = true;}
						if (file_exists($moninputfile)) $cliout = file_get_contents($moninputfile); // Checking success statement in command line output
						if (strpos($cliout, $zipsuccess_statement) > 1) {$zipcomplete = true; $zipOK = 1;}
						if (time() - $zipstart > $max_zip_time) $kill_proc = true; // Maximum exe time reached
						
						if(!$zipcomplete) usleep($monitor_pause);
					}
					$zipend = time();
					file_put_contents($zipspeedfile, ($zipspeeda[0] + $zipend - $zipstart) . "#" . ((int)( ($files_totalsize / 1000000) + $zipspeeda[1])));					
					if (file_exists($moninputfile))
						if (strpos(file_get_contents($moninputfile), $zipsuccess_statement) > 1)
							$zipOK = 1;
					break;
				default:
					AJXP_Logger::logAction("Cannot find appropiate monitoring process");
					break;
			}
		}
		else
		{
			AJXP_Logger::logAction("Zip Process could not be started.");
			file_put_contents($percentFile, 100);
		}
		
		if ($kill_proc) // Terminate in case of errors
		{
			AJXP_Logger::logAction("Zip process should be terminated due to an error. Terminating process ...");
			proc_terminate($proc);
		}
		$maxwaiting = 30; // Waiting x seconds to give process time to quit
		for ($k = 0; $k <= $maxwaiting * 10; $k++)
		{
			$stat = proc_get_status($proc);
			if ($stat['running'] && k == $maxwaiting) // Termintate in case of still running
			{
				AJXP_Logger::logAction("Zip process still running after ".$maxwaiting."s. Terminating process ...");
				proc_terminate($proc);
			}
			if ($stat['running'])
				usleep(100000);
			else
				$k = $maxwaiting + 1;
		}
		file_put_contents($percentFile, 100);
		AJXP_Logger::debug("Closing Proc");
		proc_close ($proc);
		
		// Deleting input / output files
		if (file_exists($filesname)) unlink($filesname);
		if (file_exists($logfile)) unlink($logfile);
		if (file_exists($clioutputfile)) unlink($clioutputfile);
		if (file_exists($clierrorfile)) // Logging errors of cli
		{
			$fcontent = file_get_contents($clierrorfile);
			unlink($clierrorfile);
			if (strlen($fcontent) > 2) AJXP_Logger::logAction("Command Line Error " . $fcontent);
		}
		
		if ($zipOK == 0) // Checking success statement
			AJXP_Logger::logAction("No expected success statement found. ZIP has probable errors.");
		
		file_put_contents($percentFile, 100);  // Ending monitoring process by writing 100% in percentfile
		AJXP_Logger::debug("Final end of compressing");
	}
	
	public function compress_zip($action, $httpVars, $fileVars, $dir, $selection, $urlBase)
	{ 
		$rootDir = fsAccessWrapper::getRealFSReference($urlBase) . $dir;
		$percentFile = $rootDir."/.zip_operation_".$httpVars["ope_id"];
		$compressLocally = ($action == "compress" ? true : false);
		// List all files
		$todo = array();
		$args = array();
		$replaceSearch = array($rootDir, "\\");
		$replaceReplace = array("", "/");
		foreach ($selection->getFiles() as $selectionFile) {
			$baseFile = $selectionFile;
			$args[] = escapeshellarg(substr($selectionFile, strlen($dir)+($dir=="/"?0:1)));
			$selectionFile = fsAccessWrapper::getRealFSReference($urlBase.$selectionFile);
			$todo[] = ltrim(str_replace($replaceSearch, $replaceReplace, $selectionFile), "/");
			if (is_dir($selectionFile)) {
				$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($selectionFile), RecursiveIteratorIterator::SELF_FIRST);
				foreach ($objects as $name => $object) {
					$todo[] = str_replace($replaceSearch, $replaceReplace, $name);
				}
			}
			if(trim($baseFile, "/") == ""){
				// ROOT IS SELECTED, FIX IT
				$args = array(escapeshellarg(basename($rootDir)));
				$rootDir = dirname($rootDir);
				break;
			}
		}
		$cmdSeparator = ((PHP_OS == "WIN32" || PHP_OS == "WINNT" || PHP_OS == "Windows")? "&" : ";");
		//$archiveName = SystemTextEncoding::fromUTF8($httpVars["archive_name"]);
		if (!$compressLocally) {
			$archiveName = AJXP_Utils::getAjxpTmpDir()."/".$httpVars["ope_id"]."_".$archiveName;
		}
		chdir($rootDir);
		$cmd = $this->getFilteredOption("ZIPPER_PATH")."zip -r ".escapeshellarg($archiveName)." ".implode(" ", $args);
		$fsDriver = AJXP_PluginsService::getInstance()->getUniqueActivePluginForType("access");
		$c = $fsDriver->getConfigs();
		if ((!isSet($c["SHOW_HIDDEN_FILES"]) || $c["SHOW_HIDDEN_FILES"] == false) && stripos(PHP_OS, "win") === false) {
			$cmd .= " -x .\*";
		}
		$cmd .= " ".$cmdSeparator." echo ZIP_FINISHED";
		$proc = popen($cmd, "r");
		$toks = array();
		$handled = array();
		$finishedEchoed = false;
		if($this->getFilteredOption("MAX_ZIP_TIME_FILE") != 0)//Setting zip time
			$max_zip_time = (int)$this->getFilteredOption("MAX_ZIP_TIME_FILE");
		else
			$max_zip_time = 120;  
		
		while (!feof($proc)) {
			set_time_limit ($max_zip_time);
			$results = fgets($proc, 256);
			if (strlen($results) == 0) {
			} else {
				$tok = strtok($results, "\n");
				while ($tok !== false) {
					$toks[] = $tok;
					if ($tok == "ZIP_FINISHED") {
						$finishedEchoed = true;
					} else {
						$test = preg_match('/(\w+): (.*) \(([^\(]+)\) \(([^\(]+)\)/', $tok, $matches);
						if ($test !== false) {
							$handled[] = $matches[2];
						}
					}
					$tok = strtok("\n");
				}
				if($finishedEchoed) $percent = 100;
				else $percent = min( round(count($handled) / count($todo) * 100),  100);
				file_put_contents($percentFile, $percent);
			}
			// avoid a busy wait
			if($percent < 100) usleep(1);
		}
		pclose($proc);
		file_put_contents($percentFile, 100);
	}
	
	public function read_dir_recursive($dir, $rootdir) 
	{ 
	    $handle =  opendir($dir);
		$filesr = "";

	    while ($arg = readdir($handle)) 
	    { 
			$val = $dir."\\".$arg;
	        if ($arg != "." && $arg != "..") 
	        { 
	            if (is_dir($val))
	                $filesr = $filesr . $this->read_dir_recursive($val, $rootdir); 
	            else if (strpos($val, ".axp.") === false)
	                $filesr = $filesr . substr($val, strlen($rootdir) + 1) . "\r\n"; 
	        }
	    }
		
	    closedir($handle);
		
		return $filesr;
	} 
}
