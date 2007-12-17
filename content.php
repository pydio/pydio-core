<?php
//---------------------------------------------------------------------------------------------------
//
//	AjaXplorer v1.4
//
//	Charles du Jeu
//	http://sourceforge.net/projects/ajaxplorer
//  http://www.almasound.com
//
//---------------------------------------------------------------------------------------------------

//require_once("classes/class.BookmarksManager.php");
require_once("classes/class.Utils.php");
require_once("classes/class.ConfService.php");
require_once("classes/class.AuthService.php");
require_once("classes/class.FS_Storage.php");
require_once("classes/class.UserSelection.php");
require_once("classes/class.HTMLWriter.php");
require_once("classes/class.AJXP_XMLWriter.php");
require_once("classes/class.AJXP_User.php");
require_once("classes/class.RecycleBinManager.php");
if(isSet($_GET["ajxp_sessid"]))
{
	$_COOKIE["PHPSESSID"] = $_GET["ajxp_sessid"];
}
session_start();
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
ConfService::init("conf/conf.php");
$baspage=ConfService::getConf("BOTTOM_PAGE");
$limitSize = Utils::convertBytes(ini_get('upload_max_filesize'));

if(AuthService::usersEnabled())
{
	if(isSet($_GET["get_action"]) && $_GET["get_action"] == "logout")
	{
		AuthService::disconnect();
		$loggingResult = 2;
	}	//AuthService::disconnect();
	if(isSet($_GET["get_action"]) && $_GET["get_action"] == "login")
	{
		$userId = (isSet($_GET["userid"])?$_GET["userid"]:null);
		$userPass = (isSet($_GET["password"])?$_GET["password"]:null);
		$loggingResult = AuthService::logUser($userId, $userPass);
	}
	else 
	{
		AuthService::logUser(null, null);	
	}
	// Check that current user can access current repository, try to switch otherwise.
	$loggedUser = AuthService::getLoggedUser();
	if($loggedUser != null)
	{
		if(!$loggedUser->canRead(ConfService::getCurrentRootDirIndex()) && AuthService::getDefaultRootId() != ConfService::getCurrentRootDirIndex())
		{
			ConfService::switchRootDir(AuthService::getDefaultRootId());
		}
	}
	if($loggedUser == null)
	{
		$requireAuth = true;
	}
	if(isset($loggingResult) || (isSet($_GET["get_action"]) && $_GET["get_action"] == "logged_user"))
	{
		AJXP_XMLWriter::header();
		if(isSet($loggingResult)) AJXP_XMLWriter::loggingResult($loggingResult);
		AJXP_XMLWriter::sendUserData();
		AJXP_XMLWriter::close();
		exit(1);
	}
}

$loggedUser = AuthService::getLoggedUser();
if($loggedUser != null)
{
	if($loggedUser->getPref("lang") != "") ConfService::setLanguage($loggedUser->getPref("lang"));
}
$mess = ConfService::getMessages();

foreach($_GET as $getName=>$getValue)
{
	$$getName = Utils::securePath($getValue);
}
foreach($_POST as $getName=>$getValue)
{
	$$getName = Utils::securePath($getValue);
}

$selection = new UserSelection();
$selection->initFromHttpVars();

if(isSet($action) || isSet($get_action)) $action = (isset($get_action)?$get_action:$action);
else $action = "";

if(isSet($dir) && $action != "upload") $dir = utf8_decode($dir);
if(isSet($dest)) $dest = utf8_decode($dest);

// FILTER ACTION FOR DELETE
if(ConfService::useRecycleBin() && $action == "delete" && $dir != "/".ConfService::getRecycleBinDir())
{
	$action = "move";
	$dest = "/".ConfService::getRecycleBinDir();
	$dest_node = "AJAXPLORER_RECYCLE_NODE";
}
// FILTER ACTION FOR RESTORE
if(ConfService::useRecycleBin() &&  $action == "restore" && $dir == "/".ConfService::getRecycleBinDir())
{
	$originalRep = RecycleBinManager::getFileOrigin($selection->getUniqueFile());
	if($originalRep != "")
	{
		$action = "move";
		$dest = $originalRep;
	}
	
}

//--------------------------------------
// FIRST CHECK RIGHTS FOR THIS ACTION
//--------------------------------------
if(AuthService::usersEnabled())
{
	$loggedUser = AuthService::getLoggedUser();	
	switch ($action)
	{
		// NEEDS WRITE RIGHTS
		case "edit":
		case "copy":
		case "move":
		case "delete":
		case "rename":
		case "mkdir":
		case "mkfile":
			if($loggedUser == null || !$loggedUser->canWrite(ConfService::getCurrentRootDirIndex().""))
			{
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage(null, "You have no write permission!");
				AJXP_XMLWriter::requireAuth();
				AJXP_XMLWriter::close();
				exit(1);
			}
		break;		
		case "upload":		
		case "fancy_uploader":
			if($loggedUser == null || !$loggedUser->canWrite(ConfService::getCurrentRootDirIndex().""))
			{
				if(isSet($_FILES['Filedata']))
				{
					header('HTTP/1.0 ' . '415 Not authorized');
					die('Error 415 Not authorized!');
				}
				else
				{
					AJXP_XMLWriter::header();
					AJXP_XMLWriter::sendMessage(null, $mess[207]);
					AJXP_XMLWriter::requireAuth();
					AJXP_XMLWriter::close();
				}
				exit(1);
			}
		break;
		
		// NEEDS READ RIGHTS
		case "voir":
		case "image_proxy":
		case "mp3_proxy":
		case "switch_root_dir":
		case "xml_listing":
		case "download":
		case "root_tree":		
			if($loggedUser == null || !$loggedUser->canRead(ConfService::getCurrentRootDirIndex().""))
			{
				AJXP_XMLWriter::header();
				AJXP_XMLWriter::sendMessage(null, $mess[208]);
				AJXP_XMLWriter::requireAuth();
				AJXP_XMLWriter::close();
				exit(1);
			}			
		break;
		// NO SPECIFIC RIGHTS
		case "display_action_bar":
		case "display_bookmark_bar":
		case "display_doc":
		default:
		break;
	}
}

//------------------------------------
//	SWITCH ON ACTION VARIABLE
//------------------------------------

switch($action)
{
	//------------------------------------
	//	SWITCH THE ROOT REPOSITORY
	//------------------------------------	
	case "switch_root_dir":
	
		if(!isSet($root_dir_index))
		{
			break;
		}
		$dirList = ConfService::getRootDirsList();
		if(!isSet($dirList[$root_dir_index]))
		{
			$errorMessage = "Trying to switch to an unkown folder!";
			break;
		}
		ConfService::switchRootDir($root_dir_index);
		$logMessage = "Successfully Switched!";
		
	break;
	
	//------------------------------------
	//	DOWNLOAD, IMAGE & MP3 PROXYS
	//------------------------------------
	case "download";
		FS_Storage::readFile(ConfService::getRootDir()."/".utf8_decode($file), "force-download");
		exit(0);
	break;

	case "image_proxy":
		FS_Storage::readFile(ConfService::getRootDir()."/".utf8_decode($file), "image");
		exit(0);
	break;
	
	case "mp3_proxy":
		FS_Storage::readFile(ConfService::getRootDir()."/".$file, "mp3");
		exit(0);
	break;

	//------------------------------------
	//	GET AN HTML TEMPLATE
	//------------------------------------
	case "get_template":
	
		header("Content-type:text/html");
		if(isset($template_name) && is_file("include/html/".$template_name))
		{
			if(!isSet($encode) || $encode != "false")
			{
				$mess = array_map("utf8_encode", $mess);
			}
			include("include/html/".$template_name);
		}
		exit(0);	
		
	break;
	
	//------------------------------------
	//	ONLINE EDIT
	//------------------------------------
	case "edit";	
		$file = utf8_decode($file);
		if(isset($save) && $save==1)
		{
			$code=stripslashes($code);
			$code=str_replace("&lt;","<",$code);
			$fp=fopen(ConfService::getRootDir()."/$file","w");
			fputs ($fp,$code);
			fclose($fp);
			//Utils::removeWinReturn(ConfService::getRootDir()."/$file");
			$logMessage = $mess[115];
			echo $logMessage;
		}
		else 
		{
			FS_Storage::readFile(ConfService::getRootDir()."/".$file, "plain");
		}
		exit(0);
	break;


	//------------------------------------
	//	COPY / MOVE
	//------------------------------------
	case "copy";
	case "move";
		
		if($selection->isEmpty())
		{
			$errorMessage = $mess[113];
			break;
		}
		$success = $error = array();
		
		FS_Storage::copyOrMove($dest, $selection->getFiles(), $error, $success, ($action=="move"?true:false));
		
		if(count($error)){
			$errorMessage = join("\n", $error);
		}
		else {
			$logMessage = join("\n", $success);
		}
		$reload_current_node = true;
		if(isSet($dest_node)) $reload_dest_node = $dest_node;
		$reload_file_list = true;
		
	break;
	
	//------------------------------------
	//	SUPPRIMER / DELETE
	//------------------------------------
	case "delete";
	
		if($selection->isEmpty())
		{
			$errorMessage = $mess[113];
			break;
		}
		$logMessages = array();
		$errorMessage = FS_Storage::delete($selection->getFiles(), $logMessages);
		if(count($logMessages))
		{
			$logMessage = join("\n", $logMessages);
		}
		$reload_current_node = true;
		$reload_file_list = true;
		
	break;


	//------------------------------------
	//	RENOMMER / RENAME
	//------------------------------------
	case "rename";
	
		$file = utf8_decode($file);
		$error = FS_Storage::rename($file, $filename_new);
		if($error != null) {
			$errorMessage  = $error;
			break;
		}
		$logMessage="$file $mess[41] $filename_new";
		$reload_current_node = true;
		$reload_file_list = basename($filename_new);

	break;


	//------------------------------------
	//	CREER UN REPERTOIRE / CREATE DIR
	//------------------------------------
	case "mkdir";
	
		$messtmp="";
		$dirname=Utils::processFileName(utf8_decode($dirname));
		$error = FS_Storage::mkDir($dir, $dirname);
		if(isSet($error)){
			$errorMessage = $error; break;
		}
		$reload_file_list = $dirname;
		$messtmp.="$mess[38] $dirname $mess[39] ";
		if($dir=="") {$messtmp.="/";} else {$messtmp.="$dir";}
		$logMessage = $messtmp;
		$reload_current_node = true;
		
	break;

	//------------------------------------
	//	CREER UN FICHIER / CREATE FILE
	//------------------------------------
	case "mkfile";
	
		$messtmp="";
		$filename=Utils::processFileName(utf8_decode($filename));	
		$error = FS_Storage::createEmptyFile($dir, $filename);
		if(isSet($error)){
			$errorMessage = $error; break;
		}
		$messtmp.="$mess[34] $filename $mess[39] ";
		if($dir=="") {$messtmp.="/";} else {$messtmp.="$dir";}
		$logMessage = $messtmp;
		$reload_file_list = $filename;

	break;
	

	//------------------------------------
	//	UPLOAD
	//------------------------------------	
	case "upload":

	if($dir!=""){$rep_source="/$dir";}
	else $rep_source = "";
	$destination=ConfService::getRootDir().$rep_source;
	if(!is_writable($destination))
	{
		$errorMessage = "$mess[38] $dir $mess[99].";
		break;
	}	
	$logMessage = "";
	$fancyLoader = false;
	foreach ($_FILES as $boxName => $boxData)
	{
		if(substr($boxName, 0, 9) == "userfile_")
		{
			foreach($boxData as $usFileName=>$usFileValue)
			{
				$varName = "userfile_".$usFileName;
				$$varName = $usFileValue;
			}
		}
		else if($boxName == 'Filedata')
		{
			$fancyLoader = true;
			foreach($boxData as $usFileName=>$usFileValue)
			{
				$varName = "userfile_".$usFileName;
				$$varName = $usFileValue;
			}			
		}
		else 
		{
			continue;
		}
		if ($userfile_error != UPLOAD_ERR_OK)
		{
			$errorsArray = array();
			$errorsArray[UPLOAD_ERR_FORM_SIZE] = $errorsArray[UPLOAD_ERR_INI_SIZE] = "409 : File is too big! Max is".ini_get("upload_max_filesize");
			$errorsArray[UPLOAD_ERR_NO_FILE] = "410 : No file found on server!($boxName)";
			$errorsArray[UPLOAD_ERR_PARTIAL] = "410 : File is partial";
			if($userfile_error == UPLOAD_ERR_NO_FILE && ereg('Opera',$_SERVER['HTTP_USER_AGENT']))
			{
				// BEURK : Opera hack, do not display "no file found error"
				continue;
			}
			$errorMessage = $errorsArray[$userfile_error];
			continue;
		}
		if ($userfile_size!=0)
		{
			$taille_ko=$userfile_size/1024;
		}
		else
		{
			$taille_ko=0;
		}
		if ($userfile_tmp_name=="none")
		{
			$errorMessage=$mess[31];
			break;
		}
		if ($userfile_tmp_name!="none" && $userfile_size!=0)
		{
			if($fancyLoader) $userfile_name = utf8_decode($userfile_name);
			$userfile_name=Utils::processFileName($userfile_name);
			if (!copy($userfile_tmp_name, "$destination/".$userfile_name))
			{
				$errorMessage=($fancyLoader?"411 ":"")."$mess[33] ".$userfile_name;
				break;
			}
			else
			{
				$logMessage.="$mess[34] ".$userfile_name." $mess[35] $dir";
			}
		}
	}
	if($fancyLoader)
	{
		header('HTTP/1.0 '.$errorMessage);
		die('Error '.$errorMessage);
	}
	else
	{
		print("<html><script language=\"javascript\">\n");
		if(isSet($errorMessage)){
			print("\n if(parent.ajaxplorer.actionBar.multi_selector)parent.ajaxplorer.actionBar.multi_selector.submitNext('".str_replace("'", "\'", $errorMessage)."');");		
		}else{		
			print("\n if(parent.ajaxplorer.actionBar.multi_selector)parent.ajaxplorer.actionBar.multi_selector.submitNext();");
		}
		print("</script></html>");
	}
	exit;
	break;
	
	//------------------------------------
	//	XML LISTING
	//------------------------------------
	case "xml_listing":
	
	if(!isSet($dir) || $dir == "/") $dir = "";
	$searchMode = $fileListMode = $completeMode = false;
	if(isSet($mode)){
		if($mode == "search") $searchMode = true;
		else if($mode == "file_list") $fileListMode = true;
		else if($mode == "complete") $completeMode = true;
	}	
	$nom_rep = FS_Storage::initName($dir);
	$result = FS_Storage::listing($nom_rep, !($searchMode || $fileListMode));
	$reps = $result[0];
	AJXP_XMLWriter::header();
	foreach ($reps as $repIndex => $repName)
	{
		$link = "content.php?id=&ordre=nom&sens=1&action=xml_listing&dir=".$dir."/".$repName;
		$link = str_replace("/", "%2F", $link);
		$link = str_replace("&", "&amp;", $link);
		$attributes = "";
		if($searchMode)
		{
			if(is_file($nom_rep."/".$repIndex)) {$attributes = "is_file=\"true\" icon=\"$repName\""; $repName = $repIndex;}
		}
		else if($fileListMode)
		{
			$currentFile = $nom_rep."/".$repIndex;			
			$atts = array();
			$atts[] = "is_file=\"".(is_file($currentFile)?"oui":"non")."\"";
			$atts[] = "is_editable=\"".Utils::is_editable($currentFile)."\"";
			$atts[] = "is_image=\"".Utils::is_image($currentFile)."\"";
			if(Utils::is_image($currentFile))
			{
				list($width, $height, $type, $attr) = @getimagesize($currentFile);
				$atts[] = "image_type=\"".image_type_to_mime_type($type)."\"";
				$atts[] = "image_width=\"$width\"";
				$atts[] = "image_height=\"$height\"";
			}
			$atts[] = "is_mp3=\"".Utils::is_mp3($currentFile)."\"";
			$atts[] = "mimetype=\"".Utils::mimetype($currentFile, "type")."\"";
			$atts[] = "modiftime=\"".FS_Storage::date_modif($currentFile)."\"";
			$atts[] = "filesize=\"".Utils::roundSize(filesize($currentFile))."\"";
			$atts[] = "filename=\"".$dir."/".str_replace("&", "&amp;", $repIndex)."\"";
			$atts[] = "icon=\"".(is_file($currentFile)?$repName:"folder.png")."\"";
			
			$attributes = join(" ", $atts);
			$repName = $repIndex;
		}
		else 
		{
			$folderBaseName = str_replace("&", "&amp;", $repName);
			$folderFullName = "$dir/".$folderBaseName;
			$parentFolderName = $dir;
			if(!$completeMode){
				$attributes = "icon=\"images/foldericon.png\"  openicon=\"images/openfoldericon.png\" filename=\"$folderFullName\" parentname=\"$parentFolderName\" src=\"$link\" action=\"javascript:ajaxplorer.clickDir('".$folderFullName."','".$parentFolderName."',CURRENT_ID)\"";
			}
		}
		print(utf8_encode("<tree text=\"".str_replace("&", "&amp;", $repName)."\" $attributes>"));
		print("</tree>");
	}
	if($nom_rep == ConfService::getRootDir() && ConfService::useRecycleBin() && !$completeMode)
	{
		if($fileListMode)
		{
			print(utf8_encode("<tree text=\"".str_replace("&", "&amp;", $mess[122])."\" filesize=\"-\" is_file=\"non\" is_recycle=\"1\" mimetype=\"Trashcan\" modiftime=\"".FS_Storage::date_modif(ConfService::getRootDir()."/".ConfService::getRecycleBinDir())."\" filename=\"/".ConfService::getRecycleBinDir()."\" icon=\"trashcan.png\"></tree>"));
		}
		else 
		{
			// ADD RECYCLE BIN TO THE LIST
			print("<tree text=\"$mess[122]\" is_recycle=\"true\" icon=\"images/crystal/mimes/16/trashcan.png\"  openIcon=\"images/crystal/mimes/16/trashcan.png\" filename=\"/".ConfService::getRecycleBinDir()."\" action=\"javascript:ajaxplorer.clickDir('/".ConfService::getRecycleBinDir()."','/',CURRENT_ID)\"/>");
		}
	}
	AJXP_XMLWriter::close();
	exit(1);
	break;		
		
	case "display_bookmark_bar":
	//------------------------------------
	//	BOOKMARK BAR
	//------------------------------------
	header("Content-type:text/html");
	$bmUser = null;
	if(AuthService::usersEnabled() && AuthService::getLoggedUser() != null)
	{
		$bmUser = AuthService::getLoggedUser();
	}
	else if(!AuthService::usersEnabled())
	{
		$bmUser = new AJXP_User("shared");
	}
	if($bmUser == null) exit(1);
	if(isSet($_GET["bm_action"]) && isset($_GET["bm_path"]))
	{
		if($_GET["bm_action"] == "add_bookmark")
		{
			$bmUser->addBookMark($_GET["bm_path"]);
		}
		else if($_GET["bm_action"] == "delete_bookmark")
		{
			$bmUser->removeBookmark($_GET["bm_path"]);
		}
	}
	if(AuthService::usersEnabled() && AuthService::getLoggedUser() != null)
	{
		$bmUser->save();
		AuthService::updateUser($bmUser);
	}
	else if(!AuthService::usersEnabled())
	{
		$bmUser->save();
	}
	HTMLWriter::bookmarkBar($bmUser->getBookMarks());
	session_write_close();
	exit(1);
	break;
		
	case "save_user_pref":
		$userObject = AuthService::getLoggedUser();
		if($userObject == null) exit(1);
		$i = 0;
		while(isSet($_GET["pref_name_".$i]) && isSet($_GET["pref_value_".$i]))
		{
			$prefName = $_GET["pref_name_".$i];
			$prefValue = $_GET["pref_value_".$i];
			if($prefName != "password")
			{
				$userObject->setPref($prefName, $prefValue);
				$userObject->save();
				AuthService::updateUser($userObject);
				setcookie("AJXP_$prefName", $prefValue);
			}
			else
			{
				AuthService::updatePassword($userObject->getId(), $prefValue);
			}
			$i++;
		}
		AJXP_XMLWriter::header();
		AJXP_XMLWriter::sendMessage("Done($i)", null);
		AJXP_XMLWriter::close();
		exit(1);
	break;
	
	case "display_doc":
	{
		echo HTMLWriter::getDocFile($_GET["doc_file"]);
		exit();
	}
	
	
		
	//------------------------------------
	//	DEFAUT
	//------------------------------------

	default;
	break;
}



AJXP_XMLWriter::header();

if(isset($logMessage) || isset($errorMessage))
{
	AJXP_XMLWriter::sendMessage((isSet($logMessage)?$logMessage:null), (isSet($errorMessage)?$errorMessage:null));
}

if(isset($requireAuth))
{
	AJXP_XMLWriter::requireAuth();
}

if(isset($reload_current_node) && $reload_current_node == "true")
{
	AJXP_XMLWriter::reloadCurrentNode();
}

if(isset($reload_dest_node) && $reload_dest_node != "")
{
	AJXP_XMLWriter::reloadNode($reload_dest_node);
}

if(isset($reload_file_list))
{
	AJXP_XMLWriter::reloadFileList($reload_file_list);
}

AJXP_XMLWriter::close();



session_write_close();
?>
